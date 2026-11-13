# Requirement: Classification structure (`POST /requests/classify`)

## Goal
`RequestsController::classify` handles input validation, classification policy and persistence
all in one method. Give each of them its usual Laravel home (FormRequest, service, model), add
types where they help, and lock in the current classification behaviour with regression tests.

## Evidence (2026-11-13, before any change, live API on :3001)
- `RequestsController.php:58-104`: hand-written validation, the short-message softening and
  unknown-threshold rules, the lookup, the category/confidence write and the
  `open` → `in_progress` transition, all in the controller.
- Validation errors return **HTTP 200** with `{"error": ...}`, so `classifyMessage` in `api.ts`
  treats them as success:
  - `{}`, `"   "`, `123` → `200 {"error":"message must be a non-empty string"}`
  - 2001 chars → `200 {"error":"message too long"}`
- `"0"` is rejected (`! $message` treats `"0"` as falsy): `200 {"error": ...}`. This is a bug.
- `requestId: ["a"]` → **500** (TypeError in `RequestsService::getById(string)`).
- `requestId: "not-a-uuid"` → 404, with a debug stack trace in the body.
- `getById()` eager-loads `notes`, which classify never reads, so 3 queries run where 2 would do
  (find + update).
- `KeywordClassifier::classify()` returns a loosely shaped array (only a PHPDoc shape).
- Input is already trimmed by Laravel's global `TrimStrings` and `ConvertEmptyStringsToNull`
  middleware, so the controller's `trim()` is redundant and `mb_strlen > 2000` measures the
  trimmed value.
- No feature test covers `/requests/classify`. Only `KeywordClassifierTest` (2 unit tests) does.

## Behaviour to preserve (current outputs, verified with curl)
- Messages of 3 or more words: billing 0.86, sales 0.8, support 0.78, unknown 0.4 (same keyword
  precedence).
- Messages under 3 words that match a category: confidence `max(0.5, c - 0.15)`, giving
  `invoice` → billing 0.71, sales 0.65, support 0.63. Unknown is not softened.
- If confidence < 0.55, the category becomes `unknown` and the confidence is kept.
- With `requestId`: the request's category and confidence are saved, `open` → `in_progress`,
  and other statuses are left unchanged. Unknown results are saved too.
- Response shape: `{category, confidence, requestId}`. `requestId` is `null` when absent.
- An unknown `requestId` gives 404.
- Up to 2000 characters (multibyte-aware) are accepted.

## Acceptance criteria
- [ ] Validation lives in a FormRequest. Invalid input returns **422** JSON with Laravel's
      standard `{message, errors}` shape, even when the client sends no `Accept: application/json`
      header (the web client doesn't).
- [ ] The classification policy (softening and threshold) lives in a service, not the controller.
- [ ] Applying a result to a request (category, confidence, status transition) lives on the model.
- [ ] Classifier output is a typed value object with a backed `Category` enum.
- [ ] The controller only wires the request, the service and the response.
- [ ] Every item under "Behaviour to preserve" is covered by tests. They pass before **and** after
      the refactor (characterisation tests).
- [ ] Classify with `requestId` runs 2 queries (it runs 3 today).
- [ ] Deliberate changes are recorded in `DECISIONS.md`. The AI checkpoint goes in `AI_USAGE.md`.
- [ ] `php artisan test`, `pint --test` on changed files, `npm run typecheck` and `npm test` pass.

## Out of scope
- The provider interface, the history table and `GET /requests/history` (task 5).
- Validation on `store` and `updateStatus`, and a `status` enum (same pattern, different task).
- Keyword quality (e.g. `plan` matching "explanation").
- Hiding debug stack traces (that's an `APP_DEBUG` config matter).

## Open questions
- Is the 200 → 422 change for validation errors acceptable? → Proposed: yes. The README asks
  for proper validation, and the frontend already throws on `!res.ok`. **Human to confirm.**
- Should the `category` column get an enum cast on the model? → Proposed: no. Rows outside the
  enum would throw on read and break `GET /requests`. **Human to confirm.**

Status: PASS
