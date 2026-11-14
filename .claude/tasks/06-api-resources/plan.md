# Plan: API resources and request validation

## Problem
The response shapes are built in three different ways (see the requirement's evidence):
- a hand-built array in the controller (`classify`)
- a hand-built array in the service (`list`)
- a raw model (`show`, `store`, `updateStatus`), which comes out in snake_case

Only `history` uses a resource. `store` and `updateStatus` return 200 with `{error}` on bad input.

## Changes
1. **Turn off resource wrapping**: `JsonResource::withoutWrapping()` in
   `AppServiceProvider::boot()`. Single resources and plain collections then keep today's top-level
   shape. Paginated collections (`history`) still get `data`, `links` and `meta`, because Laravel
   always wraps when pagination info is present.
   File: `app/Providers/AppServiceProvider.php`
2. **`CustomerRequestResource`**: `id, message, status, category, confidence`, then
   `noteCount` via `whenCounted('notes')` and `latestNotePreview` via
   `whenLoaded('latestNote', fn () => $this->latestNote?->body)`, then `createdAt` and `updatedAt`
   (`toISOString()`), then `notes` via `RequestNoteResource::collection($this->whenLoaded('notes'))`.
   The order matches the current list keys. Relations that aren't loaded are left out of the output.
   File: `app/Http/Resources/CustomerRequestResource.php`
3. **`RequestNoteResource`**: `id, body, authorName, createdAt`.
   File: `app/Http/Resources/RequestNoteResource.php`
4. **`ClassificationResource`**: wraps the `ClassificationResult` value object, and the constructor
   also takes `?string $requestId`. It outputs `category` (enum value), `confidence` and `requestId`.
   File: `app/Http/Resources/ClassificationResource.php`
5. **Form Requests**:
   - `StoreCustomerRequest`: `message` is `required|string`. No max length, to keep current
     behaviour, because the column is `text`.
   - `UpdateStatusRequest`: `status` is `required` and must be in `CustomerRequest::STATUSES`.
   - Add the new `STATUSES = ['open', 'in_progress', 'resolved']` constant on the model.

   Files: `app/Http/Requests/StoreCustomerRequest.php`, `app/Http/Requests/UpdateStatusRequest.php`,
   `app/Models/CustomerRequest.php`
6. **`RequestsService`**:
   - `list()` returns `Collection<CustomerRequest>` (same query, no array building).
   - `updateStatus()` loads with `findOrFail` instead of `getById()`, so it no longer eager-loads
     notes it doesn't need, and its response has no `notes`.
   - `getById()` and `create()` stay as they are.

   File: `app/Services/RequestsService.php`
7. **Controller**: every action returns a resource and type-hints its Form Request. `store` then
   returns 201 automatically, because Laravel uses 201 when the model was just created.
   File: `app/Http/Controllers/RequestsController.php`
8. **Web types**:
   - `CreatedRequest` becomes `RequestSummary` (`RequestListItem` without the note fields, so it
     includes `category` and `confidence`).
   - `updateRequestStatus` returns `Promise<RequestSummary>`.
   - `page.tsx` only invalidates queries after these calls, so the UI doesn't change.

   File: `apps/web/src/lib/api.ts`
9. **Docs**: add a "Task 6: API resources" entry to `DECISIONS.md` and a checkpoint to `AI_USAGE.md`.

## Tests
- New file `tests/Feature/RequestsEndpointsTest.php`:
  - **show:** 200, exact keys, notes with `authorName`, no snake_case keys; unknown id gives 404.
  - **store:** 201, exact keys, status `open`, row saved; missing or array `message` gives 422 and
    no row.
  - **updateStatus:** 200, exact keys with no `notes`, value saved; missing or `done` status gives
    422 and nothing changes; unknown id gives 404.
- The existing `RequestsListTest` passes without edits. This proves the list keys, order and query
  count are unchanged.
- The existing `ClassifyRequestTest` (`assertExactJson`) and `ClassificationHistoryTest` (`data`,
  links) pass without edits. This proves classify and history are unchanged.
- Run `php artisan test`, `npm test`, `npm run typecheck`, and `pint --test` on the changed PHP files.

## Risks
- `withoutWrapping()` applies to every resource. Any future single resource would also be
  unwrapped. It is recorded in DECISIONS.md, and history is covered by tests.
- `POST /requests` changes from 200 to 201, and bad input changes from 200 to 422. The web app
  checks `res.ok`, so both still work there, but any outside client relying on the old behaviour
  would be affected.
- `createdAt` format: the list already uses `toISOString()`, so it stays the same. `show`, `store`
  and `update` change format, from Eloquent's default serialisation to the same ISO string.

Approved by human, 2026-11-14.

## Revision 2: status enum with labels (asked for by the human, 2026-11-14)
1. Replace `CustomerRequest::STATUSES` with `App\Enums\RequestStatus` (`Open`, `InProgress`,
   `Resolved`) and a `label()` method (`Open`, `In progress`, `Resolved`). Cast `status` to the
   enum on the model. The dev DB holds only valid values (636 / 324 / 241), so the cast is safe.
2. Use the enum in `UpdateStatusRequest` (`Rule::enum`, plus a `status()` accessor),
   `RequestsService::create/updateStatus`, `applyClassification()` and the seeder.
3. `CustomerRequestResource` adds `statusLabel` after `status`. `status` stays the raw value.
   `ClassificationHistoryResource` outputs `requestStatus` as `->value` (same JSON).
4. Web: add `statusLabel` to `RequestListItem` and a `STATUS_LABELS` map in `api.ts`. The status
   select on `/` shows the labels; the option values stay raw.
5. Tests: unit test for `label()` on every case; list and endpoint key lists include
   `statusLabel`; assertions on the model compare enums; a web test checks the option labels.

The human chose "API + UI" for where the label is used, which approves this revision.

Status: PASS
