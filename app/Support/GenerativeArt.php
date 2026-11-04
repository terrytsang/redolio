<?php

namespace App\Support;

/**
 * Deterministic, brand-token-driven SVG artwork used wherever a blueprint section has no
 * approved image. Twelve styles (one signature per design direction) are drawn from the
 * semantic colour variables, so artwork always matches the direction's palette, and are
 * seeded by a string so the same page always renders identically (auditable, reproducible).
 * Output contains only numbers and fixed attribute names — no user text is ever interpolated.
 */
class GenerativeArt
{
    public const STYLES = ['waves', 'topo', 'orbs', 'chart', 'rings', 'grid', 'arch', 'blocks', 'stripes', 'dots', 'ribbons', 'mosaic'];

    private int $state = 0;

    public static function svg(string $style, string $seed): string
    {
        $style = in_array($style, self::STYLES, true) ? $style : 'stripes';
        $art = new self;
        $art->state = (crc32($style.'|'.$seed) % 2147483646) + 1;

        return '<svg class="bp-art" viewBox="0 0 800 600" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">'
            .$art->{'draw'.ucfirst($style)}().'</svg>';
    }

    /** Park–Miller minimal-standard generator in [0,1): 64-bit safe and fully deterministic. */
    private function rnd(): float
    {
        $this->state = ($this->state * 48271) % 2147483647;

        return $this->state / 2147483647;
    }

    private function r(float $min, float $max): float
    {
        return round($min + $this->rnd() * ($max - $min), 1);
    }

    private function bg(string $a = 'var(--color-primary)', string $b = 'var(--color-accent)'): string
    {
        return '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="'.$a.'"/><stop offset="1" stop-color="'.$b.'"/></linearGradient></defs><rect width="800" height="600" fill="url(#g)"/>';
    }

    private function drawWaves(): string
    {
        $o = '<rect width="800" height="600" fill="var(--color-primary)"/>';
        for ($i = 0; $i < 5; $i++) {
            $y = 170 + $i * 85;
            $a = $this->r(25, 60);
            $p = $this->r(0, 200);
            $d = "M0 {$y}";
            for ($x = 0; $x <= 800; $x += 100) {
                $d .= ' Q '.($x + 50).' '.round($y + ($x / 100 % 2 ? -$a : $a) + $p / 20).' '.($x + 100).' '.$y;
            }
            $o .= '<path d="'.$d.' V600 H0Z" fill="var(--color-accent)" opacity="'.(0.16 + $i * 0.09).'"/>';
        }

        return $o;
    }

    private function drawTopo(): string
    {
        $o = '<rect width="800" height="600" fill="var(--color-surface)"/>';
        $cx = $this->r(250, 550);
        $cy = $this->r(180, 420);
        for ($i = 1; $i <= 11; $i++) {
            $rx = $i * 38;
            $ry = $i * 27;
            $w = $this->r(-14, 14);
            $o .= '<path d="M'.($cx - $rx).' '.$cy.' C '.($cx - $rx).' '.($cy - $ry + $w).' '.($cx + $rx + $w).' '.($cy - $ry).' '.($cx + $rx).' '.$cy.' C '.($cx + $rx - $w).' '.($cy + $ry).' '.($cx - $rx).' '.($cy + $ry - $w).' '.($cx - $rx).' '.$cy.'Z" fill="none" stroke="var(--color-primary)" stroke-width="'.($i % 4 === 0 ? 2.4 : 1.1).'" opacity="'.round(0.9 - $i * 0.05, 2).'"/>';
        }

        return $o;
    }

    private function drawOrbs(): string
    {
        $o = '<defs><filter id="b"><feGaussianBlur stdDeviation="46"/></filter></defs><rect width="800" height="600" fill="var(--color-surface)"/><g filter="url(#b)">';
        foreach (['var(--color-primary)', 'var(--color-accent)', 'var(--color-primary)', 'var(--color-accent)'] as $i => $c) {
            $o .= '<circle cx="'.$this->r(120, 680).'" cy="'.$this->r(100, 500).'" r="'.$this->r(110, 200).'" fill="'.$c.'" opacity="'.($i < 2 ? 0.9 : 0.55).'"/>';
        }

        return $o.'</g>';
    }

    private function drawChart(): string
    {
        $o = '<rect width="800" height="600" fill="var(--color-surface)"/>';
        for ($i = 1; $i < 6; $i++) {
            $o .= '<line x1="60" x2="760" y1="'.($i * 90).'" y2="'.($i * 90).'" stroke="var(--color-muted)" stroke-width="1" opacity=".25"/>';
        }
        $v = 160.0;
        $pts = [];
        for ($i = 0; $i < 9; $i++) {
            $x = 70 + $i * 82;
            $h = $this->r(90, 360);
            $o .= '<rect x="'.($x - 22).'" y="'.(540 - $h).'" width="44" height="'.$h.'" rx="3" fill="var(--color-primary)" opacity="'.round(0.35 + $h / 600, 2).'"/>';
            $v = max(80, min(420, $v + $this->r(-70, 40)));
            $pts[] = $x.' '.round(520 - $v - $i * 12);
        }
        $o .= '<polyline points="'.implode(' ', $pts).'" fill="none" stroke="var(--color-accent)" stroke-width="5" stroke-linejoin="round" stroke-linecap="round"/>';
        foreach ($pts as $p) {
            [$x, $y] = explode(' ', $p);
            $o .= '<circle cx="'.$x.'" cy="'.$y.'" r="7" fill="var(--color-background)" stroke="var(--color-accent)" stroke-width="4"/>';
        }

        return $o;
    }

    private function drawRings(): string
    {
        $o = '<rect width="800" height="600" fill="var(--color-text)"/>';
        $cx = $this->r(300, 500);
        $cy = $this->r(220, 380);
        for ($i = 14; $i >= 1; $i--) {
            $o .= '<circle cx="'.($cx + $i * 4).'" cy="'.$cy.'" r="'.($i * 26).'" fill="none" stroke="'.($i % 3 === 0 ? 'var(--color-accent)' : 'var(--color-background)').'" stroke-width="'.($i % 3 === 0 ? 2.5 : 1).'" opacity="'.round(0.25 + (15 - $i) * 0.05, 2).'"/>';
        }

        return $o.'<circle cx="'.$cx.'" cy="'.$cy.'" r="38" fill="var(--color-accent)"/>';
    }

    private function drawGrid(): string
    {
        $o = '<rect width="800" height="600" fill="var(--color-surface)"/>';
        for ($x = 0; $x <= 800; $x += 50) {
            $o .= '<line x1="'.$x.'" x2="'.$x.'" y1="0" y2="600" stroke="var(--color-primary)" stroke-width="'.($x % 200 === 0 ? 1.6 : 0.6).'" opacity=".45"/>';
        }
        for ($y = 0; $y <= 600; $y += 50) {
            $o .= '<line y1="'.$y.'" y2="'.$y.'" x1="0" x2="800" stroke="var(--color-primary)" stroke-width="'.($y % 200 === 0 ? 1.6 : 0.6).'" opacity=".45"/>';
        }
        for ($i = 0; $i < 7; $i++) {
            $w = 50 * (1 + (int) ($this->rnd() * 3));
            $h = 50 * (1 + (int) ($this->rnd() * 3));
            $o .= '<rect x="'.(50 * (int) ($this->rnd() * 13)).'" y="'.(50 * (int) ($this->rnd() * 9)).'" width="'.$w.'" height="'.$h.'" fill="'.($i % 3 === 0 ? 'var(--color-accent)' : 'var(--color-primary)').'" opacity="'.($i % 3 === 0 ? 0.9 : 0.78).'"/>';
        }

        return $o;
    }

    private function drawArch(): string
    {
        $o = '<rect width="800" height="600" fill="var(--color-surface)"/>';
        foreach ([[110, 200, 'var(--color-primary)', .95], [310, 280, 'var(--color-accent)', .9], [500, 220, 'var(--color-primary)', .55]] as $i => [$x, $h, $c, $op]) {
            $w = 170;
            $top = 600 - $h - 130 - $this->r(0, 40);
            $o .= '<path d="M'.$x.' 600 V'.($top + $w / 2).' A'.($w / 2).' '.($w / 2).' 0 0 1 '.($x + $w).' '.($top + $w / 2).' V600Z" fill="'.$c.'" opacity="'.$op.'"/>';
        }

        return $o.'<circle cx="'.$this->r(600, 720).'" cy="'.$this->r(90, 160).'" r="46" fill="var(--color-accent)" opacity=".85"/>';
    }

    private function drawBlocks(): string
    {
        $o = '<rect width="800" height="600" fill="var(--color-background)"/>';
        $cols = ['var(--color-primary)', 'var(--color-accent)', 'var(--color-text)', 'var(--color-surface)'];
        $x = 0;
        foreach ([1.4, 1, 1.8, 1.2] as $i => $wFactor) {
            $w = round($wFactor * 170);
            $y = (int) $this->r(0, 200);
            $o .= '<rect x="'.$x.'" y="'.$y.'" width="'.$w.'" height="'.(600 - $y).'" fill="'.$cols[$i % 4].'"/>';
            $o .= '<rect x="'.($x + 18).'" y="'.($y + 18).'" width="'.($w - 36).'" height="'.$this->r(50, 140).'" fill="'.$cols[($i + 1) % 4].'" opacity=".92"/>';
            $x += $w;
        }

        return $o.'<circle cx="'.$this->r(500, 700).'" cy="'.$this->r(120, 220).'" r="'.$this->r(60, 90).'" fill="var(--color-background)" stroke="var(--color-text)" stroke-width="6"/>';
    }

    private function drawStripes(): string
    {
        $o = $this->bg();
        for ($x = -600; $x < 900; $x += 34) {
            $o .= '<line x1="'.$x.'" y1="600" x2="'.($x + 600).'" y2="0" stroke="#fff" stroke-width="'.($x % 102 === 0 ? 6 : 2).'" opacity=".14"/>';
        }

        return $o;
    }

    private function drawDots(): string
    {
        $o = '<rect width="800" height="600" fill="var(--color-surface)"/>';
        $cx = $this->r(280, 520);
        $cy = $this->r(200, 400);
        for ($y = 20; $y < 600; $y += 36) {
            for ($x = 20; $x < 800; $x += 36) {
                $d = sqrt(($x - $cx) ** 2 + ($y - $cy) ** 2);
                $r = 13 - $d / 34;
                if ($r < 1.4) {
                    continue;
                }
                $o .= '<circle cx="'.$x.'" cy="'.$y.'" r="'.round($r, 1).'" fill="var(--color-primary)"/>';
            }
        }

        return $o;
    }

    private function drawRibbons(): string
    {
        $o = $this->bg('var(--color-primary)', 'var(--color-accent)');
        for ($i = 0; $i < 6; $i++) {
            $y = 60 + $i * 90;
            $a = $this->r(70, 150);
            $o .= '<path d="M-20 '.$y.' C 200 '.($y - $a).' 330 '.($y + $a).' 520 '.($y - 20).' S 740 '.($y + $a / 2).' 840 '.($y - 30).' V'.($y + 40).' C 700 '.($y + $a / 2 + 40).' 560 '.($y + 20).' 480 '.($y + 30).' S 180 '.($y + $a + 40).' -20 '.($y + 50).'Z" fill="#fff" opacity="'.round(0.07 + $i * 0.035, 2).'"/>';
        }

        return $o;
    }

    private function drawMosaic(): string
    {
        $o = '<rect width="800" height="600" fill="var(--color-surface)"/>';
        $fills = ['var(--color-primary)', 'var(--color-accent)', 'var(--color-background)', 'var(--color-primary)'];
        for ($row = 0; $row < 4; $row++) {
            for ($col = 0; $col < 6; $col++) {
                $f = $fills[(int) ($this->rnd() * 4)];
                $o .= '<rect x="'.($col * 133 + 6).'" y="'.($row * 150 + 6).'" width="121" height="138" rx="10" fill="'.$f.'" opacity="'.round($this->r(0.45, 0.95), 2).'"/>';
            }
        }

        return $o;
    }
}
