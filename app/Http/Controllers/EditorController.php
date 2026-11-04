<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesRunOwner;
use App\Models\AnalysisRun;
use App\Models\DesignSpec;
use App\Models\WebsiteAsset;
use App\Services\Editing\EditableFields;
use App\Services\Editing\SectionOverrides;
use App\Services\Editing\SpecEditException;
use App\Services\Editing\SpecEditService;
use App\Services\Hero\HeroDestinations;
use App\Models\AiImageGeneration;
use App\Services\Assets\MuseImageService;
use App\Services\Billing\ExportPassAllowance;
use App\Services\Images\AiImageQuota;
use App\Services\Images\Unsplash;
use Illuminate\Support\Facades\Cache;
use App\Services\Rendering\PreviewRenderer;
use App\Services\Widgets\WidgetCatalog;
use App\Support\Memory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * In-place editing of a generated site. Everything here is signed-in-owner only. The page being edited is rendered by
 * the same trusted renderer as the preview; the editor only ever sends typed operations (see SpecEditService).
 */
class EditorController extends Controller
{
    use AuthorizesRunOwner;

    public function show(Request $request, AnalysisRun $analysisRun, EditableFields $fields)
    {
        $this->own($request, $analysisRun);
        $spec = $analysisRun->currentDesignSpec();
        if (! $spec) {
            return redirect()->route('analyses.show', $analysisRun->public_id);
        }
        if (! isset($spec->design_spec_json['v15'])) {
            // A design from the older generator: instead of refusing, rebuild it so it can be edited, then come back here.
            return view('analyses.making-editable', ['run' => $analysisRun]);
        }

        return view('analyses.edit', [
            'run' => $analysisRun, 'spec' => $spec, 'user' => $request->user(), 'canLaunch' => $request->user()->canPublish() || $request->user()->canExportStatic(),
            'state' => $this->state($analysisRun, $spec, $fields),
            'frameUrl' => route('analyses.edit.frame', $analysisRun->public_id),
        ]);
    }

    /** The page being edited: the real rendered document plus a small bridge script, under a CSP that allows only that script. */
    public function frame(Request $request, AnalysisRun $analysisRun, PreviewRenderer $renderer, EditableFields $fields): Response
    {
        $this->own($request, $analysisRun);
        $spec = $analysisRun->currentDesignSpec() ?? abort(404);
        $doc = $renderer->renderBlueprintDocument($spec->design_spec_json, $analysisRun, true) ?? abort(404);

        $nonce = base64_encode(random_bytes(16));
        $map = [];
        foreach ($fields->forSpec($spec->design_spec_json['v15'], $analysisRun) as $s) {
            foreach ($s['fields'] as $f) {
                $map[] = ['id' => $s['instance_id'], 'path' => $f['path'], 'type' => $f['type'], 'value' => $f['value'] ?? ($f['type'] === 'image' ? ($f['asset_id'] ?? '') : ''), 'max' => $f['max'] ?? 0, 'hero' => $f['hero'] ?? false, 'required' => $f['required']];
            }
        }
        $inject = view('analyses.partials.edit-bridge', ['nonce' => $nonce, 'fields' => $map])->render();
        $html = ($pos = strripos($doc, '</body>')) !== false ? substr_replace($doc, $inject, $pos, 0) : $doc.$inject;

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'no-store',
            'Content-Security-Policy' => "default-src 'none'; script-src 'nonce-{$nonce}'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self' data:; frame-ancestors 'self'; base-uri 'none'; form-action 'none'",
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);
    }

    public function apply(Request $request, AnalysisRun $analysisRun, SpecEditService $svc, EditableFields $fields): JsonResponse
    {
        $this->own($request, $analysisRun);
        $data = $request->validate(['base_version' => ['required', 'integer'], 'ops' => ['required', 'array', 'min:1', 'max:40'], 'ops.*.op' => ['required', 'string', 'max:20'], 'ops.*.instance_id' => ['nullable', 'string', 'max:80']]);
        $spec = $analysisRun->currentDesignSpec() ?? abort(404);
        if ($blocked = $this->editsUsedUp($request, $analysisRun)) {
            return $blocked;
        }

        try {
            $r = $svc->apply($spec, (int) $data['base_version'], $request->input('ops'), $request->user());
        } catch (SpecEditException $e) {
            return response()->json(['message' => $e->getMessage(), 'stale' => $e->stale, 'errors' => $e->errors], $e->stale ? 409 : 422);
        }

        return response()->json(['version' => $r['spec']->version, 'warnings' => $r['warnings'], 'coalesced' => $r['coalesced']] + $this->state($analysisRun, $r['spec'], $fields));
    }

    /** Keep the edits made so far as a saved layout: the next change starts a new version, so this one stays in History. */
    public function save(Request $request, AnalysisRun $analysisRun, SpecEditService $svc, EditableFields $fields): JsonResponse
    {
        $this->own($request, $analysisRun);
        $data = $request->validate(['base_version' => ['required', 'integer']]);
        $spec = $analysisRun->currentDesignSpec() ?? abort(404);
        $hadDraft = ! empty($spec->design_spec_json['v16']['edit_draft']);
        if ($hadDraft && ($blocked = $this->editsUsedUp($request, $analysisRun))) {
            return $blocked;
        }

        try {
            $saved = $svc->save($spec, (int) $data['base_version'], $request->user());
        } catch (SpecEditException $e) {
            return response()->json(['message' => $e->getMessage(), 'stale' => $e->stale], $e->stale ? 409 : 422);
        }
        if ($hadDraft && $saved->id === $spec->id && empty($saved->design_spec_json['v16']['edit_draft'])) {
            app(ExportPassAllowance::class)->consumeEdit($request->user(), $analysisRun); // a real saved edit uses one of the pass's included edits
        }

        return response()->json(['version' => $saved->version] + $this->state($analysisRun, $saved, $fields));
    }

    public function restore(Request $request, AnalysisRun $analysisRun, int $version, SpecEditService $svc, EditableFields $fields): JsonResponse
    {
        $this->own($request, $analysisRun);
        $target = $analysisRun->designSpecs()->where('version', $version)->firstOrFail();
        $current = $analysisRun->currentDesignSpec();
        if ($current->id === $target->id) {
            return response()->json(['message' => 'That is already the current version.'], 422);
        }
        try {
            $new = $svc->restore($current, $target, $request->user());
        } catch (SpecEditException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['version' => $new->version] + $this->state($analysisRun, $new, $fields));
    }

    /** Add a picture of the user's own. Re-encoded server-side so only a real, bounded image is ever stored. */
    public function upload(Request $request, AnalysisRun $analysisRun): JsonResponse
    {
        $this->own($request, $analysisRun);
        $request->validate(['image' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp']]);
        $r = $this->ingest($analysisRun, $request->file('image')->getRealPath(), (string) $request->input('alt', ''), 'user_upload', 'upload://', null);

        return $r instanceof JsonResponse ? $r : response()->json(['asset' => $this->assetJson($r)]);
    }

    /** Search Unsplash (server-side, so the access key never reaches the browser). */
    public function unsplashSearch(Request $request, AnalysisRun $analysisRun, Unsplash $unsplash): JsonResponse
    {
        $this->own($request, $analysisRun);
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:80'], 'page' => ['nullable', 'integer', 'min:1', 'max:20']]);
        if (! $unsplash->configured()) {
            return response()->json(['message' => 'Unsplash search isn’t set up yet. Upload your own picture instead.', 'configured' => false], 503);
        }
        try {
            return response()->json($unsplash->search($data['q'], (int) ($data['page'] ?? 1)) + ['configured' => true]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'configured' => true], 502);
        }
    }

    /** Copy the chosen Unsplash photo into this project's own assets (and report the download to Unsplash, as their API terms ask). */
    public function unsplashUse(Request $request, AnalysisRun $analysisRun, Unsplash $unsplash): JsonResponse
    {
        $this->own($request, $analysisRun);
        $data = $request->validate(['photo_id' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{6,40}$/'], 'alt' => ['nullable', 'string', 'max:200']]);
        if (! $unsplash->configured()) {
            return response()->json(['message' => 'Unsplash search isn’t set up yet.'], 503);
        }
        $tmp = null;
        try {
            $photo = $unsplash->photo($data['photo_id']);
            $tmp = $unsplash->download($photo);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }
        try {
            $alt = trim((string) ($data['alt'] ?? '')) ?: $photo['alt'];
            $r = $this->ingest($analysisRun, $tmp, $alt, 'unsplash', 'unsplash://', $photo['link']);
        } finally {
            @unlink($tmp);
        }

        return $r instanceof JsonResponse ? $r : response()->json(['asset' => $this->assetJson($r) + ['credit' => ['name' => $photo['author'], 'url' => $photo['author_url']]]]);
    }

    /** How many AI images this person can still make (Free: 2 in total; Starter and Agency: 5 each month). */
    public function aiImageStatus(Request $request, AnalysisRun $analysisRun, AiImageQuota $quota, MuseImageService $muse): JsonResponse
    {
        $this->own($request, $analysisRun);

        return response()->json($quota->for($request->user()) + ['configured' => $muse->isConfigured(), 'site' => (string) (parse_url($analysisRun->normalized_url, PHP_URL_HOST) ?: '')]);
    }

    /** Make a picture with Meta's Muse Image model, within the plan's allowance. Only a successful picture counts against it. */
    public function aiImageGenerate(Request $request, AnalysisRun $analysisRun, AiImageQuota $quota, MuseImageService $muse): JsonResponse
    {
        $this->own($request, $analysisRun);
        $data = $request->validate(['prompt' => ['required', 'string', 'min:8', 'max:400'], 'alt' => ['nullable', 'string', 'max:200']]);
        $user = $request->user();
        if (! $muse->isConfigured()) {
            return response()->json(['message' => 'AI image generation isn’t set up yet. Upload a picture or search Unsplash instead.'], 503);
        }
        // One at a time per person, so two quick clicks can't squeeze past the allowance.
        $lock = Cache::lock('ai-image:'.$user->id, 120);
        if (! $lock->get()) {
            return response()->json(['message' => 'Your last image is still being made — one moment.'], 429);
        }
        try {
            $q = $quota->for($user);
            if ($q['remaining'] !== null && $q['remaining'] < 1) {
                return response()->json(['message' => $q['period'] === 'month' ? 'You’ve used all 5 AI images for this month. They reset on '.\Illuminate\Support\Carbon::parse($q['resets_at'])->format('j F').'.' : 'You’ve used both of your free AI images. Upgrade to Starter for 5 every month.'] + $q, 402);
            }
            $host = parse_url($analysisRun->normalized_url, PHP_URL_HOST) ?: '';
            $prompt = trim(strip_tags($data['prompt'])).'. A beautiful, professional photograph-style banner image for the website '.$host.'. Wide landscape composition, natural light, no text, no letters, no logos, no watermarks.';
            try {
                $bytes = $muse->generateBytes($prompt);
            } catch (\RuntimeException $e) {
                return response()->json(['message' => $e->getMessage()] + $q, 502);
            }
            $tmp = tempnam(sys_get_temp_dir(), 'museimg');
            file_put_contents($tmp, $bytes);
            try {
                $r = $this->ingest($analysisRun, $tmp, trim((string) ($data['alt'] ?? '')) ?: mb_substr(trim($data['prompt']), 0, 200), 'ai_image', 'ai-generated://muse-image-1.0', null);
            } finally {
                @unlink($tmp);
            }
            if ($r instanceof JsonResponse) {
                return $r;
            }
            AiImageGeneration::create(['user_id' => $user->id, 'analysis_run_id' => $analysisRun->id, 'prompt' => mb_substr(trim($data['prompt']), 0, 600), 'asset_key' => $r->asset_key, 'created_at' => now()]);

            return response()->json(['asset' => $this->assetJson($r)] + $quota->for($user->fresh()));
        } finally {
            $lock->release();
        }
    }

    /** The one safe path an image takes into a project: validated, bounded, re-encoded (PNG/WebP keep transparency), stored. */
    private function ingest(AnalysisRun $analysisRun, string $file, string $alt, string $origin, string $urlScheme, ?string $sourceUrl): WebsiteAsset|JsonResponse
    {
        if ($analysisRun->websiteAssets()->whereIn('asset_origin', ['user_upload', 'unsplash', 'ai_image'])->count() >= 24) {
            return response()->json(['message' => 'You’ve reached the picture limit for this project (24 added pictures).'], 422);
        }
        $info = @getimagesize($file);
        if (! $info || $info[0] < 200 || $info[1] < 120 || $info[0] > 8000 || $info[1] > 8000) {
            return response()->json(['message' => 'Please use a photo at least 200×120 and no larger than 8000 pixels.'], 422);
        }
        // GD holds ~4 bytes per pixel (plus working copies): refuse what the memory limit could not decode instead of dying with a 500.
        $limit = Memory::limitBytes();
        if ($limit !== null && ($info[0] * $info[1] * 5 + memory_get_usage()) > $limit * 0.8) {
            return response()->json(['message' => 'That picture is too large to process. Please use one under about 4000 pixels wide.'], 422);
        }
        if ($info[2] === IMAGETYPE_WEBP && (! function_exists('imagecreatefromwebp') || ! function_exists('imagewebp'))) {
            return response()->json(['message' => 'WebP isn’t supported here. Please use a JPG or PNG.'], 422);
        }
        $src = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file), IMAGETYPE_PNG => @imagecreatefrompng($file), IMAGETYPE_WEBP => @imagecreatefromwebp($file), default => false
        };
        if (! $src) {
            return response()->json(['message' => 'That file couldn’t be read as an image.'], 422);
        }
        // Scale down (max 2400px) and re-encode: strips metadata and anything that is not pixels. PNG and WebP keep their
        // transparency and format; JPEG stays JPEG (no alpha to lose).
        $scale = min(1, 2400 / max($info[0], $info[1]));
        $w = max(1, (int) round($info[0] * $scale));
        $h = max(1, (int) round($info[1] * $scale));
        $dst = imagecreatetruecolor($w, $h);
        if ($info[2] !== IMAGETYPE_JPEG) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        } else {
            imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, $info[0], $info[1]);
        $hasAlpha = $info[2] !== IMAGETYPE_JPEG && \App\Support\ImageAlpha::hasTransparency($dst);
        [$ext, $mime] = match ($info[2]) {
            IMAGETYPE_PNG => ['png', 'image/png'],
            IMAGETYPE_WEBP => ['webp', 'image/webp'],
            default => ['jpg', 'image/jpeg'],
        };
        ob_start();
        match ($ext) {
            'png' => imagepng($dst, null, 7),
            'webp' => imagewebp($dst, null, 88),
            default => imagejpeg($dst, null, 86),
        };
        $bytes = (string) ob_get_clean();

        $key = 'up'.Str::lower(Str::random(10));
        $path = "site-assets/{$analysisRun->public_id}/{$key}.{$ext}";
        Storage::disk('public')->put($path, $bytes);
        $alt = mb_substr(trim(strip_tags($alt)), 0, 200);

        return WebsiteAsset::create([
            'analysis_run_id' => $analysisRun->id, 'asset_key' => $key, 'source_page_url' => $sourceUrl ?: $analysisRun->normalized_url, 'original_url' => $urlScheme.$key, 'stored_path' => $path,
            'asset_type' => 'hero_image', 'asset_origin' => $origin, 'mime_type' => $mime, 'has_alpha' => $hasAlpha, 'file_size' => strlen($bytes), 'width' => $w, 'height' => $h, 'alt_text' => $alt ?: null,
            'recommended_action' => 'reuse', 'usage_status' => 'selected', 'rights_status' => match ($origin) { 'unsplash' => 'unsplash_license', 'ai_image' => 'ai_generated', default => 'user_provided' }, 'attribution' => $origin === 'ai_image' ? 'AI-generated image (Meta Muse Image)' : null, 'created_at' => now(),
        ]);
    }

    // ---------------------------------------------------------------- helpers

    /** A Free-plan Export Pass includes two saved edits for its website; once they are used, editing it needs Starter or Agency. */
    private function editsUsedUp(Request $request, AnalysisRun $run): ?JsonResponse
    {
        $cap = app(ExportPassAllowance::class)->capFor($request->user(), $run);
        if ($cap === null || $cap['edits'] > 0) {
            return null;
        }

        return response()->json(['message' => 'You’ve used the edits included with this website’s Export Pass. Choose Starter or Agency for unlimited edits and downloads.', 'upgrade' => route('analyses.upgrade', $run->public_id)], 402);
    }

    private function state(AnalysisRun $run, DesignSpec $spec, EditableFields $fields): array
    {
        $v16 = $spec->design_spec_json['v16'] ?? [];

        return [
            'version' => $spec->version,
            'sections' => $fields->forSpec($spec->design_spec_json['v15'], $run),
            'hidden' => $fields->hidden($v16),
            'destinations' => collect(app(HeroDestinations::class)->forSpec($spec->design_spec_json))->map(fn ($d, $k) => ['key' => $k, 'label' => $d['label']])->values()->all(),
            'assets' => $run->websiteAssets()->whereNotNull('stored_path')->whereNotIn('asset_type', ['logo', 'favicon', 'icon'])->orderByRaw("asset_origin IN ('user_upload','unsplash','ai_image') DESC")->orderByDesc('relevance_score')->limit(40)->get()->map(fn ($a) => $this->assetJson($a))->values()->all(),
            'versions' => $run->designSpecs()->orderByDesc('version')->limit(30)->get()->map(fn ($s) => [
                'version' => $s->version, 'at' => $s->created_at?->diffForHumans(), 'summary' => $s->design_spec_json['v16']['change_summary'] ?? ($s->version === 1 ? 'Generated by REDO' : 'Generated variation'),
            ])->all(),
            'widgets' => collect($spec->design_spec_json['v15']['pages'][0]['widgets'] ?? [])->map(fn ($w) => $w + ['summary' => app(WidgetCatalog::class)->summary($w['type'], $w['config'] ?? [])])->values()->all(),
            'widget_types' => app(WidgetCatalog::class)->types(),
            'edits' => count($v16['user_edits'] ?? []),
            'pass' => app(ExportPassAllowance::class)->capFor(request()->user(), $run),
            'unsaved' => ! empty($v16['edit_draft']),
            'fonts' => app(SectionOverrides::class)->fontChoices(),
            'hero_status' => $v16['hero_content']['approval_status'] ?? null,
            'hero_fit' => $v16['hero_content']['validation'] ?? null,
        ];
    }

    private function assetJson(WebsiteAsset $a): array
    {
        return ['asset_id' => $a->asset_key, 'url' => $a->url(), 'alt' => $a->alt_text ?: '', 'width' => $a->width, 'height' => $a->height, 'origin' => $a->asset_origin ?: 'original', 'type' => $a->asset_type];
    }
}
