# Browser check

A redesign isn't done when the code looks right — it's done when a rendered screenshot looks right.
This step is deliberately tool-agnostic: it doesn't assume Node, a headless browser binary, or any
specific package, because this skill is meant to run inside whatever coding agent and environment
you're already in. Use whichever of these you have available:

- A browser automation tool built into your agent (for example, Claude Code's browser tool).
- A Playwright, Puppeteer, or similar MCP server if one is configured.
- A plain screenshot utility against a locally running dev server.
- If none of these are available: say so explicitly in your report rather than skipping this step
  silently. A redesign reported as finished without ever being looked at rendered is not finished.

## The loop

1. **Render it.** Get the redesign actually running — a dev server, a built static file opened
   locally, or (inside REDO) the preview renderer.
2. **Screenshot at three widths.** Desktop (around 1440px), tablet (around 768px), and mobile (around
   390px). Capture the full page at each width, not just the first screen — problems hide below the
   fold.
3. **Score each screenshot** against the rubric below.
4. **Fix the worst three problems** you found — not everything at once. Small, verifiable fixes beat
   a big unverified rewrite.
5. **Repeat.** Re-render, re-screenshot, re-score. Two or three rounds is normal; more than that
   usually means step 1–4 of `SKILL.md` (the audit, the one true thing, the chosen direction) wasn't
   settled before building started — go back and fix that instead of continuing to polish.

## The rubric

Score each screenshot 1 (fails badly) to 5 (no notes) on each axis. A redesign isn't ready to report
until every axis is 4 or better on all three widths.

| Axis | What to look for |
| --- | --- |
| **Clear first message** | Looking at the screenshot alone, with no other context: can you tell what this business is and what it wants you to do, within a few seconds? |
| **One dominant action** | Is there exactly one visually obvious primary action, not several competing buttons of equal weight? |
| **Real content, not placeholders** | Is every section showing this business's actual content (from the audit), not lorem ipsum, generic stock imagery, or an obviously fake example? |
| **Section variety** | Does each section look meaningfully different from its neighbors, or is it a repeated card-grid pattern? |
| **Frame discipline** | Are the outer margins equal and consistent at this width? Is anything awkwardly crammed to one side with dead space on the other? Any blank stretch taller than roughly half the screen? |
| **Mobile integrity** | At the 390px screenshot specifically: does anything overflow horizontally, overlap, or become illegibly small? Is the primary action still obviously reachable? |
| **House rules** | Does anything in the screenshot violate `house-rules.md` (a gradient card, an icon row, a pill eyebrow label, a glass navbar)? |

## What "fixed" looks like in a report

Don't just say "improved the hero." State the specific problem you saw in the screenshot, the
specific change you made, and that you re-screenshotted to confirm it. Example: "Desktop screenshot
showed the primary CTA and a secondary link at identical visual weight — reduced the secondary link to
plain text, re-rendered, confirmed the primary action now reads first."
