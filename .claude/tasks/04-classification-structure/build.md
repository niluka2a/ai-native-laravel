# Build: 04-classification-structure

## Changes
- `apps/api/app/Enums/Category.php` (new): backed enum `support|sales|billing|unknown`.
- `apps/api/app/Data/ClassificationResult.php` (new): `final readonly` value object (category, confidence).
- `apps/api/app/Services/KeywordClassifier.php`: returns `ClassificationResult`; regexes and confidences unchanged.
- `apps/api/app/Services/ClassificationService.php` (new): softening and unknown-threshold policy as named constants; `classify()` is pure, `classifyAndStore()` does `findOrFail` + `applyClassification` + `save` (2 queries).
- `apps/api/app/Models/CustomerRequest.php`: `applyClassification()` (no enum cast).
- `apps/api/app/Http/Requests/ClassifyRequest.php` (new): `message` required|string|max:2000, `requestId` nullable|uuid.
- `apps/api/bootstrap/app.php`: `shouldRenderJsonWhen(fn () => true)`.
- `apps/api/app/Http/Controllers/RequestsController.php`: `classify` only wires FormRequest, service and response; `KeywordClassifier` dependency replaced by `ClassificationService`.
- `apps/web/src/lib/api.ts`: `ClassifyResult.category` narrowed to the four categories.
- `DECISIONS.md` (Task 4 section with the before/after table), `AI_USAGE.md` (checkpoint 5).

## Tests added
- `tests/Feature/ClassifyRequestTest.php`: 16 characterisation cases written first (long and short confidences, 3-word boundary, save and `open` to `in_progress`, resolved unchanged, unknown saved, no id writes nothing, 404, 2000 chars), then 12 added after the refactor (2 queries, `"0"`, 2000/2001 multibyte, 8 invalid-input cases as 422 JSON). All requests are posted via `call()` with no `Accept` header.
- `tests/Unit/ClassificationServiceTest.php`: Mockery stub of `KeywordClassifier`: 0.55 vs 0.549 threshold, 2 vs 3 words, floor, unknown not softened, trimming.
- `tests/Unit/KeywordClassifierTest.php`: updated to the typed result, plus keyword precedence.

## Baseline (characterisation tests on UNCHANGED code)
`php artisan test`: 25 passed (9 existing + 16 new), 59 assertions. So the preserved behaviour is proven on the old code.

## Deviations from the plan
- None in design. Test files use PHPUnit attributes (`#[DataProvider]`) because the installed PHPUnit ignores the `@dataProvider` annotation.
- `RequestsController.php` is not Pint-clean, from code I did not change (`! $message` in `store`/`updateStatus`, and the `{ }` constructor body). Per CLAUDE.md I did not reformat it.

## Verification
| Check | Result |
|-------|--------|
| API tests | 43 passed (120 assertions) |
| Pint (10 changed/new files, controller excluded) | PASS |
| Pint on `RequestsController.php` | FAIL, pre-existing style (see above) |
| Typecheck | no errors |
| Web tests | 4 passed |
| curl `{"message":"invoice"}` | 200 `{"category":"billing","confidence":0.71,"requestId":null}` |
| curl `{}` (no Accept) | 422 JSON `{"message":"The message field is required.","errors":{...}}` |
| curl `{"message":"0"}` | 200 `{"category":"unknown","confidence":0.4,"requestId":null}` |
| curl `requestId: ["a"]` | 422 (was 500) |
| curl `requestId: "nope"` | 422 |
| curl unused uuid | 404 |
| Query count with requestId | 2 (asserted in test) |

Status: PASS
