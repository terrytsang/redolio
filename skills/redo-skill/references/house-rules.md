# House rules

A checklist of what an undirected AI redesign defaults to, and what a deliberately art-directed one
does instead. Apply these while building the redesign, not as an afterthought — and apply them on top
of whatever you preserved in the audit (step 1 of `SKILL.md`): these rules govern new decisions, they
don't override a real logo or a real photo you chose to keep.

| Instead of this default... | Do this |
| --- | --- |
| A centered eyebrow label above the headline ("NEW", "✦ FEATURE", a tiny pill badge) | Put the information in the headline itself, or the first sentence under it. If it isn't important enough for either, cut it. |
| A row of three or four icon-topped cards, all the same shape, all the same weight | Vary the section: one dominant item and supporting ones, a list, a single large example instead of three small ones. No two neighboring sections should use the same layout shape. |
| A purple-to-blue (or teal-to-pink) gradient background or button | Flat color. One ground, one ink, one accent — the accent appears on the primary action and perhaps one other element, nowhere else. |
| Soft drop shadows on cards and buttons that aren't actually floating above anything | A one-step difference in surface tone instead. Reserve real shadows for things that genuinely float: an open menu, a modal. |
| A single default sans-serif (Inter, or whatever the framework ships) used for literally everything, no scale beyond "big" and "small" | One or two typefaces, three sizes doing real visual work: one large, one for section headings, one for body text. Nothing in between. |
| Monospace for labels, stats, or "techy" decoration where no code is involved | The regular text face. Monospace only inside actual code blocks. |
| 01 / 02 / 03 step numbering, or "(001)" style indices on a features list | Let position and size show order. If sequence genuinely matters, use a real ordered list. |
| Hairline 1px borders boxing in every card, every stat, every testimonial | Space, or a tone shift, to separate things. Reserve borders for places a border is functionally meaningful (a form field, a table). |
| A frosted-glass / backdrop-blur navigation bar that floats as a pill over the hero | A plain, solid navigation bar: wordmark, a handful of links, one action. |
| Generic stock photography standing in for "the team" or "the product" when no such photo exists | Either the business's own real photo, or honest abstraction (a flat illustration, a type-led section) — never an anonymous stock photo pretending to be this specific business. |
| A hero with a vague, could-apply-to-any-competitor headline ("Grow your business with the right strategy") paired with one specific, concrete subheadline | Make the headline itself carry something specific to this business (see "the one true thing," `SKILL.md` step 2) — don't push all the specificity into smaller type nobody reads first. |
| Equal-weight competing calls to action (three buttons of the same size and color fighting for the same click) | One primary action, visually dominant. Secondary actions are smaller, lower-contrast, or textual links. |
| A section that is mostly empty space with one small icon centered in it, used more than once on the page | At most one deliberate "rest" per page — a placed image or a flat color field, not an accidentally empty box. |
| Uppercase, letter-spaced micro-labels above every single section heading | Sentence case, readable size. Reserve tracked caps for the rare moment it's genuinely a deliberate accent, not a structural habit. |

## Why these specifically

Every item above is something a model defaults to when asked to "make it look modern" with no other
direction, because each one is common enough across training data to feel safe — which is exactly why
it reads as generic the moment a real person looks at it. None of them are wrong in isolation (a
gradient can work; an icon grid can work); the problem is that they're the default, applied without a
reason, on every single AI-generated page, which is what makes a redesign immediately recognizable as
one.

## What this doesn't override

- A real logo, however it's shaped. Keep it.
- A photo of an actual person or actual work, in whatever crop or placement suits it.
- A business's brand colors, if they were a deliberate choice (see the audit, `SKILL.md` step 1) —
  don't force a dealt palette over colors the business already owns.
- Functional UI conventions: a real code block is monospace, a real data table has lines, a real form
  field has a border.
