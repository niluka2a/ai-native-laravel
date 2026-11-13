# Plan: 05-classification-history

There is no `requirement.md` for this task yet. The input is the thin-slice scope agreed with the
human (README task 5): record new classifications, serve them through
`GET /requests/history?category=`, make `/history` useful, and put the keyword classifier behind
a provider interface. The acceptance criteria are restated at the end of this plan.

## Problem (evidence, 2026-11-13, branch `refactor/task-4-classification-structure`)
- `apps/api/app/Http/Controllers/RequestsController.php:23-29`: `history()` returns
  `{"items":[],"message":"Classification history is not implemented yet."}`. Live check:
  `curl :3001/requests/history?category=billing` returns that body, and `?category=nope` returns
  **200**, so there is no validation.
- `apps/api/app/Services/ClassificationService.php:62-73`: `classifyAndStore()` overwrites
  `category` and `confidence` on `customer_requests`. Earlier results are lost, and nothing
  records which classifier produced a result.
- `ClassificationService.php:21`: the service depends on the concrete `KeywordClassifier`.
  `KeywordClassifier.php:8-11` says a provider interface is expected. Task 4 deferred it here
  (DECISIONS.md, Task 4).
- `apps/web/src/app/history/page.tsx`: a free-text category input (it accepts any string) and
  a raw `JSON.stringify` dump. There is no table and no empty state.
- `apps/web/src/app/page.tsx:40-45`: `classifyMutation` invalidates only `['requests']`, so a
  cached `['history', ...]` query stays stale for up to 10 s after a classify.
- `customer_requests` has no customer or subject column (initial migration), only `message`.
  The history UI therefore shows the request message (truncated) and status.

## Decisions (recommended; see Open questions)
1. **Write only when a result is stored**, meaning classify with a `requestId`, in the same
   `DB::transaction` as `applyClassification()`. Calls without a `requestId` are previews: they
   change nothing, have no request to link to or filter by, and recording them would need a
   nullable FK plus a copy of free-text (possibly personal) messages. The web app always sends
   `requestId`. Classify calls that fail (422, 404) write nothing.
2. **No message snapshot.** The message is read via the `request` relation. Messages cannot be
   edited (there is no update endpoint), so a snapshot would only duplicate personal data and
   grow the table. If messages become editable, revisit this (store a hash or snapshot then).
3. **Provider name on every row** (`provider`, for example `keyword`). It comes from the
   interface's `name()`, so a future model-backed provider shows up as a different value.
   Confidence is stored **after** the policy (softening and threshold), the same value written
   to the request, so history matches what staff saw.
4. **Pagination: `simplePaginate(50)`**, through an API Resource. It is built in, needs no
   count query, and keeps older rows reachable. A fixed limit would quietly hide them.
5. **Auto-increment `id`** on the history table (other tables use UUIDs). The table is
   append-only, and `ORDER BY created_at DESC, id DESC` then gives a deterministic newest-first
   order even when several rows share a second (`created_at` has 1 s precision).
6. **Enum cast** `category => Category::class` on the new model. That is safe here, unlike on
   `CustomerRequest`, because rows are only ever written from a `ClassificationResult`.

## Migration schema
`apps/api/database/migrations/2026_11_13_000000_create_request_classifications_table.php`
```
request_classifications
  id           bigint unsigned auto-increment PK      ($table->id())
  request_id   uuid FK -> customer_requests.id, cascadeOnDelete   (foreignUuid()->constrained())
  category     varchar(32)
  confidence   double
  provider     varchar(64)
  created_at   timestamp useCurrent                    (no updated_at)
  index (category, created_at)                          filtered list
  index (created_at)                                    unfiltered list
```
`down()` drops the table. No backfill. `apps/api/docker/start.sh` already runs
`migrate --force` on container start, and CI runs `migrate`.

## Changes (paths under `apps/api` unless noted)
1. **Provider interface**: `app/Contracts/ClassifierProvider.php` with
   `classify(string $message): ClassificationResult` and `name(): string`.
   `app/Services/KeywordClassifier.php` `implements ClassifierProvider`, and `name()` returns
   `'keyword'` (rules unchanged; update the docblock).
2. **Binding**: in `app/Providers/AppServiceProvider.php`, add
   `public array $bindings = [ClassifierProvider::class => KeywordClassifier::class];`.
3. **Service depends on the interface**: in `app/Services/ClassificationService.php`, the
   constructor takes `ClassifierProvider`. `classifyAndStore()` wraps the find, apply, save and
   history write in `DB::transaction()`, and writes the history row with
   `$request->classifications()->create([... 'provider' => $this->classifier->name()])`.
   `classify()` stays pure. Add
   `history(?Category $category, int $perPage = 50): Paginator`:
   `RequestClassification::query()->with('request:id,message,status')->ofCategory($category)
   ->latest()->orderByDesc('id')->simplePaginate($perPage)->withQueryString()`.
4. **Model**: `app/Models/RequestClassification.php` (`UPDATED_AT = null`, fillable, casts
   `confidence => float` and `category => Category::class`, `request(): BelongsTo`, and a local
   scope `ofCategory(?Category)` that does nothing when the value is null).
   `app/Models/CustomerRequest.php` gets `classifications(): HasMany`.
5. **Validation**: `app/Http/Requests/HistoryRequest.php` with
   `category: nullable, Rule::enum(Category::class)` and `page: nullable|integer|min:1`.
   Add a helper `category(): ?Category` that uses `$this->enum('category', Category::class)`.
6. **Resource**: `app/Http/Resources/ClassificationHistoryResource.php`. Its item shape:
   ```json
   { "id": 12, "requestId": "uuid", "requestMessage": "...", "requestStatus": "in_progress",
     "category": "billing", "confidence": 0.86, "provider": "keyword",
     "createdAt": "2026-11-13T10:00:00.000000Z" }
   ```
   The paginated response is Laravel's standard simple-paginator JSON:
   `{ data: [...], links: {first, last: null, prev, next}, meta: {current_page, from, to, per_page, path} }`.
7. **Controller**: `RequestsController::history(HistoryRequest $request)` returns
   `ClassificationHistoryResource::collection($this->classification->history($request->category()))`.
   `classify()` is unchanged, so the response shape is preserved.
8. **Web API client**: in `apps/web/src/lib/api.ts`, add
   `export const CATEGORIES = ['support','sales','billing','unknown'] as const`,
   `type Category`, `ClassifyResult.category: Category`, the `HistoryItem` and `HistoryPage`
   types, and `fetchHistory({ category, page }): Promise<HistoryPage>`. It builds the query with
   `URLSearchParams` and throws on `!res.ok` as before.
9. **History page**: `apps/web/src/app/history/page.tsx`
   - A `<select>` with an "All categories" option plus `CATEGORIES`. Changing the category resets
     the page to 1.
   - `useQuery({ queryKey: ['history', category, page], placeholderData: keepPreviousData })`.
   - Loading, error (with a retry button) and empty ("No classifications yet" or "No
     classifications for <category>") states.
   - A table with the columns When, Request (truncated message, with status), Category,
     Confidence (%), Provider, and Previous/Next buttons driven by `links.prev` and `links.next`.
   - Replace the placeholder "TODO" description text.
10. **Classify invalidates history**: in `apps/web/src/app/page.tsx`, `classifyMutation.onSettled`
    returns `Promise.all([invalidate ['requests'], invalidate ['history']])`. Status and create
    don't change history, so they are unchanged.
11. **Docs**: in `DECISIONS.md`, fill in "Classification history" (outline below) and add a row
    to the Task 4 table, or a note, that classify with `requestId` now runs 3 queries (it was 2)
    because of the history insert. In `AI_USAGE.md`, add checkpoint 6.

## Tests
API (`docker compose exec api php artisan test`):
- **New `tests/Feature/ClassificationHistoryTest.php`** (RefreshDatabase):
  - Classify with `requestId` writes exactly 1 row: the right `request_id`, `category`,
    `confidence` (the softened value for a short message, for example `"invoice"` gives 0.71),
    and `provider = 'keyword'`. The classify response is still exactly
    `{category, confidence, requestId}`.
  - Classifying twice gives 2 rows, so history is appended, not overwritten.
  - No `requestId`: 0 rows. Unknown `requestId` (404) and 422 inputs: 0 rows.
  - Swappable provider: `$this->app->instance(ClassifierProvider::class, $fake)` with
    `name() = 'fake'`. Classify records `provider = 'fake'` and the fake's category.
  - `GET /requests/history`: newest first (rows created at different times with `travelTo`,
    plus a same-second pair ordered by id). Item shape via `assertExactJson` on one item.
  - `?category=billing` returns only billing rows. `?category=nope` gives **422** JSON (no Accept
    header), and `?category=` (empty) returns all rows.
  - Pagination: 51 rows give 50 items, `links.next` keeps `category=` in the query string, and
    page 2 has 1 item.
  - Query count: `GET /requests/history` with rows on several requests runs exactly **2**
    queries (page plus eager-loaded requests), the same for 3 and for 10 requests.
- **`tests/Feature/ClassifyRequestTest.php`**: the query-count assertion goes from 2 to **3**
  (select, update, history insert). The other characterisation tests stay as they are.
- **`tests/Unit/ClassificationServiceTest.php`**: mock `ClassifierProvider` instead of
  `KeywordClassifier`.
- **`tests/Unit/KeywordClassifierTest.php`**: assert it is a `ClassifierProvider` and that
  `name()` returns `'keyword'`. Optionally, a test that the container resolves
  `ClassifierProvider` to `KeywordClassifier` (a feature test, because it needs the app).

Web (`docker compose exec web npm test`, `npm run typecheck`):
- **New `apps/web/src/app/history/page.test.tsx`** (mocks `fetchHistory`):
  - It renders rows in a table (message, category, percentage, provider).
  - The select lists "All" plus the 4 categories. Choosing `billing` calls
    `fetchHistory({ category: 'billing', page: 1 })`.
  - The loading text, the empty state (`data: []`), and an error state when the fetch rejects.
  - Next is disabled when `links.next` is null. Clicking it requests page 2.
- **`apps/web/src/app/page.test.tsx`**: a new test checks that after classify, `invalidateQueries`
  is called with `{ queryKey: ['history'] }` (spy on the test QueryClient). Add `fetchHistory`
  to the `vi.mock` list only if needed.

Style: `docker compose exec api ./vendor/bin/pint --test` on every PHP file that was changed or
added (list them in `build.md`).

Manual check (record in `build.md`): restart the api so the migration runs, classify one row on
`/`, then open `/history`. The new row is at the top, the filter works, and `?category=nope` is
rejected with 422 via curl.

## DECISIONS.md "Classification history" outline
- **Smallest useful release:** an append-only log of stored classifications that staff can
  filter by category, newest first.
- **What is stored:** request id, category, confidence (after the policy), provider, created_at.
  No message snapshot, and why (no edits, privacy, size).
- **When it is written:** only when classify stores a result (`requestId` given), in the same
  transaction as the request update. Previews and failed calls are not written. No backfill, and
  why (old rows have no provider or timestamp of classification).
- **Filtering:** `?category=` validated against `Category` (422 otherwise), newest first,
  `simplePaginate(50)`, 2 queries per page, indexes.
- **Provider boundary:** `ClassifierProvider` (`classify`, `name`) owns raw classification. The
  policy (softening, threshold), persistence and history stay in `ClassificationService`. The
  binding is in `AppServiceProvider`, so a swap is one line or config. Keyword is the default
  and is local and free.
- **Shipped vs not done:** not done are filters by date, provider or request, filtering
  previews, who triggered a classification (there is no auth), retention policy, the request
  detail view of history, config-driven provider selection, and model-backed provider
  failure handling. The query count change on classify goes into the behaviour table.

## Risks
- **Classify query count changes from 2 to 3**, and the request update and history insert are
  now atomic. This is intended. The existing test is updated and the change is documented.
- **Migration on an existing dev DB:** this is additive only, and `start.sh` migrates on start.
  Anyone running an api container that is already up must run `php artisan migrate`.
- **`simplePaginate` changes the `/history` JSON shape** away from the stub's `{items}`. Only
  the history page reads it, and that page is rewritten here.
- **Enum cast on history rows:** a hand-inserted row with an unknown category would throw on
  read. Accepted, because rows are only written by the service.
- **Out of scope:** backfill, editing or deleting history, auth or actor, history on
  `GET /requests/{id}`, a model-backed provider, ESLint.

## Open questions for the human
1. Should classify calls **without** `requestId` also be recorded? The recommendation is **no**
   (reasons in Decision 1).
2. Use **`simplePaginate(50)` with Previous/Next**, or a fixed "latest 100" with no paging
   (slightly less code, but older rows can't be reached)? The recommendation is to paginate.
3. Is it OK that classify with `requestId` goes from 2 to 3 queries, inside a transaction?

## Acceptance criteria
1. A stored classification writes one history row (request, category, confidence, provider,
   time) atomically with the request update. Classify without `requestId` behaves as decided.
2. `KeywordClassifier` implements `ClassifierProvider`, which is bound in the container.
   `ClassificationService` and its tests depend on the interface.
3. `GET /requests/history?category=` validates the category (422), returns newest first,
   paginates, and runs a constant number of queries.
4. `/history` has a category select, a table, and loading, empty and error states. Classify on
   `/` invalidates `['history']`.
5. API tests, web tests and typecheck pass, and Pint is clean on the changed PHP files.
6. DECISIONS.md and AI_USAGE.md are updated.

Status: READY FOR REVIEW

## Approval
Reviewer: niluka2a
Decision: APPROVED
Notes:
- Open questions answered as recommended: no rows for classify without `requestId`;
  `simplePaginate(50)` with Previous/Next; classify with `requestId` goes from 2 to 3 queries.
- Checked against README task 5 and the code on `origin/develop` (2a0b1a6): the plan covers every
  item (records new classifications, `GET /requests/history` with a category filter, a useful
  `/history`, a provider interface, keyword classifier stays the local default).
- History starts empty on a fresh database (no backfill). Don't seed fake history rows; the empty
  state should tell staff to classify a request on `/`.
- The 3-query count holds when the classification changes the row. If it doesn't (same category
  and confidence), Eloquent skips the UPDATE and it is 2. Keep the count test on a fresh `open`
  request and mention this in DECISIONS.md.
- AI_USAGE.md: add checkpoint 6 now. The README asks for two checkpoints; trimming the table is
  left for the later DECISIONS/AI_USAGE cleanup.

Branch: `feat/task-5-classification-history`, from `origin/develop` (2a0b1a6).

Status: PASS (approved)
