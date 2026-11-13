# Plan: 04-classification-structure

## Problem
See `requirement.md`, Evidence. In short, one controller method handles three jobs, errors come
back as 200, two input bugs exist (`"0"` and the 500 on array ids), and one query is wasted.

## Target layout
```
ClassifyRequest (FormRequest)   validation only
        │ validated message, requestId
RequestsController::classify    wiring only
        │
ClassificationService           policy: classifier → soften → threshold; apply if requestId
        ├── KeywordClassifier   keyword rules only, returns ClassificationResult
        └── CustomerRequest::applyClassification()   state change on the model
```

## Changes (all paths under `apps/api`)
0. **Tests first.** Add `tests/Feature/ClassifyRequestTest.php` with the "preserve" cases and
   run it against the current code to confirm it passes. Error-case assertions are added in step 6.
1. `app/Enums/Category.php`: `enum Category: string { Support, Sales, Billing, Unknown }`.
2. `app/Data/ClassificationResult.php`:
   `final readonly class ClassificationResult(Category $category, float $confidence)`.
3. `app/Services/KeywordClassifier.php`: return `ClassificationResult` instead of an array. The
   regexes and confidences stay unchanged. Update `KeywordClassifierTest` to match.
4. `app/Services/ClassificationService.php`, which owns the policy, with named constants
   (`SHORT_MESSAGE_MAX_WORDS = 3`, `SHORT_MESSAGE_PENALTY = 0.15`, `SOFTENED_FLOOR = 0.5`,
   `UNKNOWN_THRESHOLD = 0.55`):
   - `classify(string $message): ClassificationResult`: pure, no DB access.
   - `classifyRequest(string $message, ?string $requestId): ClassificationResult`: if an id is
     given, `CustomerRequest::query()->findOrFail($id)` (no `with('notes')`), then
     `applyClassification()` and `save()`. That is 2 queries, and `ModelNotFoundException`
     renders as 404.
5. `app/Models/CustomerRequest.php`: `applyClassification(ClassificationResult $r): void` sets
   `category` and `confidence`, and moves `open` → `in_progress`. No enum cast (see requirement).
6. `app/Http/Requests/ClassifyRequest.php`: `authorize()` returns `true`. The rules are
   `message: required|string|max:2000` and `requestId: nullable|uuid`. `max` on strings uses
   `mb_strlen`, and the global `TrimStrings`/`ConvertEmptyStringsToNull` middleware already makes
   a whitespace-only message fail `required`, so the current rules carry over exactly.
7. `bootstrap/app.php`: `$exceptions->shouldRenderJsonWhen(fn () => true)`. The app is API-only,
   and without this a failed FormRequest **redirects (302)**, because the web client sends no
   `Accept: application/json`.
8. `RequestsController::classify(ClassifyRequest $request): array`: call
   `ClassificationService::classifyRequest(...)` and return
   `['category' => $r->category->value, 'confidence' => $r->confidence, 'requestId' => ...]`.
   Remove the `KeywordClassifier` dependency from the controller.
9. `apps/web/src/lib/api.ts`: narrow `ClassifyResult.category` to
   `'support' | 'sales' | 'billing' | 'unknown'` (one line, checked by typecheck).
10. `DECISIONS.md` (task 4 section) and `AI_USAGE.md` checkpoint.

## Tests
- **Feature `ClassifyRequestTest`** (RefreshDatabase):
  - Long messages for each category return the exact current confidences (0.86, 0.8, 0.78, 0.4).
  - Short messages return the softened values: 0.71, 0.65, 0.63, and unknown stays 0.4.
  - With `requestId`, an `open` request has its category and confidence saved and becomes
    `in_progress`. `resolved` stays `resolved`. Unknown is saved. The id is echoed back.
  - No `requestId` gives `requestId: null` and no DB writes.
  - A missing id gives 404.
  - 422 for: missing or blank message, non-string message, 2001 characters, `requestId` not a
    UUID or an array. These are posted **without** an `Accept` header, to prove the
    shouldRenderJsonWhen change works.
  - `"0"` is accepted. 2000 multibyte characters are accepted.
  - The query log shows exactly 2 queries for classify with `requestId`.
- **Unit `ClassificationServiceTest`**: stub `KeywordClassifier` (Mockery) to reach the threshold
  branch, which the real keywords can't reach, at 0.55, 0.549 and short-message boundaries.
  Covers the 2- versus 3-word boundary.
- **Unit `KeywordClassifierTest`**: updated to the typed result. Keyword precedence: billing
  beats sales beats support.

## Deliberate behaviour changes (go into DECISIONS.md)
| Input | Before | After |
|---|---|---|
| invalid or missing message, > 2000 chars | 200 `{error}` | 422 `{message, errors}` |
| `"0"` | 200 `{error}` | 200 unknown 0.4 |
| `requestId` malformed | 404 | 422 |
| `requestId` array | 500 | 422 |
| 404 body text | `Request X not found` | Laravel's default model-not-found message |
| queries with `requestId` | 3 | 2 |

## Risks
- `shouldRenderJsonWhen(fn () => true)` makes **all** errors JSON, including `GET /`. That's
  intended for an API-only app, and every route already returns JSON.
- `KeywordClassifier`'s return type changes. It is only called from the controller and its own
  test.
- The 422 shape differs from `{error}`. No frontend code reads `error` (checked with grep), and
  `classifyMessage` already throws on `!ok`.

## Approval
2026-11-13: approved by the human with the recommended answers to the open questions:
1. 200 `{error}` → 422 for validation errors: **yes**.
2. Enum cast on `CustomerRequest::category`: **no**.
3. Provider interface: **deferred to task 5**. Tests stub the concrete `KeywordClassifier`.

Branch: `refactor/task-4-classification-structure`, from `origin/develop` (3af14ba).

Status: PASS (approved)
