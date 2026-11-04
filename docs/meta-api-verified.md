# Meta Model API — Verification Status

**Status: VERIFIED by live smoke test.** Confirmed against
`https://dev.meta.ai/docs/overview` (and the Muse Spark section
specifically) and, more importantly, against the real API itself — the
full P0 pipeline (crawl → Website DNA → diagnosis → DesignSpec →
evaluation) was run end-to-end against `https://example.com` with live
credentials and completed successfully on 2026-10-30. Response headers
(`x-route: model-api-rust`, `e_fb_zone`/`e_fb_vipaddr` proxy markers)
confirm this is genuine Meta-operated infrastructure, not a guessed or
hallucinated endpoint.

## Confirmed contract

| Item | Status | Value |
|---|---|---|
| Base URL | ✅ Verified | `https://api.meta.ai/v1` |
| Endpoint | ✅ Verified | `POST {base_url}/responses` (the Responses API) |
| Auth header | ✅ Verified | `Authorization: Bearer {META_API_KEY}` — no other required headers |
| Model identifier | ✅ Verified | `muse-spark-1.3` (also valid: `muse-spark-1.2`, `muse-spark-1.1`, and `-contributor` variants) |
| Request shape (plain) | ✅ Verified | `{"model": "...", "input": "<text prompt>"}` |
| Structured output field | ✅ Verified (corrected via live 400 error) | `text.format` = `{"type": "json_schema", "name": "...", "schema": {...}}` — **flat**, not nested under a `json_schema` key as the docs page implied. The live API's own error message (`` `text.format` missing required field `name` ``) is what revealed the correct shape. |
| Response shape | ✅ Verified | `output[]` array; the generated text/JSON is in the item where `type == "message"`, at `content[0].text` (a JSON string when structured output was requested — must be `json_decode`d, it is not pre-parsed into the envelope) |
| Usage/token fields | ✅ Verified | `usage.input_tokens`, `usage.output_tokens` (plus `*_tokens_details` breakdowns, unused) |
| Error shape | ✅ Verified | `{"error": {"code": "...", "message": "...", "param": ..., "type": "..."}}` — e.g. `invalid_api_key` / `authentication_error` on a bad key |
| Multi-turn / conversation continuity | Not used | Each stage sends the minimum context required; no `conversation_id` is invented, consistent with PRD policy |
| Rate limits / pricing | Documented, not independently load-tested | 3,000 RPM / 4,000,000 TPM per team (not per key) per `/docs/pricing-rate-limits`; `429` returns `Retry-After` and is already in our retryable-status list |
| Timeout/retry defaults | Unchanged from PRD | 90s timeout, 3 attempts, backoff `[1, 2, 4]`s — not yet pressure-tested against real rate limits |

## What changed in the implementation

- `HttpMetaModelClient::callStage()` now posts to `/responses` with the
  real `{model, input, text.format}` shape instead of the old placeholder
  `{model, prompt_version, stage, input}` / `/v1/reasoning` contract.
- `StageSchemas` (new) provides a JSON Schema per reasoning stage
  (`website_understanding`, `diagnosis`, `redesign_recommendations`,
  `evaluation`, `next_redo_optional`), derived from the PRD's `schemas`
  section, passed as `text.format.schema`.
- `normalizeResponse()`/`extractStructuredData()` now parse the real
  `output[].content[].text` envelope and `json_decode` the structured
  text, with a defensive fallback if the shape ever varies.
- App-side validation (`App\Services\MetaApi\Validation\*`) remains
  authoritative regardless of schema-constrained output, per PRD
  `structured_output` policy — confirmed working: a live DNA response was
  successfully validated through `WebsiteDnaValidator` with zero manual
  intervention.

## Live smoke test record (2026-10-30)

- Raw auth check: `POST /responses {"model":"muse-spark-1.3","input":"..."}` → `200`, correct text echoed back.
- Structured output check: same endpoint with `WebsiteDna` schema → `200`, schema-conformant JSON, evidence IDs correctly cited only from the provided `valid_evidence_ids`.
- Full pipeline: `AnalyzeWebsiteAction::execute()` against `https://example.com` →
  `status=complete`, all stage logs `complete`, Website DNA + 5-issue
  DiagnosisReport + DesignSpec v1 + EvaluationReport all persisted,
  `duration_ms=111698` (~112s; PRD target is ≤90s where latency permits,
  acceptable given first-call warmup and `reasoning.effort: high`).
- Correctness check: for the placeholder `example.com` content, the model
  correctly reported `business_name: null`/`industry: "Unknown"`,
  returned zero fabricated offerings/audience, and diagnosed real,
  evidence-cited structural issues (no positioning, no CTA, no trust
  signals) rather than inventing business details — consistent with the
  PRD's evidence-grounding requirement.

## Muse Image (image generation) — VERIFIED 2026-11-02

Confirmed against `https://dev.meta.ai/docs/overview` and, as above, the
real API itself: live `POST https://api.meta.ai/v1/images/generations`
calls with the project's real `META_API_KEY` returned `200` with genuine
images. Same `x-route: model-api-rust` fingerprint as the verified
Muse Spark endpoint.

| Item | Status | Value |
|---|---|---|
| Base URL | ✅ Verified | `https://api.meta.ai/v1` (same as Muse Spark) |
| Endpoint | ✅ Verified | `POST {base_url}/images/generations` (also documented: `POST /images/edits` for multi-turn editing — not used here) |
| Auth header | ✅ Verified | `Authorization: Bearer {META_API_KEY}` — identical to Muse Spark |
| Model identifier | ✅ Verified | `muse-image-1.0` |
| Request shape | ✅ Verified | `{"model": "muse-image-1.0", "prompt": "<text prompt>"}` — no other fields required |
| Response shape | ✅ Verified | `{"created": <unix ts>, "data": [{"b64_json": "<base64 image>"}], "output_format": "webp", "background": "opaque", "usage": {"input_tokens", "output_tokens", "total_tokens"}}` |
| Output format | ✅ Verified | WEBP, 1920x1280 by default (`getimagesizefromstring()` decodes it correctly — no special handling needed, unlike SVG) |
| Error shape | Not independently tested | Assumed identical to Muse Spark's `{"error": {...}}` shape given shared infrastructure; not yet forced via a live 4xx |

### Live smoke test record (2026-11-02)

- `POST /images/generations {"model":"muse-image-1.0","prompt":"A simple modern illustration of a bakery storefront, flat vector style"}` → `200`, `data[0].b64_json` decoded to a genuine 1920x1280 WEBP image (165KB).

## Known gaps / follow-ups

- Rate-limit and pricing numbers come from the docs page, not from
  exhausting the real limit — treat `3000 RPM` as directional.
- `reasoning.effort: high` appears to be a model default we didn't
  request; this adds latency (most of the ~112s run) — worth revisiting
  if speed becomes a priority (e.g. requesting lower reasoning effort, if
  the API exposes that as a request field — not yet verified).
- Opalstack-specific outbound HTTPS behavior (V7 in the PRD's
  verification gates) has not been tested — this smoke test ran from
  local dev only.
