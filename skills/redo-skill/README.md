# redo-skill

An Agent Skill that gives coding agents taste for **redesigning an existing website** — art
direction, a curated reference library, and a browser-based look-and-fix loop, instead of a generic
AI template.

Works with Claude Code, Codex, Antigravity, Meta Muse Spark, Kimi, and any other coding agent that
reads a `SKILL.md`. No required dependencies — the browser-check step adapts to whatever
screenshot/browser tool your environment already provides.

## Use it

Point your agent at `SKILL.md` and ask it to redesign, restyle, or modernize an existing site. Start
with:

```
Read skills/redo-skill/SKILL.md and use it to redesign <the site/page>.
```

## What's inside

- `SKILL.md` — the methodology: audit the existing site, find the one true thing about this business,
  choose a direction, apply the house rules, run the browser check, report.
- `references/house-rules.md` — the anti-slop checklist.
- `references/reference-library.md` — what Apple, Shopify, Stripe, and the Land-book/Awwwards galleries
  do well, in our own words, as applicable design principles.
- `references/browser-check.md` — the look-and-fix loop, tool-agnostic.
- `library/direction-recipes.json` — seven chooseable art directions (type, color, layout logic each).
- `library/reference-sites.json` — structured notes on the reference sites above.

## Relationship to REDO

This skill's `direction-recipes.json` shares its logic with the design directions implemented in the
main REDO application (`database/seeders/DesignDirectionSeeder.php`,
`app/Services/MetaApi/HttpMetaModelClient.php`) — REDO is this skill's own methodology, applied inside
a Laravel app that automates the audit (Website DNA + diagnosis) and the direction choice (the Design
Direction Selector) for you. This package exists so the same taste — the reasoning, not the Laravel
code — is usable directly inside any coding agent, on any project, independent of REDO.

See `docs/design-taste-skill.md` at the repository root for the version of this guidance wired
directly into REDO's own Meta API redesign prompts.

## License

MIT — same as the rest of this repository. Every rule, recipe, and reference entry here is original
writing; see the "A note on sources" section in `SKILL.md`.
