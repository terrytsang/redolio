<?php

namespace App\Support;

/**
 * Maps allowlisted DesignSpec token identifiers to Tailwind utility
 * classes. Inputs here have already passed DesignSpecValidator, so this
 * is a closed lookup table, never a pass-through of model-provided text.
 */
class DesignTokens
{
    private const COLOR_HEX = [
        'slate' => '#475569',
        'ink' => '#111827',
        'cream' => '#FAF7F0',
        'sand' => '#E7DFD0',
        'forest' => '#14532D',
        'ocean' => '#0C4A6E',
        'clay' => '#9A3412',
        'plum' => '#581C87',
        'amber' => '#D97706',
        'white' => '#FFFFFF',
        'black' => '#000000',
    ];

    private const TYPOGRAPHY_CLASS = [
        'modern_sans' => 'font-sans',
        'editorial_serif' => 'font-serif',
        'friendly_rounded' => 'font-sans',
        'technical_mono' => 'font-mono',
    ];

    private const SPACING_CLASS = [
        'compact' => 'space-y-8',
        'balanced' => 'space-y-16',
        'spacious' => 'space-y-28',
    ];

    private const BUTTON_CLASS = [
        'solid' => 'rounded-md',
        'outline' => 'rounded-md border-2 bg-transparent',
        'rounded' => 'rounded-full',
    ];

    public static function colorHex(string $token): string
    {
        return self::COLOR_HEX[$token] ?? self::COLOR_HEX['ink'];
    }

    public static function typographyClass(string $token): string
    {
        return self::TYPOGRAPHY_CLASS[$token] ?? 'font-sans';
    }

    public static function spacingClass(string $token): string
    {
        return self::SPACING_CLASS[$token] ?? self::SPACING_CLASS['balanced'];
    }

    public static function buttonClass(string $token): string
    {
        return self::BUTTON_CLASS[$token] ?? self::BUTTON_CLASS['solid'];
    }
}
