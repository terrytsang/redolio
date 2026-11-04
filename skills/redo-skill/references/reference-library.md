# Reference library

What follows are original observations about the general design approach of a small set of
widely-recognized sites and design-gallery communities — written from first-hand familiarity with
their publicly visible design language, in our own words, as design-principle notes rather than a
description of any specific page's exact copy, images, or markup. Nothing here is copied from these
sites or from any other "design taste" project; this is the clean-room distillation `SKILL.md`
promises. Treat it as a starting point for your own observations, not a closed list — when you study a
site you admire, add what you learned to `library/reference-sites.json` in the same format.

**How to use this.** These are principles to apply to a real business's redesign, never templates to
copy wholesale, and never a reason to imitate these specific brands' visual identity (their exact
colors, their exact logo treatment, their exact copy). The goal is the *underlying decision-making*,
applied to a completely different, specific business.

## Enduring brand sites

### Apple

The consistent throughline across Apple's product pages is **restraint in service of one message per
screen**. Each scroll stop makes exactly one claim, usually paired with one large, carefully lit
product image and a short, confident sentence — not a paragraph. Typography does most of the
persuasion work: very large display type for the claim, a noticeably smaller supporting line, and
almost nothing in between. White (or a single flat deep color) dominates; color is spent sparingly,
usually to highlight a single product detail rather than to decorate the page. Motion, when present,
reveals one thing at a time as you scroll rather than animating everything at once.

**Applicable pattern:** one dominant visual per section, one claim per section, a confident two-size
type scale, color spent on substance (the actual product/subject) rather than on UI chrome.

### Shopify

Shopify's marketing site balances Apple-style restraint with a more approachable, energetic register
appropriate to a tool aimed at small-business owners rather than a premium consumer product. Sections
mix large confident type with real, specific proof (actual numbers, actual merchant examples) rather
than abstract claims. Layouts use generous whitespace but aren't afraid of a flat, saturated color
field for an entire section to create rhythm as you scroll — alternating neutral and bold sections
rather than a uniform gray wash throughout. CTAs are plain, high-contrast, and singular per section.

**Applicable pattern:** alternating flat-color section backgrounds for scroll rhythm, specific proof
over abstract claims, approachable but still restrained type scale, one clear CTA per section.

### Stripe

Stripe's design language is notable for treating the *product itself* — real interface elements, real
code snippets, real data visualizations — as the primary imagery, rather than illustrating abstract
concepts with generic iconography or stock photography. Where decoration does appear, it tends to be
geometric and purposeful (a gradient mesh used as a true brand signature, applied consistently and
sparingly, not as a generic AI-page gradient background slapped on every card). Type is technical and
precise — monospace is used, but specifically for code and data, never as generic label decoration.
Information density is higher than Apple's, appropriate to a technical/developer audience, but still
organized with a clear single hierarchy per section.

**Applicable pattern:** show the real product/interface as imagery instead of generic icons, reserve
monospace for genuinely technical content, use a signature visual device consistently rather than
generic decoration, maintain one clear hierarchy even at higher information density.

## Design-gallery patterns (Land-book, Awwwards)

Rather than naming individual gallery entries (they rotate constantly and any specific example would
be out of date almost immediately), these are patterns that recur across the kind of work that gets
featured on curator-driven galleries like Land-book and Awwwards, as **general, non-attributable
stylistic tendencies**:

- **Oversized, confident typography carries more of the design than decoration does.** Headlines are
  frequently set far larger than a typical business-site default — large enough that the type itself
  becomes the dominant visual element of the hero, rather than competing with a busy background.
- **A distinctive layout idea, not just a distinctive palette.** The sites that stand out usually do
  so because of an unusual but legible composition choice (an asymmetric split, a full-bleed image
  paired with a tight text column, a grid that breaks intentionally in one place) — color alone rarely
  carries a page to "featured" status.
- **Real, specific imagery over generic stock.** Featured portfolio and agency sites almost always
  show actual project work, actual product shots, or actual environmental photography specific to the
  subject — not interchangeable stock photos.
- **Deliberate, sparse motion.** When animation appears, it's usually one or two signature moments
  (a hero element, a scroll-triggered reveal) rather than everything on the page animating constantly.
- **A considered relationship between sections**, where each section has a genuinely different
  composition from its neighbor, rather than a repeating card-grid pattern reused throughout the page.

**Applicable pattern:** pick one unusual-but-legible composition idea and commit to it for the hero;
vary section layouts rather than repeating a card grid; if there's no real photography available for
this business, prefer honest abstraction (flat color, considered typography) over generic stock.

## Adding your own entries

When you study a site worth learning from, add a structured entry to `library/reference-sites.json`
with: what it is, the one or two things it does distinctly well, and the applicable pattern stated
generally enough to apply to an unrelated business. Never copy its actual copy, images, code, or exact
color values — the point is the underlying decision, not the specific output.
