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
  rows); revisit with pagination. The API still answers some errors with 200 + `{error}`; the
  refetch makes the UI self-correct, and the proper fix is task 4.

## Classification history

What is the smallest useful release? Describe what you would store, when you would write it,
how staff would filter it, and what the provider boundary would own. Say what you shipped.

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
