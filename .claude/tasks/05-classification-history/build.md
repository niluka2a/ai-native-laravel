# Build: 05-classification-history

## Changes (API, under `apps/api`)
- `app/Contracts/ClassifierProvider.php` (new): `classify()` and `name()`.
- `app/Services/KeywordClassifier.php`: implements the interface, `name()` returns `keyword`.
- `app/Providers/AppServiceProvider.php`: `$bindings` maps the interface to `KeywordClassifier`.
- `app/Services/ClassificationService.php`: depends on the interface; `classifyAndStore()` runs
  find, apply, save and history insert in `DB::transaction()`; new `history()`
  (`simplePaginate(50)`, eager-loaded request, newest first, id tie-break).
- `app/Models/RequestClassification.php` (new): `UPDATED_AT = null`, enum and float casts,
  `request()`, `ofCategory()` scope. `app/Models/CustomerRequest.php`: `classifications()`.
- `app/Http/Requests/HistoryRequest.php` (new): enum-validated `category`, `page`.
- `app/Http/Resources/ClassificationHistoryResource.php` (new): item shape from the plan.
- `app/Http/Controllers/RequestsController.php`: `history()` uses the request and resource.
- `database/migrations/2026_11_13_000000_create_request_classifications_table.php` (new).

## Changes (web, under `apps/web`)
- `src/lib/api.ts`: `CATEGORIES`, `Category`, `HistoryItem`, `HistoryPage`, new `fetchHistory`.
- `src/app/history/page.tsx`: select, table, loading/error(retry)/empty states, Previous/Next.
- `src/app/page.tsx`: classify `onSettled` also invalidates `['history']`.

## Changes (docs)
- `DECISIONS.md`: "Classification history" filled in; Task 4 table row notes 3 queries since task 5.
- `AI_USAGE.md`: checkpoint 6 (factual draft for the human to edit).

## Tests added or updated
- `tests/Feature/ClassificationHistoryTest.php` (new, 10 tests): one row per stored classify,
  append on repeat, no rows for preview/404/422, container binding, fake provider recorded,
  newest-first with same-second tie-break, item shape, category filter, 422 for `nope` and
  `page=0` with empty category returning all, pagination (51 rows, links keep `category`),
  constant 2 queries for 3 and 10 requests.
- `tests/Feature/ClassifyRequestTest.php`: query count 2 to 3 (fresh `open` request).
- `tests/Unit/ClassificationServiceTest.php`: mocks `ClassifierProvider`.
- `tests/Unit/KeywordClassifierTest.php`: is a provider, `name()` is `keyword`.
- `apps/web/src/app/history/page.test.tsx` (new, 5 tests): loading and rows, select options and
  `fetchHistory({category:'billing', page:1})`, empty state with link to `/`, error and retry,
  paging (Next disabled on last page, requests page 2).
- `apps/web/src/app/page.test.tsx`: classify invalidates `['history']` (and `['requests']`).

## Deviations from the plan
- Item-shape test uses `assertEqualsCanonicalizing` on the decoded item instead of
  `assertExactJson` (the `createdAt` value is compared to the model's `toJSON()`); same strictness.
- The new classify-invalidates-history web test was not run red against the old `page.tsx`.
- Dev DB side effect: the manual check classified one existing dev request twice, so the dev DB
  has 2 history rows and that request is `in_progress` (category sales).

## Verification
| Check | Result |
|-------|--------|
| `docker compose exec api php artisan migrate` | DONE (created `request_classifications`) |
| `docker compose exec api php artisan test` | 55 passed (162 assertions) |
| `docker compose exec web npm test` | 2 files, 10 passed |
| `docker compose exec web npm run typecheck` | no errors |
| Pint on 14 changed/new PHP files | 13 pass. `RequestsController.php` fails with `unary_operator_spaces`, `not_operator_with_successor_space`; this is pre-existing (`! $message` in `store()`, unchanged; verified failing on a stash of my changes), so not reformatted per CLAUDE.md. `RequestClassification.php` was fixed (import `Carbon`) and re-run: pass. |

Manual check (curl against :3001):
- Empty history: `{"data":[],"links":{...,"next":null},"meta":{"current_page":1,...}}`.
- Classify with `requestId` twice: `{"category":"billing","confidence":0.86,"requestId":"468484b6-..."}` then `{"category":"sales","confidence":0.8,...}` (response shape unchanged).
- `GET /requests/history`: 2 items, id 2 (sales, 0.8, provider keyword) before id 1 (billing, 0.86).
- `?category=billing`: `['billing']`.
- `?category=nope`: `{"message":"The selected category is invalid.","errors":{...}} HTTP 422`.
- Browser/UI was not opened; the page is covered by Vitest only.

Criteria: 1 to 6 checked above (history row + transaction by tests; no rows for preview/404/422
by tests; interface and fake provider by tests; endpoint by tests and curl; UI by tests; docs updated).

## Review fixes (before commit)
- `/history`: Previous/Next are disabled while the next page loads (`isPlaceholderData`), so a
  double-click can't skip a page. New test "disables paging while the next page is loading"
  fails without the fix and passes with it.
- `RequestsController.php`: ran Pint (constructor `) {}`), since this task changes the file. All
  14 changed PHP files now pass `pint --test`.
- Rerun: `php artisan test` 55 passed (162 assertions); `npm test` 11 passed; typecheck clean.

Status: PASS
