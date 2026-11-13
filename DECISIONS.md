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
