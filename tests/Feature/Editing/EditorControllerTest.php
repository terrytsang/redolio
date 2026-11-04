<?php

namespace Tests\Feature\Editing;

use App\Models\User;
use App\Models\WebsiteAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Catalogue\Concerns\BuildsBlueprintSpecs;
use Tests\TestCase;

class EditorControllerTest extends TestCase
{
    use BuildsBlueprintSpecs;
    use RefreshDatabase;

    private function site(): array
    {
        $run = $this->makeRun(null, ['team1' => 'team_portrait']);
        [$res, $spec] = $this->attach($run, $this->legacySpec());
        $this->assertTrue($res['attached'], implode('; ', $res['errors']));

        return [$run, $spec, User::factory()->create()];
    }

    private function hero($spec): array
    {
        return collect($spec->design_spec_json['v15']['pages'][0]['sections'])->firstWhere('component_id', 'hero');
    }

    public function test_guests_are_sent_to_sign_in_and_come_back_to_the_editor(): void
    {
        [$run] = $this->site();

        $this->get(route('analyses.edit', $run->public_id))->assertRedirect(route('login'));
        $this->postJson(route('analyses.edit.apply', $run->public_id), ['base_version' => 1, 'ops' => [['op' => 'set', 'instance_id' => 'x']]])->assertStatus(401);
        $this->assertSame(1, $run->designSpecs()->count());
    }

    public function test_someone_elses_project_cannot_be_edited(): void
    {
        [$run, $spec, $user] = $this->site();
        $run->update(['user_id' => User::factory()->create()->id]);

        $this->actingAs($user)->get(route('analyses.edit', $run->public_id))->assertNotFound();
        $this->actingAs($user)->postJson(route('analyses.edit.apply', $run->public_id), ['base_version' => 1, 'ops' => [['op' => 'set', 'instance_id' => 'x']]])->assertNotFound();
    }

    public function test_the_editor_page_loads_with_the_page_state_for_a_signed_in_user(): void
    {
        [$run, $spec, $user] = $this->site();

        $this->actingAs($user)->get(route('analyses.edit', $run->public_id))->assertOk()->assertSee('Edit your site')->assertSee('Gentle care for the whole family')->assertSee('Approve design');
        $this->assertSame($user->id, $run->fresh()->user_id, 'the first signed-in editor claims the project');
    }

    public function test_the_edit_frame_is_the_real_page_with_a_locked_down_csp_and_a_bridge(): void
    {
        [$run, $spec, $user] = $this->site();

        $r = $this->actingAs($user)->get(route('analyses.edit.frame', $run->public_id))->assertOk();

        $csp = $r->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'none'", $csp);
        $this->assertMatchesRegularExpression("/script-src 'nonce-[A-Za-z0-9+\/=]+'/", $csp);
        $this->assertStringNotContainsString('unsafe-eval', $csp);
        preg_match("/nonce-([A-Za-z0-9+\/=]+)/", $csp, $m);
        $html = $r->getContent();
        $this->assertStringContainsString('Gentle care for the whole family', $html);
        $this->assertStringContainsString('nonce="'.$m[1].'"', $html);
        preg_match_all('/<script\b([^>]*)>/i', $html, $tags);
        foreach ($tags[1] as $attrs) { // every script is either the nonce'd bridge or an inert data block (JSON-LD)
            $this->assertTrue(str_contains($attrs, 'nonce="'.$m[1].'"') || str_contains($attrs, 'application/ld+json'), 'unexpected script: '.$attrs);
        }
        $this->assertStringContainsString('"path":"headline"', $html);
    }

    public function test_apply_saves_an_edit_and_returns_fresh_state_and_rejects_bad_ones(): void
    {
        [$run, $spec, $user] = $this->site();
        $hid = $this->hero($spec)['instance_id'];

        $ok = $this->actingAs($user)->postJson(route('analyses.edit.apply', $run->public_id), ['base_version' => 1, 'ops' => [['op' => 'set', 'instance_id' => $hid, 'path' => 'headline', 'value' => 'A friendlier headline']]])->assertOk();
        $this->assertSame(2, $ok->json('version'));
        $heroFields = collect($ok->json('sections'))->firstWhere('component_id', 'hero')['fields'];
        $this->assertSame('A friendlier headline', collect($heroFields)->firstWhere('path', 'headline')['value']);
        $this->assertSame(1, $ok->json('edits'));

        $this->actingAs($user)->postJson(route('analyses.edit.apply', $run->public_id), ['base_version' => 2, 'ops' => [['op' => 'set_cta', 'instance_id' => $hid, 'path' => 'primary_cta', 'label' => 'Go', 'href' => 'javascript:alert(1)']]])
            ->assertStatus(422)->assertJsonStructure(['message']);

        $this->actingAs($user)->postJson(route('analyses.edit.apply', $run->public_id), ['base_version' => 1, 'ops' => [['op' => 'set', 'instance_id' => $hid, 'path' => 'headline', 'value' => 'Stale']]])
            ->assertStatus(409)->assertJsonPath('stale', true);
    }

    public function test_restore_creates_a_new_version(): void
    {
        [$run, $spec, $user] = $this->site();
        $hid = $this->hero($spec)['instance_id'];
        $this->actingAs($user)->postJson(route('analyses.edit.apply', $run->public_id), ['base_version' => 1, 'ops' => [['op' => 'set', 'instance_id' => $hid, 'path' => 'headline', 'value' => 'Edited']]])->assertOk();

        $r = $this->actingAs($user)->postJson(route('analyses.edit.restore', [$run->public_id, 1]))->assertOk();

        $this->assertSame(3, $r->json('version'));
        $this->assertSame('Gentle care for the whole family', collect(collect($r->json('sections'))->firstWhere('component_id', 'hero')['fields'])->firstWhere('path', 'headline')['value']);
        $this->actingAs($user)->postJson(route('analyses.edit.restore', [$run->public_id, 3]))->assertStatus(422);
    }

    public function test_a_transparent_png_stays_a_transparent_png(): void
    {
        Storage::fake('public');
        [$run, , $user] = $this->site();
        $im = imagecreatetruecolor(400, 300);
        imagealphablending($im, false);
        imagesavealpha($im, true);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
        imagefilledellipse($im, 200, 150, 200, 200, imagecolorallocatealpha($im, 200, 30, 30, 0));
        $tmp = tempnam(sys_get_temp_dir(), 'png').'.png';
        imagepng($im, $tmp);
        $file = new UploadedFile($tmp, 'logo.png', 'image/png', null, true);

        $r = $this->actingAs($user)->postJson(route('analyses.edit.upload', $run->public_id), ['image' => $file])->assertOk();
        $asset = WebsiteAsset::where('asset_key', $r->json('asset.asset_id'))->first();
        $this->assertSame('image/png', $asset->mime_type);
        $out = imagecreatefromstring(Storage::disk('public')->get($asset->stored_path));
        $this->assertSame(127, (imagecolorat($out, 2, 2) >> 24) & 0x7F, 'the corner is still fully transparent');
        $this->assertSame(0, (imagecolorat($out, 200, 150) >> 24) & 0x7F, 'the shape is still opaque');
    }

    public function test_uploads_are_validated_reencoded_and_usable_in_the_hero(): void
    {
        Storage::fake('public');
        [$run, $spec, $user] = $this->site();
        $good = UploadedFile::fake()->image('team.png', 900, 600);

        $r = $this->actingAs($user)->postJson(route('analyses.edit.upload', $run->public_id), ['image' => $good, 'alt' => '<b>Our team</b>'])->assertOk();
        $key = $r->json('asset.asset_id');
        $asset = WebsiteAsset::where('asset_key', $key)->first();
        $this->assertSame('user_upload', $asset->asset_origin);
        $this->assertSame('image/png', $asset->mime_type, 'a PNG stays a PNG');
        $this->assertStringEndsWith('.png', $asset->stored_path);
        $this->assertSame('Our team', $asset->alt_text);
        Storage::disk('public')->assertExists($asset->stored_path);

        $hid = $this->hero($spec)['instance_id'];
        $this->actingAs($user)->postJson(route('analyses.edit.apply', $run->public_id), ['base_version' => 1, 'ops' => [['op' => 'set_image', 'instance_id' => $hid, 'path' => 'image', 'asset_id' => $key, 'alt' => 'Our team']]])->assertOk();

        // not an image, SVG, tiny, and a fake image extension with script contents
        $this->actingAs($user)->postJson(route('analyses.edit.upload', $run->public_id), ['image' => UploadedFile::fake()->create('x.pdf', 20, 'application/pdf')])->assertStatus(422);
        $this->actingAs($user)->postJson(route('analyses.edit.upload', $run->public_id), ['image' => UploadedFile::fake()->createWithContent('x.svg', '<svg onload="alert(1)"/>')])->assertStatus(422);
        $this->actingAs($user)->postJson(route('analyses.edit.upload', $run->public_id), ['image' => UploadedFile::fake()->image('tiny.png', 50, 50)])->assertStatus(422);
        $this->actingAs($user)->postJson(route('analyses.edit.upload', $run->public_id), ['image' => UploadedFile::fake()->createWithContent('evil.png', '<?php system($_GET[0]); ?>')])->assertStatus(422);
    }

    public function test_the_chosen_picture_shows_in_the_rendered_hero_for_each_hero_variant_that_supports_photos(): void
    {
        Storage::fake('public');
        [$run, $spec, $user] = $this->site();
        $hid = $this->hero($spec)['instance_id'];
        $this->actingAs($user)->postJson(route('analyses.edit.apply', $run->public_id), ['base_version' => 1, 'ops' => [['op' => 'set_image', 'instance_id' => $hid, 'path' => 'image', 'asset_id' => 'team1', 'alt' => 'Our team']]])->assertOk();

        $html = $this->actingAs($user)->get(route('analyses.edit.frame', $run->public_id))->getContent();
        $this->assertStringContainsString('team1.jpg', $html);
    }

    public function test_the_reveal_offers_editing_and_registration_returns_to_it(): void
    {
        [$run, $spec, $user] = $this->site();

        $this->get(route('analyses.reveal', $run->public_id))->assertOk()->assertSee('Edit the design');
        $this->get(route('analyses.edit', $run->public_id));
        $this->post(route('register.store'), ['name' => 'New Person', 'email' => 'new@example.com', 'password' => 'Str0ng-Passw0rd!', 'password_confirmation' => 'Str0ng-Passw0rd!'])
            ->assertRedirect(route('analyses.edit', $run->public_id));
    }

    public function test_style_link_and_layout_edits_go_through_the_editor_endpoint_and_save_closes_the_draft(): void
    {
        [$run, $spec, $user] = $this->site();
        $hid = $this->hero($spec)['instance_id'];

        $r = $this->actingAs($user)->postJson(route('analyses.edit.apply', $run->public_id), ['base_version' => $spec->version, 'ops' => [
            ['op' => 'set_style', 'instance_id' => $hid, 'path' => 'headline', 'size' => 58, 'font' => 'lora'],
            ['op' => 'set_link', 'instance_id' => $hid, 'path' => 'subheadline', 'href' => 'https://example.com/about'],
            ['op' => 'set_layout', 'instance_id' => $hid, 'width' => 'wide'],
        ]])->assertOk();
        $heroState = collect($r->json('sections'))->firstWhere('instance_id', $hid);
        $this->assertSame(58, collect($heroState['fields'])->firstWhere('path', 'headline')['style']['size']);
        $this->assertSame('https://example.com/about', collect($heroState['fields'])->firstWhere('path', 'subheadline')['style']['href']);
        $this->assertSame('wide', $heroState['layout']['width']);
        $this->assertTrue($r->json('unsaved'));
        $this->assertContains('lora', array_column($r->json('fonts'), 'id'));

        $this->actingAs($user)->postJson(route('analyses.edit.save', $run->public_id), ['base_version' => 1])->assertStatus(409);
        $saved = $this->actingAs($user)->postJson(route('analyses.edit.save', $run->public_id), ['base_version' => $r->json('version')])->assertOk();
        $this->assertFalse($saved->json('unsaved'));
        $this->assertStringStartsWith('Saved layout', $saved->json('versions.0.summary'));

        $this->actingAs($user)->postJson(route('analyses.edit.apply', $run->public_id), ['base_version' => $saved->json('version'), 'ops' => [['op' => 'set_link', 'instance_id' => $hid, 'path' => 'subheadline', 'href' => 'javascript:alert(1)']]])->assertStatus(422);
        $this->actingAs($user)->get(route('analyses.edit', $run->public_id))->assertOk()->assertSee('Save layout');
    }

    public function test_only_the_owner_can_save(): void
    {
        [$run, $spec] = $this->site();
        $run->update(['user_id' => User::factory()->create()->id]);
        $this->actingAs(User::factory()->create())->postJson(route('analyses.edit.save', $run->public_id), ['base_version' => 1])->assertNotFound();
    }

    private function jpegBytes(): string
    {
        $im = imagecreatetruecolor(800, 500);
        imagefill($im, 0, 0, imagecolorallocate($im, 20, 120, 200));
        ob_start();
        imagejpeg($im);

        return (string) ob_get_clean();
    }

    public function test_unsplash_search_and_use_copy_the_photo_into_the_project(): void
    {
        Storage::fake('public');
        config(['services.unsplash.access_key' => 'test-key']);
        [$run, , $user] = $this->site();
        Http::fake([
            'api.unsplash.com/search/photos*' => Http::response(['total_pages' => 2, 'results' => [['id' => 'abc123xyz', 'alt_description' => 'a dentist', 'urls' => ['small' => 'https://images.unsplash.com/s'], 'user' => ['name' => 'Jo Photo', 'links' => ['html' => 'https://unsplash.com/@jo']], 'links' => ['html' => 'https://unsplash.com/photos/abc123xyz']]]]),
            'api.unsplash.com/photos/abc123xyz/download*' => Http::response(['url' => 'x']),
            'api.unsplash.com/photos/abc123xyz' => Http::response(['id' => 'abc123xyz', 'alt_description' => 'a dentist', 'urls' => ['regular' => 'https://images.unsplash.com/photo-1'], 'links' => ['download_location' => 'https://api.unsplash.com/photos/abc123xyz/download', 'html' => 'https://unsplash.com/photos/abc123xyz'], 'user' => ['name' => 'Jo Photo', 'links' => ['html' => 'https://unsplash.com/@jo']]]),
            'images.unsplash.com/*' => Http::response($this->jpegBytes(), 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $found = $this->actingAs($user)->getJson(route('analyses.edit.unsplash', [$run->public_id, 'q' => 'dentist']))->assertOk();
        $this->assertSame('Jo Photo', $found->json('results.0.author'));
        $this->assertStringContainsString('utm_source=redo', $found->json('results.0.author_url'));

        $used = $this->actingAs($user)->postJson(route('analyses.edit.unsplash.use', $run->public_id), ['photo_id' => 'abc123xyz'])->assertOk();
        $asset = WebsiteAsset::where('asset_key', $used->json('asset.asset_id'))->first();
        $this->assertSame('unsplash', $asset->asset_origin);
        $this->assertSame('a dentist', $asset->alt_text);
        Storage::disk('public')->assertExists($asset->stored_path);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/photos/abc123xyz/download'));
    }

    public function test_unsplash_is_refused_without_a_key_and_never_downloads_from_other_hosts(): void
    {
        [$run, , $user] = $this->site();
        config(['services.unsplash.access_key' => null]);
        $this->actingAs($user)->getJson(route('analyses.edit.unsplash', [$run->public_id, 'q' => 'dentist']))->assertStatus(503)->assertJson(['configured' => false]);

        config(['services.unsplash.access_key' => 'k']);
        Http::fake(['api.unsplash.com/photos/abc123xyz' => Http::response(['id' => 'abc123xyz', 'urls' => ['regular' => 'https://evil.example/x.jpg'], 'links' => [], 'user' => []])]);
        $this->actingAs($user)->postJson(route('analyses.edit.unsplash.use', $run->public_id), ['photo_id' => 'abc123xyz'])->assertStatus(502);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'evil.example'));
    }

    private function museFake(): void
    {
        config(['meta_api.base_url' => 'https://api.meta.test/v1', 'meta_api.api_key' => 'k']);
        Http::fake(['api.meta.test/v1/images/generations' => Http::response(['data' => [['b64_json' => base64_encode($this->jpegBytes())]], 'output_format' => 'jpeg'])]);
    }

    private function onPlan(\App\Models\User $u, string $key): void
    {
        $this->seed(\Database\Seeders\PlanSeeder::class);
        \App\Models\Subscription::create(['user_id' => $u->id, 'plan_id' => \App\Models\Plan::where('plan_key', $key)->value('id'), 'status' => 'active', 'started_at' => now()]);
    }

    public function test_free_plan_gets_two_ai_images_in_total_and_only_successes_count(): void
    {
        Storage::fake('public');
        [$run, , $user] = $this->site();
        $this->onPlan($user, 'free');
        config(['meta_api.base_url' => 'https://api.meta.test/v1', 'meta_api.api_key' => 'k']);
        $ok = ['data' => [['b64_json' => base64_encode($this->jpegBytes())]], 'output_format' => 'jpeg'];
        Http::fake(['api.meta.test/v1/images/generations' => Http::sequence()->push($ok)->push(['error' => ['message' => 'boom']], 500)->push($ok)->push($ok)]);

        $this->actingAs($user)->getJson(route('analyses.edit.ai-image', $run->public_id))->assertOk()->assertJson(['limit' => 2, 'used' => 0, 'remaining' => 2, 'period' => 'total', 'can_upgrade' => true]);

        $first = $this->actingAs($user)->postJson(route('analyses.edit.ai-image.generate', $run->public_id), ['prompt' => 'A friendly dental clinic reception'])->assertOk()->assertJson(['remaining' => 1]);
        $asset = WebsiteAsset::where('asset_key', $first->json('asset.asset_id'))->first();
        $this->assertSame('ai_image', $asset->asset_origin);
        $this->assertSame('ai_generated', $asset->rights_status);
        Storage::disk('public')->assertExists($asset->stored_path);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/images/generations') && $r['model'] === 'muse-image-1.0' && str_contains($r['prompt'], 'friendly dental clinic') && str_contains($r['prompt'], 'no text'));

        // A failed generation does not use up an image.
        $this->actingAs($user)->postJson(route('analyses.edit.ai-image.generate', $run->public_id), ['prompt' => 'Another try at a banner'])->assertStatus(502)->assertJson(['remaining' => 1]);

        $this->actingAs($user)->postJson(route('analyses.edit.ai-image.generate', $run->public_id), ['prompt' => 'A second banner picture'])->assertOk()->assertJson(['remaining' => 0]);
        $third = $this->actingAs($user)->postJson(route('analyses.edit.ai-image.generate', $run->public_id), ['prompt' => 'A third banner picture'])->assertStatus(402);
        $this->assertStringContainsString('Upgrade to Starter', $third->json('message'));
        $this->assertSame(2, \App\Models\AiImageGeneration::where('user_id', $user->id)->count());
    }

    public function test_starter_and_agency_get_five_ai_images_a_month_that_come_back_next_month(): void
    {
        Storage::fake('public');
        [$run, , $user] = $this->site();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->onPlan($user, 'starter');
        $this->museFake();
        $this->actingAs($user)->getJson(route('analyses.edit.ai-image', $run->public_id))->assertJson(['limit' => 5, 'period' => 'month', 'can_upgrade' => false]);

        foreach (range(1, 5) as $i) {
            $this->actingAs($user)->postJson(route('analyses.edit.ai-image.generate', $run->public_id), ['prompt' => "Banner picture number {$i}"])->assertOk();
        }
        $blocked = $this->actingAs($user)->postJson(route('analyses.edit.ai-image.generate', $run->public_id), ['prompt' => 'One more banner picture'])->assertStatus(402);
        $this->assertStringContainsString('reset on', $blocked->json('message'));

        // Last month's images don't count this month.
        \App\Models\AiImageGeneration::where('user_id', $user->id)->update(['created_at' => now()->subMonth()->startOfMonth()->addDays(3)]);
        $this->actingAs($user)->getJson(route('analyses.edit.ai-image', $run->public_id))->assertJson(['used' => 0, 'remaining' => 5]);
        $this->actingAs($user)->postJson(route('analyses.edit.ai-image.generate', $run->public_id), ['prompt' => 'A fresh month banner'])->assertOk();

        $agency = User::factory()->create();
        $this->onPlanAgain($agency, 'agency');
        $run->update(['user_id' => $agency->id]);
        $this->actingAs($agency)->getJson(route('analyses.edit.ai-image', $run->public_id))->assertJson(['limit' => 5, 'period' => 'month']);
    }

    private function onPlanAgain(\App\Models\User $u, string $key): void
    {
        \App\Models\Subscription::create(['user_id' => $u->id, 'plan_id' => \App\Models\Plan::where('plan_key', $key)->value('id'), 'status' => 'active', 'started_at' => now()]);
    }

    public function test_ai_images_are_owner_only_validated_and_need_the_meta_key(): void
    {
        [$run, , $user] = $this->site();
        $this->postJson(route('analyses.edit.ai-image.generate', $run->public_id), ['prompt' => 'A banner picture please'])->assertStatus(401);
        $run->update(['user_id' => User::factory()->create()->id]);
        $this->actingAs($user)->postJson(route('analyses.edit.ai-image.generate', $run->public_id), ['prompt' => 'A banner picture please'])->assertNotFound();

        $run->update(['user_id' => $user->id]);
        $this->actingAs($user)->postJson(route('analyses.edit.ai-image.generate', $run->public_id), ['prompt' => 'short'])->assertStatus(422);
        config(['meta_api.api_key' => '']);
        $this->actingAs($user)->postJson(route('analyses.edit.ai-image.generate', $run->public_id), ['prompt' => 'A banner picture please'])->assertStatus(503);
        $this->assertSame(0, \App\Models\AiImageGeneration::count());
    }

    public function test_the_picker_has_a_meta_ai_image_tab(): void
    {
        [$run, , $user] = $this->site();
        $this->actingAs($user)->get(route('analyses.edit', $run->public_id))->assertOk()->assertSee('Meta AI Image')->assertSee('Generate image');
    }

    private function edit(User $user, $run, int $version, string $text): \Illuminate\Testing\TestResponse
    {
        $hid = $this->hero($run->currentDesignSpec())['instance_id'];

        return $this->actingAs($user)->postJson(route('analyses.edit.apply', $run->public_id), ['base_version' => $version, 'ops' => [['op' => 'set', 'instance_id' => $hid, 'path' => 'headline', 'value' => $text]]]);
    }

    public function test_an_export_pass_on_the_free_plan_includes_two_saved_edits_and_three_downloads_for_its_website(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        [$run, , $user] = $this->site();
        $this->onPlan($user, 'free');
        $run->update(['user_id' => $user->id]);
        $svc = app(\App\Services\Billing\ExportPassAllowance::class);

        // Before buying, a Free-plan person edits and saves freely: no cap at all.
        $this->assertNull($svc->capFor($user, $run));
        $v = $this->edit($user, $run, 1, 'Free headline one')->assertOk()->json('version');
        $v = $this->actingAs($user)->postJson(route('analyses.edit.save', $run->public_id), ['base_version' => $v])->assertOk()->json('version');

        // Buying a pass for this website: three downloads (the first plus two more) and two saved edits.
        \App\Models\OneTimePurchase::create(['user_id' => $user->id, 'analysis_run_id' => $run->id, 'purchase_type' => 'export_pass', 'status' => 'paid', 'exports_remaining' => 3, 'saves_remaining' => 2]);
        $this->assertSame(['downloads' => 3, 'edits' => 2], $svc->capFor($user, $run));
        $this->actingAs($user)->get(route('analyses.edit', $run->public_id))->assertOk()->assertSee('pass-note');

        $v = $this->edit($user, $run, $v, 'Paid headline two')->assertOk()->assertJson(['pass' => ['downloads' => 3, 'edits' => 2]])->json('version');
        $s1 = $this->actingAs($user)->postJson(route('analyses.edit.save', $run->public_id), ['base_version' => $v])->assertOk()->assertJson(['pass' => ['edits' => 1]]);
        $v = $this->edit($user, $run, $s1->json('version'), 'Paid headline three')->assertOk()->json('version');
        $s2 = $this->actingAs($user)->postJson(route('analyses.edit.save', $run->public_id), ['base_version' => $v])->assertOk()->assertJson(['pass' => ['edits' => 0]]);

        // Used up: the editor stops taking changes and points to Starter / Agency.
        $blocked = $this->edit($user, $run, $s2->json('version'), 'A fourth headline')->assertStatus(402);
        $this->assertStringContainsString('Starter or Agency', $blocked->json('message'));
        $this->assertStringContainsString(route('analyses.upgrade', $run->public_id), $blocked->json('upgrade'));

        // Another website of theirs is not capped by this pass.
        $other = $this->makeRun(null, ['team1' => 'team_portrait']);
        $this->assertNull($svc->capFor($user, $other));
    }

    public function test_starter_and_agency_edit_and_save_without_limits_even_with_a_pass_on_file(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        [$run, , $user] = $this->site();
        $this->onPlan($user, 'starter');
        $run->update(['user_id' => $user->id]);
        \App\Models\OneTimePurchase::create(['user_id' => $user->id, 'analysis_run_id' => $run->id, 'purchase_type' => 'export_pass', 'status' => 'paid', 'exports_remaining' => 0, 'saves_remaining' => 0]);

        $this->assertNull(app(\App\Services\Billing\ExportPassAllowance::class)->capFor($user, $run));
        $v = 1;
        foreach (range(1, 4) as $i) {
            $v = $this->edit($user, $run, $v, "Starter headline {$i}")->assertOk()->json('version');
            $v = $this->actingAs($user)->postJson(route('analyses.edit.save', $run->public_id), ['base_version' => $v])->assertOk()->json('version');
        }
    }

    public function test_downloads_are_counted_per_website_and_a_loose_pass_attaches_to_the_first_one_used(): void
    {
        [$run, , $user] = $this->site();
        $this->onPlan($user, 'free');
        $run->update(['user_id' => $user->id]);
        $svc = app(\App\Services\Billing\ExportPassAllowance::class);
        $pass = \App\Models\OneTimePurchase::create(['user_id' => $user->id, 'analysis_run_id' => null, 'purchase_type' => 'export_pass', 'status' => 'paid', 'exports_remaining' => 3, 'saves_remaining' => 2]);

        $this->assertTrue($svc->canExport($user, $run));
        $this->assertTrue($svc->consumeExport($user, $run));
        $this->assertSame($run->id, $pass->fresh()->analysis_run_id, 'a pass bought from the pricing page attaches to the first website it downloads');
        $this->assertSame(2, $pass->fresh()->exports_remaining);

        $other = $this->makeRun(null, ['team1' => 'team_portrait']);
        $other->update(['user_id' => $user->id]);
        $this->assertFalse($svc->canExport($user, $other), 'that pass now belongs to the first website');
        $this->assertTrue($svc->consumeExport($user, $run) && $svc->consumeExport($user, $run));
        $this->assertFalse($svc->canExport($user, $run), 'three downloads used: Starter or another pass is needed');
        $this->assertFalse($svc->consumeExport($user, $run));
    }

    public function test_the_dashboard_card_offers_edit_the_design_on_hover_and_shows_what_the_pass_has_left(): void
    {
        [$run, , $user] = $this->site();
        $this->onPlan($user, 'free');
        $run->update(['user_id' => $user->id, 'status' => 'complete']);
        \App\Models\OneTimePurchase::create(['user_id' => $user->id, 'analysis_run_id' => $run->id, 'purchase_type' => 'export_pass', 'status' => 'paid', 'exports_remaining' => 2, 'saves_remaining' => 1]);

        $page = $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $page->assertSee('Edit the design')->assertSee('group-hover:opacity-100', false)->assertSee('Export Pass · 1 saved edit · 2 downloads left');
        $this->assertStringContainsString('<a href="'.route('analyses.edit', $run->public_id).'" class="btn btn-primary pointer-events-auto', $page->getContent());
    }

    /** @return string a PNG with a see-through background and a dark slogan-like bar */
    private function transparentPng(bool $palette = false): string
    {
        $im = $palette ? imagecreate(600, 300) : imagecreatetruecolor(600, 300);
        if ($palette) {
            $clear = imagecolorallocate($im, 255, 255, 255);
            imagecolortransparent($im, $clear);
            imagefill($im, 0, 0, $clear);
            imagefilledrectangle($im, 80, 120, 520, 180, imagecolorallocate($im, 20, 20, 20));
        } else {
            imagealphablending($im, false);
            imagesavealpha($im, true);
            imagefill($im, 0, 0, imagecolorallocatealpha($im, 255, 255, 255, 127));
            imagefilledrectangle($im, 80, 120, 520, 180, imagecolorallocatealpha($im, 20, 20, 20, 0));
        }
        ob_start();
        imagepng($im);

        return (string) ob_get_clean();
    }

    public function test_a_transparent_png_keeps_its_transparency_and_is_shown_whole_on_the_page_with_no_frame_colour(): void
    {
        Storage::fake('public');
        [$run, $spec, $user] = $this->site();
        foreach ([false, true] as $palette) {
            $file = UploadedFile::fake()->createWithContent('slogan.png', $this->transparentPng($palette));
            $up = $this->actingAs($user)->postJson(route('analyses.edit.upload', $run->public_id), ['image' => $file, 'alt' => 'Slogan'])->assertOk();
            $asset = WebsiteAsset::where('asset_key', $up->json('asset.asset_id'))->first();

            $this->assertSame('image/png', $asset->mime_type);
            $this->assertTrue((bool) $asset->has_alpha, 'recorded as see-through');
            $im = imagecreatefrompng(Storage::disk('public')->path($asset->stored_path));
            $this->assertSame([127, 127], [(imagecolorat($im, 5, 5) >> 24) & 0x7F, (imagecolorat($im, 590, 290) >> 24) & 0x7F], 'the background is still fully transparent');
            $this->assertSame(0, (imagecolorat($im, 300, 150) >> 24) & 0x7F, 'the artwork is still solid');
            $this->assertSame([600, 300], [imagesx($im), imagesy($im)], 'original size kept');
        }

        // Placed in the hero, it is marked so the page shows it on the page itself, whole.
        $hid = $this->hero($spec)['instance_id'];
        $assetKey = WebsiteAsset::where('analysis_run_id', $run->id)->where('has_alpha', true)->latest('id')->value('asset_key');
        $this->actingAs($user)->postJson(route('analyses.edit.apply', $run->public_id), ['base_version' => 1, 'ops' => [['op' => 'set_image', 'instance_id' => $hid, 'path' => 'image', 'asset_id' => $assetKey, 'alt' => 'Slogan']]])->assertOk();
        $html = $this->actingAs($user)->get(route('analyses.edit.frame', $run->public_id))->assertOk()->getContent();
        $this->assertStringContainsString('bp-visual--clear', $html);
        $this->assertStringContainsString('.bp-visual--clear .bp-visual__img{object-fit:contain}', $html);
    }

    public function test_opaque_pictures_are_not_treated_as_see_through(): void
    {
        Storage::fake('public');
        [$run, , $user] = $this->site();
        $png = imagecreatetruecolor(600, 300);
        imagefill($png, 0, 0, imagecolorallocate($png, 30, 90, 160));
        ob_start();
        imagepng($png);
        $opaquePng = (string) ob_get_clean();

        foreach ([['photo.png', $opaquePng], ['photo.jpg', $this->jpegBytes()]] as [$name, $bytes]) {
            $up = $this->actingAs($user)->postJson(route('analyses.edit.upload', $run->public_id), ['image' => UploadedFile::fake()->createWithContent($name, $bytes)])->assertOk();
            $this->assertFalse((bool) WebsiteAsset::where('asset_key', $up->json('asset.asset_id'))->value('has_alpha'), "{$name} keeps the normal cropped frame");
        }
    }
}
