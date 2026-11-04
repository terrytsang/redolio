<?php

namespace App\Services\Blueprint;

use InvalidArgumentException;

/**
 * Compiles a validated token profile (+ per-direction personality overrides) into semantic
 * CSS custom properties. Every override is validated against a closed set of formats, so
 * token values can never carry arbitrary CSS.
 */
class TokenCssCompiler
{
    private const COLOR_KEYS = ['background', 'surface', 'text', 'muted', 'primary', 'primary_contrast', 'accent'];

    private const DENSITY = [
        'compact' => 'clamp(2.75rem, 6vw, 5rem)',
        'balanced' => 'clamp(3.5rem, 8vw, 7rem)',
        'spacious' => 'clamp(5rem, 11vw, 9.5rem)',
    ];

    public function __construct(private readonly BlueprintFonts $fonts) {}

    public function compile(array $profile, array $overrides = []): string
    {
        $t = $this->resolve($profile, $overrides);

        $vars = [];
        foreach (self::COLOR_KEYS as $key) {
            $vars['--color-'.str_replace('_', '-', $key)] = $t['color'][$key];
        }
        $vars += [
            '--font-display' => $this->fonts->stack($t['typography']['display']),
            '--font-body' => $this->fonts->stack($t['typography']['body']),
            '--radius' => $t['radius'],
            '--button-radius' => $t['button_radius'],
            '--heading-weight' => (string) $t['heading_weight'],
            '--heading-tracking' => $t['tracking'],
            '--eyebrow-transform' => $t['eyebrow_case'],
            '--eyebrow-tracking' => $t['eyebrow_case'] === 'uppercase' ? '.14em' : '.02em',
            '--container' => '1200px',
            '--space-section' => self::DENSITY[$t['density']],
            '--breakpoint-tablet' => '768px',
            '--breakpoint-desktop' => '1024px',
        ];

        $css = ":root{\n";
        foreach ($vars as $name => $value) {
            $css .= "  {$name}: {$value};\n";
        }

        return $css."}\n";
    }

    /** Font ids a compiled profile uses (for @font-face emission and export). @return string[] */
    public function fontIds(array $profile, array $overrides = []): array
    {
        $t = $this->resolve($profile, $overrides);

        return array_values(array_unique([$t['typography']['display'], $t['typography']['body']]));
    }

    /** Profile defaults, then validated overrides. */
    public function resolve(array $profile, array $overrides = []): array
    {
        $t = [
            'color' => $profile['color'],
            'typography' => $profile['typography'],
            'radius' => $profile['radius'],
            'button_radius' => $profile['button_radius'] ?? $profile['radius'],
            'heading_weight' => $profile['heading_weight'] ?? 700,
            'tracking' => $profile['tracking'] ?? '-0.01em',
            'eyebrow_case' => $profile['eyebrow_case'] ?? 'uppercase',
            'density' => $profile['density'] ?? 'balanced',
        ];
        $buttonSet = false;

        foreach ($overrides as $group => $values) {
            switch ($group) {
                case 'color':
                    $t['color'] = array_merge($t['color'], $this->validColors($values));
                    break;
                case 'typography':
                    $t['typography'] = array_merge($t['typography'], $this->validTypography($values));
                    break;
                case 'radius':
                    $t['radius'] = $this->px($values, 'radius');
                    break;
                case 'button_radius':
                    $t['button_radius'] = $this->px($values, 'button_radius');
                    $buttonSet = true;
                    break;
                case 'heading_weight':
                    in_array($values, [500, 600, 700, 800], true) || throw new InvalidArgumentException('Invalid heading_weight override.');
                    $t['heading_weight'] = $values;
                    break;
                case 'tracking':
                    (is_string($values) && preg_match('/^-?0?\.\d{1,3}em$|^0em$/', $values)) || throw new InvalidArgumentException('Invalid tracking override.');
                    $t['tracking'] = $values;
                    break;
                case 'eyebrow_case':
                    in_array($values, ['uppercase', 'none'], true) || throw new InvalidArgumentException('Invalid eyebrow_case override.');
                    $t['eyebrow_case'] = $values;
                    break;
                case 'density':
                    isset(self::DENSITY[$values]) || throw new InvalidArgumentException('Invalid density override.');
                    $t['density'] = $values;
                    break;
                default:
                    throw new InvalidArgumentException("Unsupported token override group [{$group}].");
            }
        }
        // A direction that changes the corner radius keeps buttons in step unless it styles them explicitly.
        if (isset($overrides['radius']) && ! $buttonSet) {
            $t['button_radius'] = $t['radius'];
        }

        return $t;
    }

    private function validColors(array $values): array
    {
        foreach ($values as $key => $hex) {
            if (! in_array($key, self::COLOR_KEYS, true) || ! is_string($hex) || ! preg_match('/^#[0-9A-Fa-f]{6}$/', $hex)) {
                throw new InvalidArgumentException("Invalid colour token override [{$key}].");
            }
        }

        return $values;
    }

    private function validTypography(array $values): array
    {
        foreach ($values as $key => $family) {
            if (! in_array($key, ['display', 'body'], true) || ! is_string($family) || ! $this->fonts->isApproved($family)) {
                throw new InvalidArgumentException("Invalid typography token override [{$key}].");
            }
        }

        return $values;
    }

    private function px(mixed $value, string $name): string
    {
        if (! is_string($value) || ! preg_match('/^\d{1,3}px$/', $value)) {
            throw new InvalidArgumentException("Invalid {$name} override.");
        }

        return $value;
    }
}
