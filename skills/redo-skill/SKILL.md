---
name: redo-skill
description: >-
  Art direction for redesigning an EXISTING website — not generating one from a blank brief. Gives
  coding agents (Claude Code, Codex, Antigravity, Meta Muse Spark, Kimi, and others) a researched
  taste reference distilled from enduring brand sites (Apple, Shopify, Stripe) and curated
  design-gallery patterns (Land-book, Awwwards), a strict anti-slop checklist, and a browser-based
  look-and-fix loop, so a redesign reads as deliberately art-directed instead of a generic AI
  template. Use this whenever asked to redesign, restyle, modernize, refresh, or "make this look
  less AI-generated" for an existing website or landing page, in any stack.
---

# redo-skill

An AI-redesigned page usually looks like the average of every page the model has seen, because
nothing about it was decided — and worse, a redesign that wasn't decided also usually **throws away
the one thing the original site had that a template never will: a real, specific business behind
it.** The logo someone actually chose. The photo of the actual person. The sentence that only this
business could have written. Generic redesigns replace all of it with a nicer-looking version of
nothing in particular.

This skill exists to prevent both failures at once. It is built around three ideas:

1. **Redesign starts with an audit, not a blank page.** Before anything is restyled, decide what
   already works and must survive (real photos, a logo, a voice, a fact only this business has) and
   what's actually broken (not "it looks old" — a specific, nameable problem: no clear call to
   action, three competing headlines, a trust gap). A redesign that can't name what it preserved and
   what it fixed is a repaint, not a redesign.
2. **One deliberate direction, chosen on purpose.** `library/direction-recipes.json` holds a small
   set of researched art directions, each with a distinct type system, color proportions, and layout
   logic. Pick the one direction that fits this business's real identity — never the generic SaaS
   default (centered headline, three icon cards, soft gradient, pill button).
3. **A browser check closes the loop.** Specs and good intentions don't catch a cramped hero or a
   broken mobile layout — a screenshot does. Render the redesign, look at it at three widths, score
   it against the checklist, fix the worst three problems, look again.

## The redesign run

### 1. Audit the existing site

Before touching anything, write down two short lists:

- **Preserve.** The logo (if it's a real, considered mark — not a default favicon). Any photo of an
  actual person or actual work. The business's name and what it does, in its own words where that
  wording is already clear and specific. Brand colors, if they were clearly a deliberate choice
  rather than a framework default.
- **Fix.** Name the actual problems, each as a specific, checkable claim: "the primary action
  competes with two others of equal visual weight," "the hero image is a generic stock photo, not
  this business," "there's no way to tell what this costs or how to start." Vague complaints ("looks
  dated") aren't fixable — restate them as something concrete before moving on.

If you're working inside REDO itself, this step is already built: `WebsiteDnaService` and the
diagnosis stage produce exactly this pair of lists, each claim tied to evidence from the real crawled
site. Outside REDO, do it by hand — read the real page, don't assume.

### 2. Find the one true thing

Every business has at least one fact a template can't invent: what its name actually means, a
specific number (years running, seats, a price), the one thing it does that its nearest competitor
doesn't, a ritual or constraint it operates under. Write one sentence:

> This redesign uses **[the specific fact]** to make the **[hero / one section]** unmistakably this
> business's, not any competitor's in the same category.

If a competitor's name could replace this business's name in that sentence and it would still read
true, the fact isn't specific enough — find a better one. "Family-owned since 1987" is a fact; "we
care about quality" is not.

### 3. Choose a direction

Read `library/direction-recipes.json`. Each entry is a complete, distinct system: type pairing, color
proportions, layout logic, what it's good for. Pick the one whose logic actually fits the audited
business and the fact from step 2 — not the one that looks most like what you'd build by default.
`references/reference-library.md` explains where these recipes come from and how to extend them with
your own observations of a site you admire.

If the business already has real brand colors or a typeface worth keeping (from step 1), keep those
and take only the layout logic and proportions from the chosen recipe.

### 4. Apply the house rules

`references/house-rules.md` is a short, strict checklist of what a generic AI page defaults to and
what to do instead (gradients, pill badges, icon-topped card rows, tiny tracked-caps labels, and
more). Apply it while building, not after.

### 5. Browser check

`references/browser-check.md` is the look-and-fix loop: render the page, screenshot it at desktop,
tablet, and mobile widths using whatever browser or screenshot tool is available in your environment
(Claude Code's browser tool, a Playwright/Puppeteer MCP server, a plain screenshot utility — this
skill doesn't require any one of them), score each screenshot against the rubric, fix the worst three
problems, repeat once or twice more. Never report a redesign done without having looked at it
rendered.

### 6. Report

State plainly: what was preserved and why, what direction was chosen and why it fits this business
specifically, the one true thing from step 2 and where it shows up, and the browser-check score from
your last round with anything still short of ideal. Don't claim a check you didn't actually run.

## Files

| File | Read it for |
| --- | --- |
| `references/house-rules.md` | The anti-slop checklist — what to avoid and what to do instead. |
| `references/reference-library.md` | What Apple, Shopify, Stripe, and the Land-book/Awwwards galleries actually do well, and how to add your own observations. |
| `references/visual-richness.md` | Image-to-content relevance, when to use an icon vs. a photo vs. nothing, and SMB design patterns worth carrying into future direction recipes. |
| `references/browser-check.md` | The look-and-fix loop: how to screenshot, score, and iterate. |
| `library/direction-recipes.json` | The chooseable art directions: type, color, layout logic for each. |
| `library/reference-sites.json` | Structured notes on specific reference sites — principles only, never copied content. |

## A note on sources

This skill is an original methodology, written for redesigning real existing businesses' websites.
It was developed after studying how other "design taste" agent skills approach the same general
problem (giving coding agents art direction instead of defaults), but every rule, recipe, and
reference entry here is independently written from first-hand knowledge of the referenced sites'
publicly visible design language — never copied text, code, or assets from any other project. See
`references/reference-library.md` for what "reference" means here: principles and patterns, stated in
our own words, never another site's actual copy, images, or markup.
