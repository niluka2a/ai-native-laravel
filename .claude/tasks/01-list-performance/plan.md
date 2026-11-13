# Plan: 01-list-performance (Revision 2)

## Revision note
Revision 1 was approved, built and committed (`73a14c2`). It used `withCount('notes')` plus a
correlated `addSelect` subquery, which ran 1 query. The human has now asked to replace the
subquery with an Eloquent relationship, `CustomerRequest::latestNote()` using
`latestOfMany('created_at')`, loaded eagerly. Reasons: it is idiomatic Laravel, the relation can
be reused, and the new CLAUDE.md rule says "prefer the framework's built-in way over
hand-written SQL". The human accepts these trade-offs:
- 2 queries instead of 1. This is still constant and still ≤ 3.
- The eager load binds every request id, so it would hit the placeholder limit at about 65k
  requests. This is acceptable because the endpoint is unpaginated anyway.
- The generated SQL uses derived tables.

The approval of Revision 1 does not cover this revision. **It needs a fresh human approval.**

## Problem
The original N+1 is fixed: 1,201 queries before, 1 now, as recorded in `requirement.md` and
`build.md`. The current fix is hand-written SQL in `RequestsService::list()`
(`apps/api/app/Services/RequestsService.php:16-25`, the `addSelect` subquery). The new CLAUDE.md
rules ask for Eloquent relationships and eager loading instead. The response must stay
byte-identical.

## Checked facts (Revision 2)
- **Vendor:** Laravel `13.40.0`. In `Relations/Concerns/CanBeOneOfMany.php::ofMany()` (lines
  85-94), `latestOfMany('created_at')` becomes `['created_at' => 'MAX', 'id' => 'MAX']`. The
  primary key is always added as the last aggregate. So the relation picks the row with
  MAX(created_at), then MAX(id) among those rows. That is the tested rule
  `created_at DESC, id DESC`.
- **The id type is MariaDB's native `UUID`, not `char(36)`.** This holds for both `request_desk`
  and `request_desk_test`, on MariaDB 11.4.13. `MAX()` and `ORDER BY ... DESC` use the same type
  comparison, so they always agree. Read-only evidence:
  - Per request in the dev DB: `MAX(id)` vs `ORDER BY id DESC LIMIT 1` gave **0 mismatches**
    across 1,200 requests.
  - The test fixture ids, cast to UUID: `MAX(...0001, ...0002)` = `...0002`, and the same
    values with `ORDER BY DESC` also give `...0002`. So
    `test_notes_with_the_same_created_at_are_broken_by_greater_id` stays valid.
  - For UUIDv7, MAX picks the later timestamp. `HasUuids::newUniqueId()` returns
    `Str::uuid7()`, so for notes created through the app the id tie-break is also roughly
    chronological. Seeded notes use v4 ids: they are deterministic but not chronological, as
    already documented.
- **Generated SQL:** probed read-only on the dev DB with a temporary dynamic relation.
  - Query 1: `select customer_requests.*, (select count(*) ...) as notes_count ... order by created_at desc`.
    0 bindings, about 3-5 ms.
  - Query 2: `select request_notes.* from request_notes inner join (select MAX(id) ..., min(created_at) ... inner join (select MAX(created_at) ... where request_id in (?…1,200) group by request_id) ...) ...`.
    1,200 bindings, about 8-9 ms.
  - `get()` takes about 33-45 ms in PHP. It returned 1,200 requests, each with a latest note.
    Seed data has 0 `(request_id, created_at)` ties.
- **Column constraint (`latestNote:id,request_id,body`):** it works. The join is built inside
  the relation and does not depend on the selected columns. It is still 2 queries, but only
  saves about 1 ms because the only dropped columns are `author_name` and `created_at`.
  `request_id` must be in the list: without it Eloquent cannot match notes to requests, and
  every preview silently becomes `null`. **Decision: do not constrain columns.** The gain is
  negligible, and leaving them out removes that silent failure mode.
- If there are 0 requests, Eloquent skips the eager query, so the endpoint runs 1 query. Every
  case with at least 1 request runs 2.

## Changes
1. Add the relationship in `apps/api/app/Models/CustomerRequest.php`. Add
   `use Illuminate\Database\Eloquent\Relations\HasOne;` and this method:
   ```php
   public function latestNote(): HasOne
   {
       return $this->hasOne(RequestNote::class, 'request_id')->latestOfMany('created_at');
   }
   ```
   Place it after `notes()`, in the same style.
2. Change `list()` in `apps/api/app/Services/RequestsService.php`. Replace the `addSelect`
   block with `->with('latestNote')`. Keep `withCount('notes')` and the existing `orderByDesc`.
   Map `'latestNotePreview' => $row->latestNote?->body`. Keep the same keys in the same order,
   the `(int)` cast and the docblock. Remove `use App\Models\RequestNote;` if it is no longer
   used, because Pint's `no_unused_imports` rule would flag it. Don't change other methods.
3. Update the tests in `apps/api/tests/Feature/RequestsListTest.php`:
   - In the query-count test, keep `assertSame($small, $large)` and
     `assertLessThanOrEqual(3, $large)`. Add `assertSame(2, $large)` to document the count.
   - Add `test_latest_note_relation_returns_newest_note`. Seed one request with notes out of
     order, and add a same-second tie. Assert that `CustomerRequest::find($id)->latestNote->body`
     is the expected note. This covers the relation when it is reused outside `list()`.
   - Leave the other tests unchanged. They must pass as they are.
4. Update `DECISIONS.md` (Task 1). Rewrite the "Approach" bullet: the `latestNote()` relation
   eager-loaded with `withCount`, 2 queries, and why (idiomatic, reusable, the CLAUDE.md rule).
   Briefly name the trade-offs (the bound id list, the derived tables) and note that
   Revision 1 used a single-query subquery. Explain the tie-break: `latestOfMany` adds MAX(id),
   and MariaDB's UUID type orders MAX the same way as `ORDER BY id DESC`. Keep the "No index"
   and "Out of scope" bullets. In the verification table, set the "After" row to the new
   numbers: 2 queries, the new timing and the hash.
5. Fill row 2 in `AI_USAGE.md`:
   - Delegated: I asked the planner to revise the approach to an idiomatic `latestOfMany`
     relation.
   - Proposed: the relation plus eager loading, with no column constraint.
   - Check: the vendor tie-break check, the hash compare, the query count, tests and Pint.
   - Decision: I accepted 2 queries in return for idiomatic, reusable code.

   The human should review this wording.
6. The builder rewrites `.claude/tasks/01-list-performance/build.md` for Revision 2. The
   reviewer then rewrites `review.md`.

## Before/after check (for build.md)
The stack must be running, the dev DB seeded and not reseeded, and `DB_LOG_QUERIES=true`.
`S=/private/tmp/claude-501/-Users-niluka-Documents-Workplace-ai-native-fullstack-challenge-laravel-nextjs/56d6c0f1-94fd-4ed0-bc1e-03f686ab94e9/scratchpad`
1. Query count:
   `T=$(date -u +%Y-%m-%dT%H:%M:%SZ); sleep 1; curl -s localhost:3001/requests -o $S/after.json; sleep 1; docker compose logs api --since "$T" | grep -c 'query: '`
   → expected **2** (baseline 1,201, Revision 1 gave 1).
2. Timing, 5 runs:
   `for i in 1 2 3 4 5; do curl -s -o /dev/null -w '%{time_total}\n' localhost:3001/requests; done`.
   Expect roughly the Revision 1 time of about 0.06 s, and well under the 0.32-0.37 s baseline.
3. Response identity:
   `shasum -a 256 $S/before.json $S/after.json` must equal
   `850c2e52329fcf59195e9cde4b1858930a78b1c8c4cc8fba69d9e4a510d470eb`, and `jq length` must
   return 1200. If they differ, run `diff <(jq -S . $S/before.json) <(jq -S . $S/after.json)`
   and explain the difference.
4. Run `docker compose exec api php artisan test`,
   `docker compose exec web npm run typecheck` and
   `docker compose exec api ./vendor/bin/pint --test --dirty`.

## Tests
- The existing tests still pass unchanged: counts with out-of-order notes, the empty case
  (`0` / `null`), order and keys, and the tie-break by greater id.
- The query-count test checks that the count is equal for 2 and 25 requests, ≤ 3, and exactly 2.
- The new relation test checks that `latestNote` returns the newest note and breaks ties by
  greater id.
- The hash matches the baseline, and Pint is clean on the changed files.

## Risks
- **Placeholder limit:** the `IN (...)` list grows with N and would fail at about 65k requests.
  This is accepted, and pagination is out of scope.
- **Derived-table SQL:** it uses a temporary table and a filesort. It costs about 8-9 ms on
  4,800 notes and grows with the number of notes. No index will be added, same as Revision 1.
- **Silent nulls:** if anyone later adds a column constraint without `request_id`, every
  preview becomes `null`. The hash compare and the preview tests would catch this.
- **The query-count assertion of exactly 2 is stricter.** A future eager load would make it
  fail on purpose, and whoever adds one must update it.
- **Tie semantics** are the same as Revision 1. They rely on MariaDB's UUID ordering. Switching
  to `char(36)` with a case-insensitive collation would still agree for lowercase ids.
- **Out of scope:** `GET /requests/{id}` (it could use the relation later), pagination, the
  frontend and indexes.

Status: READY FOR REVIEW

## Approval
Reviewer: niluka2a (human)
Decision: APPROVED (Revision 2)
Notes: Approved on 2026-11-13. No auto commit: the human commits manually.

Status: PASS
