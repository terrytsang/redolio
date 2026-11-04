<?php

/*
 * Approved fonts for blueprint sites (PRD v1.5: "load only approved fonts, with robust
 * system fallbacks"). All are open-licensed (SIL OFL) and self-hosted via the Vite
 * Bunny Fonts build (vite.config.js) — nothing is ever loaded from a third-party CDN
 * at runtime. A token override may only name an id from this list.
 *
 * id => [family, alias in public/build/fonts-manifest.json, fallback stack kind]
 */
return [
    'instrument-sans' => ['family' => 'Instrument Sans', 'fallback' => 'sans'],
    'inter' => ['family' => 'Inter', 'fallback' => 'sans'],
    'manrope' => ['family' => 'Manrope', 'fallback' => 'sans'],
    'fraunces' => ['family' => 'Fraunces', 'fallback' => 'serif'],
    'source-sans-3' => ['family' => 'Source Sans 3', 'fallback' => 'sans'],
    'space-grotesk' => ['family' => 'Space Grotesk', 'fallback' => 'sans'],
    'ibm-plex-sans' => ['family' => 'IBM Plex Sans', 'fallback' => 'sans'],
    'instrument-serif' => ['family' => 'Instrument Serif', 'fallback' => 'serif'],
    'archivo' => ['family' => 'Archivo', 'fallback' => 'sans'],
    'cormorant-garamond' => ['family' => 'Cormorant Garamond', 'fallback' => 'serif'],
    'jost' => ['family' => 'Jost', 'fallback' => 'sans'],
    'rubik' => ['family' => 'Rubik', 'fallback' => 'sans'],
    'syne' => ['family' => 'Syne', 'fallback' => 'sans'],
    'dm-sans' => ['family' => 'DM Sans', 'fallback' => 'sans'],
    'playfair-display' => ['family' => 'Playfair Display', 'fallback' => 'serif'],
    'work-sans' => ['family' => 'Work Sans', 'fallback' => 'sans'],
    'plus-jakarta-sans' => ['family' => 'Plus Jakarta Sans', 'fallback' => 'sans'],
    'lora' => ['family' => 'Lora', 'fallback' => 'serif'],
    'nunito-sans' => ['family' => 'Nunito Sans', 'fallback' => 'sans'],
];
