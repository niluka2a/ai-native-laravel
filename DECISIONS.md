# Decisions

## Priorities and trade-offs

What did you tackle first, and why? Record any deliberate changes to existing behaviour.

**Task 1: list performance (`GET /requests`).** I fixed the N+1 first because it is the
largest measurable cost and it grows linearly with the data: 1,201 queries per call on the
seeded data (1,200 requests, 4,800 notes), and every note row was loaded just to count it and
read one body.

- **Approach:** `RequestsService::list()` uses `withCount('notes')` plus the eager-loaded
  `CustomerRequest::latestNote()` relation (`hasOne(...)->latestOfMany('created_at')`): 2
  queries, constant. I chose it over a hand-written correlated subquery (Revision 1, a single
  query) because it is idiomatic Eloquent, reusable, and follows the rule to prefer the
  framework's built-in way. Trade-offs I accepted: one more query, the eager load binds every
  request id (placeholder limit around 65k requests; the endpoint is unpaginated anyway), and
  the generated SQL uses derived tables.
- **Tie-break (deliberate change):** the latest note is chosen by `created_at DESC, id DESC`:
  `latestOfMany('created_at')` adds `MAX(id)` as the last aggregate, and MariaDB's native `UUID`
  type orders `MAX` the same way as `ORDER BY id DESC` (0 mismatches over the 1,200 dev
  requests). Before, ties on `created_at` had an undefined order. Ids are random UUIDs, so the rule is
  deterministic but not necessarily chronological. The seed data has no ties, so output is
  unchanged.
- **No index:** I did not add `(request_id, created_at)` on `request_notes`. The FK index
  already narrows each subquery to about 4 rows; the remaining filesort costs under 1 ms in
  total, which does not justify a migration. Revisit if notes per request grow a lot.
- **Out of scope:** pagination and response-shape changes (the frontend reads the whole array).

**Task 2: UI freshness (status change and classify).** The API saved both actions correctly,
but the table on `/` kept the old values because only `createMutation` invalidated the
`['requests']` query (`staleTime` is 10 s, no refetch on focus), and the status `<select>` is
controlled by the cached row, so it snapped back.

- **Approach:** both mutations call `invalidateQueries` in `onSettled` and return the promise,
  so the table is refetched after success and after failure and always shows what the server
  stored. The server stays the source of truth.
- **Pending value in the UI, not in the cache (Revision 2):** the status `<select>` shows
  `statusMutation.variables.status` while that row's mutation is pending (TanStack Query v5
  "optimistic UI via variables"), otherwise the cached `row.status`. There is no `onMutate` or
  rollback code: the query cache is never edited, and the refetch in `onSettled` decides what
  is shown. On failure the select keeps the attempted value, disabled, until the refetch lands
  (tens of ms), then shows the server's value. Revision 1 patched the cache and rolled back;
  I replaced it because it was more code and let overlapping actions flicker.
- **All row controls are disabled while any action is pending:** `isBusy` is
  `statusMutation.isPending || classifyMutation.isPending`, and `isPending` lasts until the
  refetch arrives (the `onSettled` promise is returned). Only one action is in flight at a time,
  which prevents conflicting edits. Per-row disabling would need `useMutationState`, which I
  left out. Classify is still not optimistic: category, confidence and the `open` to
  `in_progress` rule belong to the server.
- **Response payloads are not written into the cache:** `PATCH /requests/{id}/status` returns
  the raw snake_case model and `POST /requests/classify` returns only
  `{category, confidence, requestId}`, neither matches `RequestListItem`. So
  `updateRequestStatus` is typed `Promise<unknown>` (the UI never reads it) and
  `classifyMessage` gets a real `ClassifyResult` type.
- **Test runner:** added Vitest + React Testing Library + jsdom as dev dependencies (free,
  local) with `npm test` and a `Web tests` CI step, so the behaviour is covered by tests that
  fail on the old code. Cost: a few dev dependencies and a slightly longer CI run.
- **Accepted trade-offs:** each action refetches the whole list (2 queries, about 50 ms, 1,200
  rows); revisit with pagination. The API still answered some errors with 200 + `{error}`; the
  refetch makes the UI self-correct. Task 4 fixed this for `classify` (422); `store` and
  `updateStatus` still do it.

## Task 4: classification structure

`RequestsController::classify` mixed validation, classification policy and persistence. Each now
has its usual home:

- `ClassifyRequest` (FormRequest): `message` is `required|string|max:2000`, `requestId` is
  `nullable|uuid`. Laravel's global `TrimStrings` and `ConvertEmptyStringsToNull` middleware
  already trim input, so the old `trim()` calls were redundant.
- `ClassificationService`: the softening and unknown-threshold rules, as named constants.
  `classify()` is pure; `classifyAndStore()` also stores the result when a `requestId` is given.
- `CustomerRequest::applyClassification()`: sets category and confidence and moves `open` to
  `in_progress`.
- `KeywordClassifier` returns a `ClassificationResult` value object with a backed `Category`
  enum. Regexes and confidences are unchanged.
- `bootstrap/app.php`: `shouldRenderJsonWhen(fn () => true)`. Without it a failed FormRequest
  redirects (302), because the web client sends no `Accept: application/json` header. The app
  is API-only, so all errors being JSON is intended.

Decisions the human confirmed: validation errors become 422 (the frontend already throws on
`!res.ok`); no enum cast on `CustomerRequest::category` (a row outside the enum would throw on
read and break `GET /requests`); the provider interface is left for task 5, so tests stub the
concrete `KeywordClassifier`.

| Input | Before | After |
|---|---|---|
| invalid or missing message, > 2000 chars | 200 `{error}` | 422 `{message, errors}` |
| `"0"` | 200 `{error}` | 200 unknown 0.4 |
| `requestId` malformed | 404 | 422 |
| `requestId` array | 500 | 422 |
| 404 body text | `Request X not found` | Laravel's default model-not-found message |
| queries with `requestId` | 3 | 2 (3 since task 5, which adds the history insert) |

Success responses are unchanged (`{category, confidence, requestId}`), and characterisation
tests written against the old code pass on both versions.

## Classification history

Before this change, a request only kept its latest classification. Classifying it again
overwrote the category and confidence, so staff couldn't see what had changed or which
classifier decided. The smallest useful release is an append-only log of stored classifications
that staff can filter by category, newest first. I shipped that as a thin slice, plus a provider
interface so the keyword classifier can be swapped later.

**What I store.** One row per stored classification in `request_classifications`: the request,
category, confidence, provider name and time. The confidence is the final value after the
softening and threshold rules, so the history shows the same number staff saw on the request. I
don't copy the message text. Messages can't be edited, so a copy would only duplicate possibly
personal text. If messages become editable, I'd revisit this.

**When I write it.** Only when classify saves a result to a request (a `requestId` is given), in
the same transaction as the request update, so the two can't disagree. Previews without a
`requestId` and failed calls (404, 422) write nothing, because a preview changes nothing and has
no request to link to. I didn't backfill, because old classifications have no provider or time.
The history starts empty, and the page tells staff to classify a request on `/`.

**How staff filter it.** `/history` has a category dropdown and shows the newest first, 50 per
page with Previous/Next. The API behind it, `GET /requests/history?category=`, rejects unknown
categories with a 422 and treats an empty value as "all". Each page takes 2 queries (the rows,
then their requests eager-loaded), with indexes on `(category, created_at)` and `(created_at)`.
I used an auto-increment id so rows created in the same second still sort reliably.

**Provider boundary.** `ClassifierProvider` has two methods, `classify()` and `name()`. It only
produces the raw result; the confidence rules, saving and history stay in
`ClassificationService`. `KeywordClassifier` is bound as the default in `AppServiceProvider`, so
the app still runs locally with no API key, and switching provider is a one-line change. A test
binds a fake provider and checks that its name is recorded.

**Trade-off I accepted.** Classify with a `requestId` now runs 3 queries instead of 2 because of
the history insert. It stays at 2 when the result doesn't change the request, because Eloquent
skips an update with no changes.

**Not done yet:** filtering by date, provider or request; recording previews; who classified
(there is no auth); a retention policy; history on a request's detail view; choosing the
provider from config; and building a model-backed provider (the plan is below).

## Future model-backed classifier

This is a plan only. Nothing here is built yet.

**Deployment order**

1. Database first. Any new history columns, such as the model version or response time, are
   added as nullable, so the current code keeps working.
2. Ship the model provider switched off. `KeywordClassifier` stays the default binding, and a
   config value chooses the provider.
3. Turn it on slowly. First in shadow mode, where the model runs but its answers are only logged,
   then for a small share of requests, then for all of them. The `provider` column in the
   history shows which one does better.
4. Frontend last, if at all. The classify response keeps the same shape.

**Failure handling**

1. The keyword classifier is the backup. On a timeout, an error or an invalid answer the request
   is classified with keywords. If the model keeps failing, a circuit breaker skips it for a few
   minutes.
2. The model's answer is checked. Only a known `Category` and a confidence between 0 and 1 are
   accepted; anything else becomes `unknown`. The existing confidence rules still apply.
3. The history stays honest. A fallback is stored with `provider = keyword`, not the model.
4. The database never waits for the model. `classifyAndStore()` already classifies before it
   opens the transaction, so a slow model can't hold locks.
5. Rollback is one config change back to `keyword`. No migration rollback is needed because the
   new columns are nullable.

## Assumptions

- There is no authentication, so history doesn't record who classified a request.
- Customer messages can't be edited, so history doesn't copy the message text.
- The frontend reads the whole `GET /requests` list, so I kept it unpaginated and kept its
  response shape.
- The app is API-only, so every error, including validation, is returned as JSON.
- The seed data has no notes with the same `created_at`, so the new tie-break doesn't change
  current output.
- One staff action at a time on `/` is acceptable, so all row controls are disabled while an
  action is pending.

## Verification and remaining work

What did you verify and how? Record any remaining gaps, including CI checks you could not run,
and what you would tackle next.

**Task 1 verification (same seeded dev DB, same machine):**

| | Before | After |
| --- | --- | --- |
| Queries per `GET /requests` | 1,201 | 2 |
| Wall time (curl, 5 runs) | 0.32 to 0.37 s | 0.075 to 0.091 s |
| Response SHA-256 prefix | `850c2e52329fcf59` | `850c2e52329fcf59` (byte-identical) |

`tests/Feature/RequestsListTest.php` covers counts, latest preview, the empty case, ordering and
keys, the tie-break and a constant query count. Remaining gap: the endpoint is still
unpaginated, so payload size grows with the number of requests.

**CI migrate fix:** the `Migrate` step overrode `DB_DATABASE` to `request_desk_app`, which no step
creates or grants, so it failed with `1044 Access denied`. I removed the override so migrations run
against `request_desk`, which the MariaDB service creates and grants and which `.env.example` and
docker-compose already use. I did not add `request_desk_app` to `init.sql`, because nothing else
uses that database.

**CI test database fix:** with the migrate fix, CI went green but the API tests ran against
`request_desk`, not `request_desk_test`. The job-level `env:` sets `DB_DATABASE=request_desk` as a
real environment variable, Laravel reads `$_SERVER` first, and phpunit's `<env force="true">` does
not set `$_SERVER`, so the real variable won. As a result `RefreshDatabase` wiped the app database,
the "Prepare test database" step was unused, and `Migrate` only printed "Nothing to migrate"
because the tests had already migrated it. I changed that line in `phpunit.xml` to
`<server ... force="true">` and added `tests/Feature/TestDatabaseTest.php`, which asserts the
connection uses `request_desk_test`. I kept the CI env block, so the workflow is unchanged.

**Task 3 verification (clean checkout, reproduced locally):** I could not run GitHub Actions from
here (no `gh` CLI), so I reproduced each step on a fresh `git clone` (no `node_modules`, no
`.next`): `node:22` for the web steps, the api image (PHP 8.3) for the API steps, and a new
`mariadb:11.4` container configured like the CI service, with the job's `DB_*` values set as
process env.

| Step | Result |
| --- | --- |
| Check submission artifacts, install, prepare environment | pass |
| Lint | exits 0 but runs nothing: `apps/web` has no `lint` script or ESLint config, so `--if-present` skips it (true since the original repo) |
| Typecheck, Web tests (4), Build | pass |
| Prepare test database, Test | pass: 9 tests, tables created in `request_desk_test` only. Before the fix, the new test failed and tables landed in `request_desk` |
| Migrate | runs `initial_schema` on the empty `request_desk`. The old `request_desk_app` override reproduces `1044 Access denied` |

Not verified: the run on GitHub's `ubuntu-latest` runner itself (service networking,
`setup-php`, npm cache). Next: add ESLint so the Lint step actually checks something.
