<?php

namespace App\Services\Widgets;

/** Social / messaging / marketplace platforms: how to turn what a person types into a safe link, and how each looks. */
class SocialLinks
{
    /** key => [label, brand colour, "kind" of value the person types] */
    public const PLATFORMS = [
        'whatsapp' => ['WhatsApp (click to chat)', '#25D366', 'phone'],
        'facebook' => ['Facebook', '#1877F2', 'url'],
        'messenger' => ['Messenger', '#0A7CFF', 'url'],
        'instagram' => ['Instagram', '#E4405F', 'handle'],
        'linkedin' => ['LinkedIn', '#0A66C2', 'url'],
        'youtube' => ['YouTube', '#FF0000', 'url'],
        'tiktok' => ['TikTok', '#111111', 'handle'],
        'x' => ['X (Twitter)', '#111111', 'handle'],
        'telegram' => ['Telegram', '#26A5E4', 'handle'],
        'line' => ['LINE', '#06C755', 'url'],
        'pinterest' => ['Pinterest', '#E60023', 'url'],
        'shopee' => ['Shopee', '#EE4D2D', 'url'],
        'lazada' => ['Lazada', '#0F146D', 'url'],
        'shopify' => ['Shopify store', '#5E8E3E', 'url'],
        'email' => ['Email', '#475569', 'email'],
        'phone' => ['Phone call', '#0F766E', 'phone'],
        'website' => ['Website / other link', '#6D28D9', 'url'],
    ];

    private const HANDLE_BASE = ['instagram' => 'https://www.instagram.com/', 'tiktok' => 'https://www.tiktok.com/@', 'x' => 'https://x.com/', 'telegram' => 'https://t.me/'];

    public static function options(): array
    {
        return array_map(fn ($p) => $p[0], self::PLATFORMS);
    }

    public static function color(string $platform): string
    {
        return self::PLATFORMS[$platform][1] ?? '#475569';
    }

    public static function label(string $platform): string
    {
        return self::PLATFORMS[$platform][0] ?? ucfirst($platform);
    }

    /** A safe https:/mailto:/tel: link, or null when what was typed can't be one. */
    public static function href(string $platform, string $value, string $message = ''): ?string
    {
        $value = trim($value);
        if (! isset(self::PLATFORMS[$platform]) || $value === '' || mb_strlen($value) > 300) {
            return null;
        }
        $kind = self::PLATFORMS[$platform][2];
        if ($kind === 'email') {
            return filter_var($value, FILTER_VALIDATE_EMAIL) ? 'mailto:'.$value : null;
        }
        if ($kind === 'phone') {
            $digits = preg_replace('/\D+/', '', $value);
            if (strlen($digits) < 7 || strlen($digits) > 15) {
                return null;
            }

            return $platform === 'whatsapp'
                ? 'https://wa.me/'.$digits.($message !== '' ? '?text='.rawurlencode(mb_substr($message, 0, 200)) : '')
                : 'tel:+'.$digits;
        }
        if ($kind === 'handle' && preg_match('/^@?[A-Za-z0-9._]{2,40}$/', $value) && ! str_contains($value, '.com')) {
            return self::HANDLE_BASE[$platform].ltrim($value, '@');
        }
        if (! preg_match('#^https://#i', $value)) {
            if (preg_match('#^[\w-]+(\.[\w-]+)+(/\S*)?$#', $value)) {
                $value = 'https://'.$value;
            } else {
                return null;
            }
        }
        $host = parse_url($value, PHP_URL_HOST);

        return is_string($host) && str_contains($host, '.') && ! preg_match('/\s/', $value) ? $value : null;
    }

    /** Inline SVG glyph (24×24), drawn in currentColor. Simplified marks, not official logo artwork. */
    public static function icon(string $platform): string
    {
        $bag = '<path d="M5.500 8.500h13l-1 11.500h-11z"/><path d="M9 8.500a3 3 0 0 1 6 0" fill="none" stroke="currentColor" stroke-width="1.800" stroke-linecap="round"/>';
        $letter = fn (string $l) => '<text x="12" y="17.600" text-anchor="middle" font-family="Arial,sans-serif" font-weight="800" font-size="8.500" style="fill:var(--bp-ic-bg,#000)">'.$l.'</text>';
        $paths = [
            'whatsapp' => '<path fill-rule="evenodd" d="M12 3a9 9 0 0 0-7.700 13.600L3 21l4.500-1.200A9 9 0 1 0 12 3zm4.400 12.200c-.2.600-1.200 1.100-1.700 1.200-.4.100-1 .1-1.600-.1-.4-.1-.9-.3-1.500-.6-2.600-1.100-4.300-3.800-4.400-4-.1-.2-1-1.300-1-2.500s.6-1.800.9-2c.2-.2.5-.3.7-.3h.5c.2 0 .4 0 .5.4l.8 1.900c.1.1.1.3 0 .4l-.3.5-.4.4c-.1.1-.2.3-.1.5.1.2.6 1 1.300 1.600.9.800 1.600 1 1.900 1.200.2.100.4.1.5-.1l.7-.9c.2-.2.3-.2.5-.1l1.800.9c.2.1.4.2.4.3.1.2.1.7-.1 1.200z"/>',
            'facebook' => '<path d="M13.500 21v-7.500h2.600l.4-3h-3V8.600c0-.9.3-1.500 1.600-1.500h1.600V4.400c-.3 0-1.200-.1-2.300-.1-2.300 0-3.900 1.400-3.900 4v2.200H8v3h2.600V21z"/>',
            'messenger' => '<path fill-rule="evenodd" d="M12 2C6.500 2 2 6.200 2 11.400c0 2.900 1.400 5.500 3.600 7.200V22l3.300-1.800c.9.200 1.900.4 3.100.4 5.500 0 10-4.200 10-9.400S17.500 2 12 2zm1 12.600-2.500-2.700-4.900 2.700 5.400-5.700 2.600 2.700 4.800-2.700z"/>',
            'instagram' => '<rect x="3.500" y="3.500" width="17" height="17" rx="5" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="17.200" cy="6.800" r="1.200"/>',
            'linkedin' => '<path d="M5 9h3v10H5zM6.500 4.500a1.800 1.800 0 1 1 0 3.600 1.800 1.800 0 0 1 0-3.600zM10 9h2.900v1.400c.5-.9 1.600-1.600 3.100-1.600 3 0 3.600 2 3.600 4.600V19h-3v-5c0-1.200 0-2.700-1.700-2.700s-1.900 1.300-1.900 2.600V19H10z"/>',
            'youtube' => '<path fill-rule="evenodd" d="M21.600 8.200a2.500 2.500 0 0 0-1.800-1.800C18.300 6 12 6 12 6s-6.300 0-7.800.4A2.500 2.500 0 0 0 2.400 8.200C2 9.700 2 12 2 12s0 2.300.4 3.800a2.500 2.500 0 0 0 1.800 1.800C5.700 18 12 18 12 18s6.300 0 7.800-.4a2.500 2.500 0 0 0 1.800-1.800c.4-1.500.4-3.800.4-3.800s0-2.300-.4-3.800zM10 15V9l5.200 3z"/>',
            'tiktok' => '<path d="M16.500 3h-2.900v12.200a2.600 2.600 0 1 1-2.600-2.600c.3 0 .5 0 .8.1V9.600a5.600 5.600 0 1 0 4.700 5.600V9.100a6.500 6.500 0 0 0 3.800 1.200V7.400A3.900 3.900 0 0 1 16.500 3z"/>',
            'x' => '<path d="M17.800 3h3l-6.600 7.500L22 21h-6l-4.700-6.100L5.800 21h-3l7-8L2.400 3h6.200l4.200 5.600zm-1 16.200h1.700L7.700 4.700H5.900z"/>',
            'telegram' => '<path fill-rule="evenodd" d="M21.500 4.300 2.900 11.500c-.9.400-.9 1 0 1.300l4.700 1.500 1.800 5.600c.2.600.4.800.9.800s.7-.2 1-.5l2.400-2.300 4.800 3.500c.9.500 1.500.2 1.800-.8l3.100-14.700c.3-1.300-.5-1.900-1.900-1.300zM8.700 13.600l9.300-5.900c.4-.3.8-.1.5.2l-7.600 6.900-.3 3.200z"/>',
            'line' => '<path d="M12 3C6.500 3 2 6.600 2 11c0 3.900 3.500 7.100 8.200 7.800.3.100.8.200.9.500.1.300.1.700 0 1l-.1.900c0 .3-.2 1 .9.500s5.700-3.400 7.800-5.800C21.200 14 22 12.600 22 11c0-4.400-4.500-8-10-8z"/>',
            'pinterest' => '<path d="M12 2a10 10 0 0 0-3.600 19.300c-.1-.8-.2-2 0-2.900l1.200-5s-.3-.6-.3-1.500c0-1.400.8-2.400 1.800-2.400.9 0 1.300.6 1.300 1.400 0 .9-.5 2.100-.8 3.300-.2 1 .5 1.800 1.500 1.800 1.800 0 3.200-1.900 3.200-4.700 0-2.500-1.800-4.200-4.300-4.200-2.900 0-4.600 2.200-4.600 4.400 0 .9.300 1.800.8 2.300.1.100.1.200.1.300l-.3 1.100c0 .2-.2.200-.4.100-1.300-.6-2.100-2.500-2.100-4 0-3.300 2.400-6.300 6.900-6.300 3.600 0 6.400 2.600 6.400 6 0 3.600-2.300 6.500-5.400 6.500-1.100 0-2.100-.5-2.400-1.200l-.7 2.500c-.2.900-.9 2.100-1.300 2.800A10 10 0 1 0 12 2z"/>',
            'shopee' => $bag.$letter('S'),
            'lazada' => $bag.$letter('L'),
            'shopify' => $bag.$letter('S'),
            'email' => '<path d="M3 6.500A1.500 1.500 0 0 1 4.500 5h15A1.500 1.500 0 0 1 21 6.500v11a1.500 1.500 0 0 1-1.500 1.500h-15A1.500 1.500 0 0 1 3 17.500zm2 .8v.4l7 4.800 7-4.800v-.4L12 12z"/>',
            'phone' => '<path d="M6.600 10.800a15.100 15.100 0 0 0 6.600 6.600l2.200-2.200c.3-.3.700-.4 1-.2 1.100.4 2.300.6 3.600.6.600 0 1 .4 1 1V20c0 .6-.4 1-1 1A17 17 0 0 1 3 4c0-.6.4-1 1-1h3.500c.6 0 1 .4 1 1 0 1.300.2 2.500.6 3.600.1.300 0 .7-.2 1z"/>',
            'website' => '<circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2"/><path d="M3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18" fill="none" stroke="currentColor" stroke-width="1.800"/>',
        ];

        return '<svg viewBox="0 0 24 24" width="1em" height="1em" fill="currentColor" aria-hidden="true" focusable="false">'.($paths[$platform] ?? $paths['website']).'</svg>';
    }
}
