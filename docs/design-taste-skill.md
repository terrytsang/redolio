# REDO Design Taste Skill

A distilled set of design-taste rules that steer REDO's Meta API redesign generation away from generic
"AI slop" output. REDO doesn't run a live browser or edit arbitrary code — every redesign is a JSON
`DesignSpec` rendered into one of a fixed set of allowlisted Blade templates (see
`app/Services/Rendering/PreviewRenderer::TEMPLATE_VIEWS`). So unlike the two tools this is distilled from,
this skill can't inspect a rendered screenshot or rewrite CSS — it works entirely by shaping what the model
writes into the DesignSpec (copy, section choice, asset references, layout composition) before our
templates render it. The directives below are injected into
`HttpMetaModelClient::STAGE_INSTRUCTIONS['redesign_recommendations']`.

## Sources

- [Leonxlnx/taste-skill](https://github.com/Leonxlnx/taste-skill) — an agent skill that deals a researched
  design direction, ties it to one real fact about the client, builds with real credited photography, and
  checks the result in a browser across three widths, failing it on "slop."
- [pbakaus/impeccable](https://github.com/pbakaus/impeccable) — a design vocabulary and 62-rule slop
  detector for coding agents; names the recurring AI-generated-UI tells (Inter everywhere, purple-to-blue
  gradients, cards nested in cards, a rounded-square icon tile above every heading) and gives agents
  specific commands (`bolder`, `quieter`, `distill`, `polish`) instead of vague instructions like "make it
  pop."

## What "AI slop" looks like, and REDO's translation of each rule

| Slop tell (from the two skills) | How it shows up in a JSON DesignSpec | REDO's rule |
|---|---|---|
| Generic default: cream page, thin serif, italic accent, pill buttons, regardless of the brief | Picking `modern_sans` + default color tokens for every business | The chosen `direction_key`'s own typography/color system must be used deliberately — never silently fall back to generic defaults when a direction was explicitly requested |
| Stock/generic imagery instead of something tied to the one real subject | Describing a "generic stock photo" as if it were real, or omitting real assets | `available_assets` (real logo/portraits/portfolio photos) must be preferred over inventing placeholder descriptions; never claim a generic image is the real one |
| One sentence tying the brief to a direction ("the house at night, seen from the water") | A headline that could apply to any business in the industry | The hero headline must reference something specific from this business's own DNA (a named person, a specific service, a specific place) — not an interchangeable industry platitude |
| Cards nested in cards, gray text on colored backgrounds | Section `items` with redundant nested containers implied by copy structure | Keep section copy direct — one clear heading, one clear body, items that stand on their own without manufactured visual nesting |
| No clear title on the first screen, uneven margins, random blank stretches | A `hero.headline` that's vague/generic, or sections with no body and no items (an empty shell) | Every rendered section must have either a body or items — never an empty section kept only for "rationale"; the hero headline is the single clearest claim on the page |
| "Make it pop" — vague instructions with no steerable target | N/A (prompt-engineering meta-rule) | `preferences` and `direction_key` are specific, named levers (preserve_logo, preserve_wording, direction tone) — REDO never asks the model to vaguely "improve" without a named target |

## How this is wired in

The condensed version of this table lives as directives inside
`HttpMetaModelClient::STAGE_INSTRUCTIONS['redesign_recommendations']` (and a shorter version in
`design_direction_ranking`), so every DesignSpec generation call carries it. If REDO ever adds a stage that
inspects its own rendered preview (e.g. a screenshot-based critique pass, matching taste-skill's "check in a
real browser, fail on slop" step), this file is where that stage's rules should be documented and the
PRD's no-headless-browser constraint should be revisited first.

## Non-goals (things we deliberately did not adopt)

- **No Node/headless-Chrome dependency.** Both source skills assume a live dev server and browser
  automation (Playwright/Puppeteer-class tooling) to screenshot and iterate. REDO's architecture is
  explicitly server-side PHP with no headless browser — see the SSRF/scraping-infeasibility findings
  earlier in this project. We adopt the *taste rules*, not the *tooling*.
- **No "deal 3 of 20 directions" randomness.** REDO's direction catalogue is fixed and allowlisted
  (`DesignDirection.is_selectable`); every selectable direction must have a real, tested Blade template
  (AT-32). We rank all eligible directions deterministically + via the model, rather than dealing a random
  subset.
