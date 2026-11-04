<?php

namespace App\Services\VisualQa;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Remote provider: Browserless REST `/function` (Puppeteer code run in a hosted
 * browser). Chosen over `/screenshot` because one call per viewport can set the
 * viewport, load the page, run the shared blueprint inspection and return both
 * the screenshot and the metrics, using only documented behaviour
 * (POST /function?token=…, JSON {code, context}, function returns {data, type}).
 *
 * NOT verified against a live Browserless account: it is contract-tested with
 * faked HTTP only. The page is sent as self-contained HTML (local images are
 * inlined as data: URIs), so nothing on the host needs to be reachable.
 * Provider tokens stay server-side; plan, region and data-processing terms must
 * be confirmed before production use.
 */
class BrowserlessBrowser implements BrowserService
{
    use InlinesLocalAssets;

    private const VIEWPORTS = [['desktop', 1440, 1000], ['tablet', 768, 1024], ['mobile', 390, 844]];

    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $token,
        private readonly int $timeoutSeconds = 60,
    ) {}

    public function id(): string
    {
        return 'browserless';
    }

    public function isAvailable(): bool
    {
        return $this->token !== null && $this->token !== '' && $this->baseUrl !== '';
    }

    public function capturesExternalUrls(): bool
    {
        return true;
    }

    public function capture(string $htmlPath, string $outputDir, ?array $expectations = null): array
    {
        if (! $this->isAvailable()) {
            throw new RuntimeException('Browserless is not configured (BROWSERLESS_TOKEN / BROWSERLESS_URL).');
        }

        $html = $this->inlineLocalAssets($htmlPath);
        File::ensureDirectoryExists($outputDir);
        $code = $this->functionCode();

        $results = [];
        foreach (self::VIEWPORTS as [$name, $width, $height]) {
            $response = Http::timeout($this->timeoutSeconds)
                ->withQueryParameters(['token' => $this->token])
                ->acceptJson()
                ->post(rtrim($this->baseUrl, '/').'/function', [
                    'code' => $code,
                    'context' => ['html' => $html, 'viewport' => ['name' => $name, 'width' => $width, 'height' => $height], 'expectations' => $expectations],
                ]);

            if (! $response->successful()) {
                throw new RuntimeException("Browserless request failed with HTTP {$response->status()}.");
            }

            $data = $response->json();
            $data = is_array($data['data'] ?? null) ? $data['data'] : $data; // tolerate either envelope
            if (! is_array($data) || ! isset($data['viewport'])) {
                throw new RuntimeException('Browserless returned an unexpected response.');
            }

            $shot = null;
            if (! empty($data['screenshot_base64']) && ($bytes = base64_decode($data['screenshot_base64'], true)) !== false) {
                $shot = "{$outputDir}/{$name}.png";
                File::put($shot, $bytes);
            }
            unset($data['screenshot_base64']);

            $results[] = array_merge([
                'viewport' => $name, 'width' => $width, 'height' => $height, 'load_error' => null, 'console_errors' => [], 'page_errors' => [],
                'failed_requests' => [], 'has_horizontal_overflow' => false, 'image_count' => 0, 'broken_image_count' => 0, 'broken_image_srcs' => [],
                'blueprint' => null, 'reduced_motion_ok' => null,
            ], $data, ['screenshot_path' => $shot]);
        }

        return $results;
    }


    /** Puppeteer function: same measurements as scripts/visual-qa/capture.cjs, shared blueprint inspection included. */
    private function functionCode(): string
    {
        $inspect = preg_replace('/^if \(typeof module.*$/m', '', File::get(base_path('scripts/visual-qa/inspect-blueprint.cjs')));

        return <<<JS
{$inspect}
export default async function ({ page, context }) {
  const { html, viewport, expectations } = context;
  const consoleErrors = [], pageErrors = [], failedRequests = [];
  page.on('console', (m) => m.type() === 'error' && consoleErrors.push(m.text().slice(0, 300)));
  page.on('pageerror', (e) => pageErrors.push(String(e.message || e).slice(0, 300)));
  page.on('requestfailed', (r) => failedRequests.push(r.url().slice(0, 200)));
  await page.setViewport({ width: viewport.width, height: viewport.height });
  let loadError = null;
  try { await page.setContent(html, { waitUntil: 'load', timeout: 30000 }); await page.addStyleTag({ content: 'html{scrollbar-width:none}::-webkit-scrollbar{display:none}' }); await page.evaluate(() => document.fonts && document.fonts.ready); await new Promise((r) => setTimeout(r, 500)); } catch (e) { loadError = String(e.message || e).slice(0, 300); }
  const out = { viewport: viewport.name, load_error: loadError, console_errors: consoleErrors.slice(0, 10), page_errors: pageErrors.slice(0, 10), failed_requests: failedRequests.slice(0, 10) };
  if (!loadError) {
    out.screenshot_base64 = await page.screenshot({ fullPage: true, encoding: 'base64' });
    Object.assign(out, await page.evaluate(() => {
      const imgs = Array.from(document.images), broken = imgs.filter((i) => i.complete && i.naturalWidth === 0);
      const d = document.documentElement;
      return { has_horizontal_overflow: d.scrollWidth > d.clientWidth + 2, image_count: imgs.length, broken_image_count: broken.length, broken_image_srcs: broken.map((i) => (i.getAttribute('src') || '').slice(0, 120)).slice(0, 20) };
    }));
    if (expectations) {
      out.blueprint = await page.evaluate(inspectBlueprint, { exp: expectations, width: viewport.width });
      if (viewport.name === 'desktop') {
        await page.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: 'reduce' }]);
        out.reduced_motion_ok = await page.evaluate(() => Array.from(document.querySelectorAll('[class^="bp-"]')).slice(0, 40).every((el) => { const s = getComputedStyle(el); const z = (v) => v.split(',').every((p) => parseFloat(p) === 0); return z(s.transitionDuration || '0s') && z(s.animationDuration || '0s'); }));
      }
    }
  }
  return { data: out, type: 'application/json' };
}
JS;
    }
}
