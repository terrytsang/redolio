<?php

namespace App\Services\Widgets;

use App\Models\AnalysisRun;
use App\Services\Blueprint\SectionRegistry;
use App\Services\Editing\SectionOverrides;
use App\Services\Editing\SpecEditException;
use Illuminate\Support\Str;

/**
 * Add-on widgets a person can drop into a generated page from the editor. A widget is a typed config (never markup):
 * every field is cleaned against the schema below, links go through the safe-link rule, and the only free-form code
 * (Embed) is rendered inside a sandboxed iframe on its own file, never into the page itself.
 *
 * Stored at `v15.pages[0].widgets[] = {id, type, after, config}` where `after` is the section instance a widget follows
 * ('top' = above the first section). Global widgets (analytics, floating chat) ignore `after`.
 */
class WidgetCatalog
{
    public const MAX_WIDGETS = 14;

    public const HEROES = ['split' => 'Content left, picture right', 'image-left' => 'Picture left, content right', 'banner-left' => 'Full-width banner, content on the left', 'banner-right' => 'Full-width banner, content on the right', 'fullscreen-left' => 'Full screen, picture on the left', 'fullscreen-right' => 'Full screen, picture on the right', 'bold-left' => 'Big type, buttons bottom-left', 'bold-right' => 'Big type, buttons bottom-right', 'centered' => 'Centered — big headline', 'aerial-fullscreen' => 'Full-screen picture', 'editorial-asymmetric' => 'Editorial — asymmetric', 'product-showcase' => 'Product showcase', 'typography-led' => 'Bold typography'];

    public function __construct(private readonly SectionRegistry $registry, private readonly SectionOverrides $overrides) {}

    // ---------------------------------------------------------------- definitions

    /** @return array<string, array<string, mixed>> */
    public function types(): array
    {
        $cta = fn (string $k, string $label) => [['key' => $k.'_label', 'label' => $label.' text', 'type' => 'text', 'max' => 40], ['key' => $k.'_href', 'label' => $label.' link', 'type' => 'url', 'help' => 'https://…, mailto:…, tel:… or #section. The button appears once both its text and its link are filled in.']];
        $platformItem = [
            ['key' => 'platform', 'label' => 'Platform', 'type' => 'select', 'options' => SocialLinks::options(), 'default' => 'whatsapp'],
            ['key' => 'value', 'label' => 'Link, @handle, number or email', 'type' => 'text', 'max' => 300, 'help' => 'WhatsApp / phone: the number with country code. Email: the address. Others: the page link or @handle.'],
        ];

        return [
            'hero' => [
                'label' => 'Hero section', 'group' => 'Sections', 'icon' => '🌅', 'place' => 'inline', 'blurb' => 'A big opening block — pick one of 6 designs.',
                'fields' => array_merge([
                    ['key' => 'variant', 'label' => 'Design', 'type' => 'select', 'options' => self::HEROES, 'default' => 'split', 'visual' => 'hero-wireframes'],
                    ['key' => 'eyebrow', 'label' => 'Small label', 'type' => 'text', 'max' => 80],
                    ['key' => 'headline', 'label' => 'Headline', 'type' => 'text', 'max' => 140, 'default' => 'Say what you do in one clear line'],
                    ['key' => 'subheadline', 'label' => 'Description', 'type' => 'textarea', 'max' => 400, 'default' => 'One or two sentences that tell a visitor why to choose you.'],
                ], $cta('primary', 'Main button'), $cta('secondary', 'Second button'), [['key' => 'image', 'label' => 'Picture', 'type' => 'image']]),
                'defaults' => ['variant' => 'split', 'headline' => 'Say what you do in one clear line', 'subheadline' => 'One or two sentences that tell a visitor why to choose you.', 'primary_label' => 'Get started', 'primary_href' => '#contact'],
            ],
            'form' => [
                'label' => 'Form', 'group' => 'Engage', 'icon' => '📝', 'place' => 'inline', 'blurb' => 'Contact or enquiry form — choose its fields and where it sends.',
                'fields' => [
                    ['key' => 'heading', 'label' => 'Heading', 'type' => 'text', 'max' => 80, 'default' => 'Get in touch'],
                    ['key' => 'intro', 'label' => 'Intro', 'type' => 'textarea', 'max' => 300],
                    ['key' => 'fields', 'label' => 'Fields', 'type' => 'list', 'max' => 8, 'min' => 1, 'itemLabel' => 'label', 'items' => [
                        ['key' => 'label', 'label' => 'Label', 'type' => 'text', 'max' => 40],
                        ['key' => 'type', 'label' => 'Type', 'type' => 'select', 'options' => ['text' => 'Short text', 'email' => 'Email', 'tel' => 'Phone', 'textarea' => 'Long message'], 'default' => 'text'],
                        ['key' => 'required', 'label' => 'Required', 'type' => 'bool'],
                    ]],
                    ['key' => 'submit_label', 'label' => 'Button text', 'type' => 'text', 'max' => 40, 'default' => 'Send message'],
                    ['key' => 'destination', 'label' => 'Send submissions to', 'type' => 'select', 'options' => ['email' => 'My email (opens the visitor’s mail app)', 'endpoint' => 'A form service / webhook (https URL)'], 'default' => 'email'],
                    ['key' => 'email', 'label' => 'Your email address', 'type' => 'text', 'max' => 120, 'showWhen' => ['destination', 'email']],
                    ['key' => 'endpoint', 'label' => 'Form endpoint URL', 'type' => 'url', 'help' => 'e.g. a Formspree, Basin, Getform or Zapier address. Must be https.', 'showWhen' => ['destination', 'endpoint']],
                    ['key' => 'note', 'label' => 'Small print under the button', 'type' => 'text', 'max' => 160],
                    ['key' => 'style', 'label' => 'Look', 'type' => 'select', 'options' => ['card' => 'Card', 'plain' => 'Plain'], 'default' => 'card'],
                ],
                'defaults' => ['heading' => 'Get in touch', 'intro' => 'Tell us a little about what you need and we’ll reply soon.', 'fields' => [['label' => 'Your name', 'type' => 'text', 'required' => true], ['label' => 'Email', 'type' => 'email', 'required' => true], ['label' => 'Message', 'type' => 'textarea', 'required' => false]], 'submit_label' => 'Send message', 'destination' => 'email', 'style' => 'card'],
            ],
            'social' => [
                'label' => 'Social links', 'group' => 'Connect', 'icon' => '🔗', 'place' => 'inline', 'blurb' => 'WhatsApp, Facebook, Instagram, TikTok, Shopee, Lazada, Shopify, email and more.',
                'fields' => [
                    ['key' => 'heading', 'label' => 'Heading (optional)', 'type' => 'text', 'max' => 80],
                    ['key' => 'items', 'label' => 'Links', 'type' => 'list', 'max' => 12, 'min' => 1, 'itemLabel' => 'platform', 'items' => $platformItem],
                    ['key' => 'whatsapp_message', 'label' => 'WhatsApp pre-filled message', 'type' => 'text', 'max' => 200, 'help' => 'What the chat starts with, e.g. “Hi! I’d like to know more.”'],
                    ['key' => 'style', 'label' => 'Look', 'type' => 'select', 'options' => ['round' => 'Round brand icons', 'buttons' => 'Buttons with names', 'minimal' => 'Minimal icons'], 'default' => 'round'],
                    ['key' => 'align', 'label' => 'Align', 'type' => 'select', 'options' => ['center' => 'Center', 'left' => 'Left'], 'default' => 'center'],
                ],
                'defaults' => ['heading' => 'Find us online', 'items' => [['platform' => 'whatsapp', 'value' => ''], ['platform' => 'facebook', 'value' => ''], ['platform' => 'instagram', 'value' => '']], 'style' => 'round', 'align' => 'center', 'whatsapp_message' => 'Hi! I’d like to know more.'],
            ],
            'testimonials' => [
                'label' => 'Testimonials carousel', 'group' => 'Engage', 'icon' => '💬', 'place' => 'inline', 'blurb' => 'Slide through customer quotes, with arrows and optional autoplay.',
                'fields' => [
                    ['key' => 'heading', 'label' => 'Heading', 'type' => 'text', 'max' => 80, 'default' => 'What customers say'],
                    ['key' => 'items', 'label' => 'Testimonials', 'type' => 'list', 'max' => 10, 'min' => 1, 'itemLabel' => 'name', 'items' => [
                        ['key' => 'quote', 'label' => 'Quote', 'type' => 'textarea', 'max' => 400],
                        ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'max' => 60],
                        ['key' => 'role', 'label' => 'Role / company', 'type' => 'text', 'max' => 80],
                    ]],
                    ['key' => 'autoplay', 'label' => 'Auto-advance', 'type' => 'bool'],
                    ['key' => 'seconds', 'label' => 'Seconds per slide', 'type' => 'number', 'min' => 3, 'max' => 20, 'default' => 6, 'showWhen' => ['autoplay', true]],
                ],
                'defaults' => ['heading' => 'What customers say', 'items' => [['quote' => 'Replace this with a real customer quote — specific results sound most believable.', 'name' => 'Customer name', 'role' => 'Role or company'], ['quote' => 'Add a second quote so visitors can slide through them.', 'name' => 'Another customer', 'role' => '']], 'autoplay' => false, 'seconds' => 6],
            ],
            'cta-banner' => [
                'label' => 'Call-to-action banner', 'group' => 'Engage', 'icon' => '📣', 'place' => 'inline', 'blurb' => 'A bold banner that pushes one action.',
                'fields' => array_merge([['key' => 'heading', 'label' => 'Heading', 'type' => 'text', 'max' => 100, 'default' => 'Ready to get started?'], ['key' => 'text', 'label' => 'Text', 'type' => 'textarea', 'max' => 240]], $cta('button', 'Button')),
                'defaults' => ['heading' => 'Ready to get started?', 'text' => 'Talk to us today — we’ll help you find the right fit.', 'button_label' => 'Contact us', 'button_href' => '#contact'],
            ],
            'floating-chat' => [
                'label' => 'Floating chat icons', 'group' => 'Connect', 'icon' => '💭', 'place' => 'global', 'single' => true, 'blurb' => 'A chat button that floats at the bottom-right and opens WhatsApp, Messenger, phone…',
                'fields' => [
                    ['key' => 'items', 'label' => 'Channels', 'type' => 'list', 'max' => 5, 'min' => 1, 'itemLabel' => 'platform', 'items' => $platformItem],
                    ['key' => 'whatsapp_message', 'label' => 'WhatsApp pre-filled message', 'type' => 'text', 'max' => 200],
                    ['key' => 'label', 'label' => 'Greeting', 'type' => 'text', 'max' => 60, 'default' => 'Chat with us'],
                    ['key' => 'position', 'label' => 'Position', 'type' => 'select', 'options' => ['right' => 'Bottom right', 'left' => 'Bottom left'], 'default' => 'right'],
                ],
                'defaults' => ['items' => [['platform' => 'whatsapp', 'value' => '']], 'label' => 'Chat with us', 'position' => 'right', 'whatsapp_message' => 'Hi! I’d like to know more.'],
            ],
            'announcement' => [
                'label' => 'Announcement bar', 'group' => 'Engage', 'icon' => '📢', 'place' => 'top', 'single' => true, 'blurb' => 'A slim bar above the page for an offer or notice.',
                'fields' => array_merge([['key' => 'text', 'label' => 'Message', 'type' => 'text', 'max' => 140, 'default' => 'Free consultation this month']], $cta('link', 'Link')),
                'defaults' => ['text' => 'Free consultation this month', 'link_label' => 'Book now', 'link_href' => '#contact'],
            ],
            'video' => [
                'label' => 'Video', 'group' => 'Embed & tracking', 'icon' => '▶️', 'place' => 'inline', 'blurb' => 'A YouTube video, privacy-friendly.',
                'fields' => [['key' => 'heading', 'label' => 'Heading (optional)', 'type' => 'text', 'max' => 80], ['key' => 'url', 'label' => 'YouTube link', 'type' => 'url', 'help' => 'Paste the video link, e.g. https://www.youtube.com/watch?v=…']],
                'defaults' => ['heading' => '', 'url' => ''],
            ],
            'map' => [
                'label' => 'Google Map', 'group' => 'Embed & tracking', 'icon' => '📍', 'place' => 'inline', 'blurb' => 'Show where you are, from an address.',
                'fields' => [['key' => 'heading', 'label' => 'Heading (optional)', 'type' => 'text', 'max' => 80, 'default' => 'Find us'], ['key' => 'address', 'label' => 'Address or place', 'type' => 'text', 'max' => 200], ['key' => 'height', 'label' => 'Height (px)', 'type' => 'number', 'min' => 200, 'max' => 800, 'default' => 380]],
                'defaults' => ['heading' => 'Find us', 'address' => '', 'height' => 380],
            ],
            'embed' => [
                'label' => 'Embed anything (HTML)', 'group' => 'Embed & tracking', 'icon' => '🧩', 'place' => 'inline', 'blurb' => 'Paste any code — a booking widget, calendar, reviews, a script.',
                'fields' => [
                    ['key' => 'title', 'label' => 'Name (for screen readers)', 'type' => 'text', 'max' => 80, 'default' => 'Embedded content'],
                    ['key' => 'code', 'label' => 'HTML / embed code', 'type' => 'code', 'max' => 20000, 'help' => 'Runs in an isolated frame on your published site, so it can’t read or change the rest of the page.'],
                    ['key' => 'height', 'label' => 'Height (px)', 'type' => 'number', 'min' => 80, 'max' => 1600, 'default' => 400],
                ],
                'defaults' => ['title' => 'Embedded content', 'code' => '', 'height' => 400],
            ],
            'analytics' => [
                'label' => 'Google Analytics', 'group' => 'Embed & tracking', 'icon' => '📈', 'place' => 'head', 'single' => true, 'blurb' => 'Track visits with your GA4 or Tag Manager ID (live on the published site).',
                'fields' => [['key' => 'measurement_id', 'label' => 'Measurement ID', 'type' => 'text', 'max' => 20, 'help' => 'GA4 looks like G-XXXXXXXXXX; Tag Manager looks like GTM-XXXXXXX.']],
                'defaults' => ['measurement_id' => ''],
            ],
        ];
    }

    public function has(string $type): bool
    {
        return isset($this->types()[$type]);
    }

    public function newId(): string
    {
        return 'w'.Str::lower(Str::random(7));
    }

    // ---------------------------------------------------------------- cleaning

    /** @return array<string, mixed> */
    public function defaults(string $type): array
    {
        return $this->types()[$type]['defaults'] ?? [];
    }

    /**
     * Clean a (possibly partial) config against the type's schema. $base is the existing config the patch applies to.
     *
     * @return array<string, mixed>
     */
    public function clean(string $type, array $config, AnalysisRun $run, array $base = []): array
    {
        $def = $this->types()[$type] ?? throw new SpecEditException('Unknown add-on.');
        $out = [];
        foreach ($def['fields'] as $f) {
            $k = $f['key'];
            $raw = array_key_exists($k, $config) ? $config[$k] : ($base[$k] ?? ($f['default'] ?? null));
            $out[$k] = $this->field($f, $raw, $run, $def['label']);
        }
        $this->crossCheck($type, $out);

        return $out;
    }

    /** Whole-config validation that needs several fields at once. */
    private function crossCheck(string $type, array &$c): void
    {
        $fail = fn (string $m) => throw new SpecEditException($m);
        if ($type === 'hero') {
            $c['headline'] === '' && $fail('The hero needs a headline.');
            // A button's text and link are saved one field at a time, so a half-filled button is allowed while someone is typing it:
            // it simply stays hidden until both are there (see renderHero).
        }
        if ($type === 'form' && $c['destination'] === 'email' && $c['email'] !== '' && ! filter_var($c['email'], FILTER_VALIDATE_EMAIL)) {
            $fail('That email address doesn’t look right.');
        }
        if ($type === 'form' && $c['destination'] === 'endpoint' && $c['endpoint'] !== '' && ! str_starts_with($c['endpoint'], 'https://')) {
            $fail('The form endpoint must be an https:// address.');
        }
        if ($type === 'announcement' && $c['text'] === '') {
            $fail('The announcement needs a message.');
        }
        if ($type === 'analytics' && $c['measurement_id'] !== '' && ! preg_match('/^(G-[A-Z0-9]{4,14}|GT-[A-Z0-9]{4,14}|GTM-[A-Z0-9]{4,10})$/', $c['measurement_id'])) {
            $fail('That doesn’t look like a Google Analytics (G-…) or Tag Manager (GTM-…) ID.');
        }
        if ($type === 'video' && $c['url'] !== '' && ! $this->youtubeId($c['url'])) {
            $fail('Paste a YouTube video link (youtube.com/watch?v=… or youtu.be/…).');
        }
        foreach (['social', 'floating-chat'] as $t) {
            if ($type === $t) {
                foreach ($c['items'] as $i => $it) {
                    if ($it['value'] !== '' && SocialLinks::href($it['platform'], $it['value'], (string) ($c['whatsapp_message'] ?? '')) === null) {
                        $fail('Check the '.SocialLinks::label($it['platform']).' entry — '.(in_array($it['platform'], ['whatsapp', 'phone'], true) ? 'use the number with country code' : ($it['platform'] === 'email' ? 'use a full email address' : 'use the page link or an @handle')).'.');
                    }
                }
            }
        }
    }

    private function field(array $f, mixed $raw, AnalysisRun $run, string $widgetLabel): mixed
    {
        $label = $f['label'];
        switch ($f['type']) {
            case 'text':
                $v = trim((string) preg_replace('/\s+/u', ' ', strip_tags(is_scalar($raw) ? (string) $raw : '')));
                if (mb_strlen($v) > ($f['max'] ?? 120)) {
                    throw new SpecEditException("“{$label}” is too long (maximum {$f['max']} characters).");
                }

                return $v;
            case 'textarea':
                $v = trim((string) preg_replace("/[ \t]+/", ' ', str_replace(["\r\n", "\r"], "\n", strip_tags(is_scalar($raw) ? (string) $raw : ''))));
                if (mb_strlen($v) > ($f['max'] ?? 500)) {
                    throw new SpecEditException("“{$label}” is too long (maximum {$f['max']} characters).");
                }

                return $v;
            case 'code':
                $v = is_string($raw) ? str_replace("\0", '', $raw) : '';
                if (mb_strlen($v) > ($f['max'] ?? 20000)) {
                    throw new SpecEditException("“{$label}” is too long (maximum {$f['max']} characters).");
                }

                return $v;
            case 'url':
                $v = trim(is_string($raw) ? $raw : '');
                if ($v === '') {
                    return '';
                }
                if (! preg_match('#^(https:|mailto:|tel:|\#|/)#i', $v) && preg_match('#^[\w-]+(\.[\w-]+)+(/\S*)?$#', $v)) {
                    $v = 'https://'.$v;
                }
                if (! $this->overrides->validHref($v)) {
                    throw new SpecEditException("“{$label}” isn’t a link we can use — start it with https://, mailto:, tel: or #.");
                }

                return $v;
            case 'select':
                $v = is_scalar($raw) ? (string) $raw : '';

                return array_key_exists($v, $f['options']) ? $v : ($f['default'] ?? array_key_first($f['options']));
            case 'bool':
                return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
            case 'number':
                return max($f['min'] ?? 0, min($f['max'] ?? 9999, (int) ($raw ?? $f['default'] ?? 0)));
            case 'image':
                $id = is_array($raw) ? (string) ($raw['asset_id'] ?? '') : '';
                if ($id === '') {
                    return null;
                }
                if (! $run->websiteAssets()->where('asset_key', $id)->whereNotNull('stored_path')->exists()) {
                    throw new SpecEditException('That picture isn’t available for this project.');
                }

                return ['asset_id' => $id, 'alt' => mb_substr(trim(strip_tags((string) ($raw['alt'] ?? ''))) ?: 'Image', 0, 200)];
            case 'list':
                $rows = is_array($raw) ? array_values($raw) : [];
                if (count($rows) > ($f['max'] ?? 10)) {
                    throw new SpecEditException("Too many items in “{$label}” (maximum {$f['max']}).");
                }
                $out = [];
                foreach ($rows as $row) {
                    $row = is_array($row) ? $row : [];
                    $item = [];
                    foreach ($f['items'] as $sub) {
                        $item[$sub['key']] = $this->field($sub, $row[$sub['key']] ?? ($sub['default'] ?? null), $run, $widgetLabel);
                    }
                    $out[] = $item;
                }
                if (count($out) < ($f['min'] ?? 0)) {
                    throw new SpecEditException("“{$label}” needs at least {$f['min']}.");
                }

                return $out;
        }

        return null;
    }

    // ---------------------------------------------------------------- rendering

    /**
     * @param  array<int, array{id: string, type: string, after: ?string, config: array}>  $widgets
     * @param  array<int, string>  $sectionIds
     * @param  array<string, string>  $assetUrls
     * @return array{top: string, after: array<string, string>, end: string, head: string, hasScript: bool, embeds: array<string, string>}
     */
    public function renderAll(array $widgets, array $sectionIds, array $assetUrls, bool $publish, array $artStyle = []): array
    {
        $out = ['top' => '', 'after' => [], 'end' => '', 'head' => '', 'hasScript' => false, 'embeds' => []];
        foreach ($widgets as $w) {
            $type = $w['type'] ?? '';
            if (! $this->has($type)) {
                continue;
            }
            $def = $this->types()[$type];
            $cfg = $w['config'] ?? [];
            if ($type === 'analytics') {
                if ($publish && ($cfg['measurement_id'] ?? '') !== '') {
                    $out['head'] .= $this->analyticsHead($cfg['measurement_id']);
                    $out['hasScript'] = true;
                }

                continue;
            }
            $html = $this->renderOne($w['id'], $type, $cfg, $assetUrls, $publish, $out);
            if ($html === '') {
                continue;
            }
            $wrapped = '<div class="bp-widget bp-widget--'.e($type).'" data-widget="'.e($w['id']).'" data-widget-type="'.e($type).'" data-widget-place="'.e($def['place']).'" data-widget-label="'.e($def['label']).'">'.$html.'</div>';
            if ($def['place'] === 'global') {
                $out['end'] .= $wrapped;
            } elseif ($def['place'] === 'top') {
                $out['top'] = $wrapped.$out['top']; // a slim bar above the page always stays the very first thing
            } elseif (($w['after'] ?? 'top') === 'top') {
                $out['top'] .= $wrapped;
            } elseif (in_array($w['after'], $sectionIds, true)) {
                $out['after'][$w['after']] = ($out['after'][$w['after']] ?? '').$wrapped;
            } else {
                $out['end'] = $wrapped.$out['end']; // its section was hidden: keep it, at the end of the page
            }
            $type === 'testimonials' && $out['hasScript'] = true;
        }

        return $out;
    }

    private function renderOne(string $id, string $type, array $c, array $assetUrls, bool $publish, array &$out): string
    {
        switch ($type) {
            case 'hero':
                return $this->renderHero($id, $c, $assetUrls);
            case 'embed':
                if (trim((string) ($c['code'] ?? '')) === '') {
                    return $publish ? '' : view('blueprints.widgets.placeholder', ['label' => 'Embedded code', 'note' => 'Paste code in the add-on’s settings.'])->render();
                }
                $publish && $out['embeds'][$id] = $this->embedDocument($c);

                return view('blueprints.widgets.embed', ['c' => $c, 'id' => $id, 'publish' => $publish])->render();
            case 'video':
                $yt = $this->youtubeId((string) ($c['url'] ?? ''));
                if (! $yt) {
                    return $publish ? '' : view('blueprints.widgets.placeholder', ['label' => 'Video', 'note' => 'Paste a YouTube link in the add-on’s settings.'])->render();
                }

                return view('blueprints.widgets.video', ['c' => $c, 'yt' => $yt, 'publish' => $publish])->render();
            case 'map':
                if (trim((string) ($c['address'] ?? '')) === '') {
                    return $publish ? '' : view('blueprints.widgets.placeholder', ['label' => 'Map', 'note' => 'Add an address in the add-on’s settings.'])->render();
                }

                return view('blueprints.widgets.map', ['c' => $c, 'publish' => $publish])->render();
            case 'social':
            case 'floating-chat':
                $items = [];
                foreach ($c['items'] ?? [] as $it) {
                    $href = SocialLinks::href($it['platform'], (string) $it['value'], (string) ($c['whatsapp_message'] ?? ''));
                    $href && $items[] = $it + ['href' => $href];
                }
                if (! $items) {
                    return $publish ? '' : view('blueprints.widgets.placeholder', ['label' => $type === 'social' ? 'Social links' : 'Floating chat', 'note' => 'Add a link in the add-on’s settings.'])->render();
                }

                return view('blueprints.widgets.'.$type, ['c' => $c, 'items' => $items, 'id' => $id])->render();
            case 'form':
                $ok = (($c['destination'] ?? '') === 'email' && ($c['email'] ?? '') !== '') || (($c['destination'] ?? '') === 'endpoint' && ($c['endpoint'] ?? '') !== '');
                if (! $ok && $publish) {
                    return ''; // a form that goes nowhere is never published
                }

                return view('blueprints.widgets.form', ['c' => $c, 'id' => $id])->render().($ok ? '' : view('blueprints.widgets.placeholder', ['label' => 'Form isn’t connected yet', 'note' => 'Choose where submissions go in the add-on’s settings — until then it isn’t shown on your published site.'])->render());
            default:
                return view('blueprints.widgets.'.$type, ['c' => $c, 'id' => $id])->render();
        }
    }

    private function renderHero(string $id, array $c, array $assetUrls): string
    {
        $variant = isset(self::HEROES[$c['variant'] ?? '']) ? $c['variant'] : 'split';
        $cta = fn (string $k) => ($c[$k.'_label'] ?? '') !== '' && ($c[$k.'_href'] ?? '') !== '' ? ['label' => $c[$k.'_label'], 'href' => $c[$k.'_href']] : null;
        $props = ['eyebrow' => $c['eyebrow'] ?: null, 'headline' => $c['headline'], 'subheadline' => $c['subheadline'] ?: null, 'primary_cta' => $cta('primary'), 'secondary_cta' => $cta('secondary'), 'image' => $c['image'] ?? null];
        $html = view($this->registry->view('hero', $variant), ['c' => array_filter($props, fn ($v) => $v !== null), 'id' => 'widget-'.$id, 'assetUrls' => $assetUrls, 'artStyle' => 'stripes'])->render();
        // A page has one #top and one section marker per real section: a hero add-on is neither.
        $html = preg_replace(['/\sid="top"/', '/\sdata-section="[^"]*"/'], '', $html) ?? $html;

        return preg_replace('/<h1(\s|>)/', '<h2 class="bp-widget-h1"$1', str_replace('</h1>', '</h2>', $html), 1) ?? $html; // one <h1> per page
    }

    public function youtubeId(string $url): ?string
    {
        if (preg_match('#^https://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:[^\#]*&)?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})#', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    private function analyticsHead(string $id): string
    {
        $safe = e($id);
        $src = str_starts_with($id, 'GTM-') ? 'https://www.googletagmanager.com/gtm.js?id='.rawurlencode($id) : 'https://www.googletagmanager.com/gtag/js?id='.rawurlencode($id);

        return '<meta name="redo-analytics" content="'.$safe.'"><script async src="'.e($src).'"></script>';
    }

    /** The isolated document an Embed widget's code lives in (served with a sandbox CSP; see SitePublicController). */
    private function embedDocument(array $c): string
    {
        return "<!doctype html>\n<html><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width, initial-scale=1\"><base target=\"_blank\"><style>html,body{margin:0;padding:0}</style></head><body>\n".$c['code']."\n</body></html>\n";
    }

    /** Short human summary for the editor's list. */
    public function summary(string $type, array $c): string
    {
        return match ($type) {
            'hero' => ($c['headline'] ?? '').' · '.(self::HEROES[$c['variant'] ?? 'split'] ?? ''),
            'form' => ($c['heading'] ?? '').' · '.count($c['fields'] ?? []).' fields',
            'social', 'floating-chat' => collect($c['items'] ?? [])->map(fn ($i) => SocialLinks::label($i['platform']))->unique()->implode(', '),
            'testimonials' => count($c['items'] ?? []).' testimonials',
            'cta-banner' => $c['heading'] ?? '',
            'announcement' => $c['text'] ?? '',
            'analytics' => ($c['measurement_id'] ?? '') ?: 'Add your ID',
            'map' => ($c['address'] ?? '') ?: 'Add an address',
            'video' => ($c['url'] ?? '') ?: 'Add a link',
            'embed' => trim((string) ($c['code'] ?? '')) === '' ? 'Paste your code' : Str::limit(trim(strip_tags((string) $c['code'])) ?: 'HTML', 50),
            default => '',
        };
    }
}
