# Visual richness: images, icons, and when a page reads as "plain"

Learned from a batch of reference SMB website designs (mortgage, healthcare, food
manufacturing, organic retail, agriculture, automotive — a consistent, well-executed local-
business design system reused smartly across industries) and from real REDO output that read
as too plain and text-only by comparison. This file documents what changed in the renderer to
fix the gap, and the judgment calls that go with using it — not a contradiction of
`house-rules.md`'s "vary the section" rule, a sharper version of it.

## What was actually broken (fixed in code, not just guidance)

1. **Image-to-content relevance.** `hero.image_asset_key` and every section item's `asset_key`
   now resolve to the *specific* asset the model named for that *specific* slot
   (`PreviewRenderer::resolveItemImageUrls()`), instead of images being handed out
   sequentially regardless of which card they ended up on. A card showing an unrelated photo
   (a dashboard screenshot on a "team confidence" card, say) was a rendering bug, not a model
   quality problem — always use `asset_key` to pin a genuinely matching image to its card, and
   leave it null rather than borrow one that doesn't fit.
2. **Icon fallback.** `items[].icon` (schema enum backed by `config('design_allowlist.icons')`,
   rendered by `<x-icon>`) lets a feature/value/trust item get a clean, on-brand icon treatment
   when no real/stock/AI-generated image genuinely suits it, instead of either an empty text
   block or a wrong photo. An icon card is a first-class, deliberate choice — the reference
   designs mix photo cards and icon cards constantly, often in the same grid.

## The nuance with `house-rules.md`

That file already warns against "a row of three or four icon-topped cards, all the same
shape, all the same weight." That rule is about **uniform repetition**, not icons themselves —
a section where every single item is an identical colored-circle icon, same size, same
position, is exactly the generic AI default it's warning about. The fix:

- **Mix within a grid.** If a section has 4-6 items, it's fine — often better — for some to
  carry a real/relevant image and others an icon, rather than forcing all of them into the
  same treatment.
- **Let content density vary.** One item can carry more supporting detail (a stat, a short
  list) than its neighbors; they don't all need to be interchangeable.
- **Icons are a precision tool, not decoration.** Pick the one icon name that actually matches
  the item's specific claim (`clock` for turnaround time, `shield-check` for a guarantee,
  `map-pin` for a location fact) — never a generic "here's *an* icon so the card isn't bare"
  choice.

## Reference patterns worth carrying into future direction recipes

Observed consistently across the reference set, not yet all wired into the six templates —
useful as design direction when authoring new recipes via Design Intelligence → Generate from
URL, or when extending a template:

- **Trust-badge row directly under the hero**: 3-4 short icon+label pairs (e.g. "100% Safe
  Standards", "Fast & Easy Application"), white background, circular tinted icon backgrounds —
  a fast, low-cost way to add credibility signal immediately below the fold.
- **Stat band**: a full-width accent/dark band with 3-4 icon+number+label tiles ("50K+ Happy
  Customers", "15+ Years"). Works well as a section divider between a feature grid and
  testimonials.
- **Image + floating badge**: a photo with a small overlapping card in one corner (a stat, a
  certification badge, a "100% Organic" seal) — adds depth without a second full section.
- **Alternating section tone**: every other major section gets a tint shift (white → the
  direction's surface color) rather than every section sharing one flat background end to
  end — breaks up long pages into readable rhythm.

## What never changes

- Never invent an asset_id or icon name outside the allowlists — both are validated server-side
  regardless of what the model claims (`DesignSpecValidator`).
- A stock photo or AI-generated illustration is always honestly captioned as generic supporting
  imagery, never implied to depict this specific business's real people, product, or place
  (`asset_origin`, see `AbstractModelClient::STAGE_INSTRUCTIONS['redesign_recommendations']`).
- Preserving a real original asset always outranks a stock photo, which always outranks an
  AI-generated one, which always outranks a wrong/irrelevant image.
