# Build: 06-api-resources

## Changes (API, under `apps/api`)
- `app/Http/Resources/CustomerRequestResource.php` (new): the list keys in the same order;
  `noteCount` uses `whenCounted`, `latestNotePreview` uses `whenLoaded('latestNote')`, and
  `notes` uses `whenLoaded`.
- `app/Http/Resources/RequestNoteResource.php` (new): `id, body, authorName, createdAt`.
- `app/Http/Resources/ClassificationResource.php` (new): wraps `ClassificationResult` plus `requestId`.
- `app/Http/Requests/StoreCustomerRequest.php` (new): `message` is `required|string`.
- `app/Http/Requests/UpdateStatusRequest.php` (new): `status` is `required|string`, in `CustomerRequest::STATUSES`.
- `app/Models/CustomerRequest.php`: new `STATUSES` constant.
- `app/Providers/AppServiceProvider.php`: `JsonResource::withoutWrapping()` in `boot()`.
- `app/Services/RequestsService.php`: `list()` returns `Collection<CustomerRequest>`;
  `updateStatus()` uses `findOrFail` (no eager-loaded notes).
- `app/Http/Controllers/RequestsController.php`: every action returns a resource; store and
  update type-hint their Form Requests; the hand-written checks are removed.

## Changes (web, under `apps/web`)
- `src/lib/api.ts`: `CreatedRequest` is replaced by `RequestSummary` (`RequestListItem` without
  the note fields); `updateRequestStatus` returns `Promise<RequestSummary>`.
- `src/app/page.test.tsx`: the status mocks are typed as `RequestSummary` and resolve with a real
  row instead of `{}`.

## Changes (docs)
- `DECISIONS.md`: new "Task 6: API resources" section (before/after table); the task 2 note on
  `{error}` responses now says task 6 fixed them.
- `AI_USAGE.md`: checkpoint 7.

## Tests added or updated
- `tests/Feature/RequestsEndpointsTest.php` (new, 11 tests):
  - **show:** exact keys plus `notes` with `authorName`; unknown id gives 404.
  - **store:** 201 with exact keys and `open`; missing, empty or array `message` gives 422 and no row.
  - **update:** exact keys with no `notes`, value saved; missing, `done` or array status gives
    422 and nothing changes; unknown id gives 404.
- These existing tests passed without edits: `RequestsListTest` (keys, order, query count),
  `ClassifyRequestTest` (`assertExactJson`) and `ClassificationHistoryTest` (`data`, links).

## Deviations from the plan
- `page.test.tsx` had to change: with the real return type, `deferred<unknown>` and
  `resolve({})` no longer typecheck. Test-only change; the behaviour under test is the same.

## Verification (2026-11-14)
- `docker compose exec api php artisan test`: **66 passed** (204 assertions).
- `docker compose exec api ./vendor/bin/pint --test <10 changed PHP files>`: pass.
- `docker compose exec web npm run typecheck`: no errors.
- `docker compose exec web npm test`: **11 passed**.
- curl against the dev stack (read-only or rejected calls only):
  - `GET /requests`: 1,201 rows, keys unchanged.
  - `GET /requests/{id}`: camelCase with `notes`.
  - `POST /requests {}`: 422.
  - `PATCH .../status {"status":"done"}`: 422.
  - classify gives `{"category":"billing","confidence":0.86,"requestId":null}`.
  - history keys are `data, links, meta`.

## Revision 2: status enum with labels
- `app/Enums/RequestStatus.php` (new): `Open`, `InProgress`, `Resolved` with `label()`.
- `CustomerRequest`: `status` cast to the enum; `STATUSES` removed; `applyClassification()` compares enums.
- `UpdateStatusRequest`: `Rule::enum(RequestStatus::class)` and a `status()` accessor;
  `RequestsService::updateStatus()` takes the enum; `create()` uses `RequestStatus::Open`.
- `CustomerRequestResource`: `statusLabel` after `status`. `ClassificationHistoryResource`:
  `requestStatus` is `->value` (same JSON).
- `database/seeders/DatabaseSeeder.php`: uses the enum values. Pint also added one missing blank
  line between constants. That issue was already on `develop`, and it is in a file this task changes.
- Web: `statusLabel` on `RequestListItem`, `STATUS_LABELS` in `api.ts`, and the select shows labels.
  The page test mock now spreads the real module so `STATUS_LABELS` is available.
- Tests:
  - new `tests/Unit/RequestStatusTest.php`
  - list and endpoint key lists include `statusLabel`, and the endpoint tests assert the labels
  - `ClassifyRequestTest` compares enums
  - new web test: the option values and labels, and that the raw value is sent

## Verification, revision 2 (2026-11-14)
- `php artisan test`: **67 passed** (207 assertions).
- `npm test`: **12 passed**; `npm run typecheck`: no errors.
- `pint --test` on the 12 changed PHP files: pass.
- `php -l` on the seeder: pass. Not run, because it would replace the dev data.
- curl:
  - `GET /requests` keys include `statusLabel`; pairs are open/Open 636,
    in_progress/In progress 324, resolved/Resolved 241.
  - `PATCH` with `done` gives 422.

Status: PASS
