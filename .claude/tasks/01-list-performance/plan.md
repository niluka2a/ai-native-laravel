# Plan: 01-list-performance

## Problem
`RequestsService::list()` (`apps/api/app/Services/RequestsService.php`, lines 16-26) loads all
requests, then **inside the loop** runs `RequestNote::where('request_id', ...)->orderByDesc('created_at')->get()`
once per request. Each call loads every note row only to `count()` it and read `first()->body`.
On the seeded data (1,200 requests, 4,800 notes) that is **1 + 1,200 = 1,201 queries** per call,
0.32–0.37 s (baseline in `requirement.md`). Response time and query count grow linearly with N.

Checked facts:
- Laravel `v13.40.0` (composer.lock), MariaDB 11.4, PHP 8.3. `withCount`, `addSelect` with a
  subquery builder, and `latestOfMany` all exist in the installed vendor code.
- The controller returns the array as-is (`RequestsController::list`); routes have no prefix
  (`apiPrefix: ''`), so the endpoint is `GET /requests`.
- Seed data: 1,200 distinct request `created_at`s; 0 notes sharing `(request_id, created_at)`;
  0 requests without notes. So the baseline order and previews are deterministic, and the
  empty case can only be proven by a test.

## Approach (decision)
Use **one query**: `withCount('notes')` plus a correlated subquery for the preview, added with
`addSelect`. The subquery orders by `created_at DESC, id DESC` and takes `LIMIT 1`.

Why this one and not `hasOne()->latestOfMany()` + eager load:
- 1 query vs 2. Both meet "≤ 3".
- No new relation on the model. The change stays inside `list()`.
- No `WHERE request_id IN (...1,200 bound UUIDs...)` list. That list grows with N and would hit
  the 65,535-placeholder limit at about 65k requests. The correlated subquery has no bindings.
- The tie-break is written down in the query (`id DESC`), the same rule `latestOfMany` uses
  (MAX(id) among rows with MAX(created_at)).
- Read-only `ANALYZE` on the dev DB: the count subquery and the preview subquery both use the
  existing FK index `request_notes_request_id_foreign` (about 4 rows per request).
  Measured 2.4–2.6 ms for the count query and 5.8–6.8 ms for the preview query (3 runs).
  The `latestOfMany` SQL builds two derived tables ("Using temporary; Using filesort").

**Ties:** if two notes on one request share `created_at`, the one with the greater `id` wins.
IDs are UUIDs (the seeder uses random v4 via `Str::uuid()`), so this rule is *deterministic*
but not guaranteed *chronological*. Today the order is
undefined, so this is no regression. Record it in `DECISIONS.md`.

**Composite index `(request_id, created_at)`: not added.** The FK index already narrows each
subquery to about 4 rows. The only cost left is a filesort of those 4 rows, under 1 ms in
total. That is not a measurable gain that would justify a migration. Revisit this if notes per
request grow a lot.

## Changes
1. Rewrite `list()` — `apps/api/app/Services/RequestsService.php`
   ```php
   $rows = CustomerRequest::query()
       ->withCount('notes')
       ->addSelect(['latest_note_preview' => RequestNote::query()
           ->select('body')
           ->whereColumn('request_notes.request_id', 'customer_requests.id')
           ->orderByDesc('request_notes.created_at')
           ->orderByDesc('request_notes.id')
           ->limit(1)])
       ->orderByDesc('customer_requests.created_at')
       ->get();
   ```
   Map each row to the **same keys in the same order** as today. Use
   `'noteCount' => (int) $row->notes_count` and
   `'latestNotePreview' => $row->latest_note_preview`. Remove the loop query and the
   `setRelation` call. Keep the docblock return type. Do not change other methods.
2. Add the feature test — `apps/api/tests/Feature/RequestsListTest.php` (see Tests).
3. Record decisions — `DECISIONS.md`: under "Priorities and trade-offs", explain why the N+1 was
   fixed first, the single-query approach and why not `latestOfMany`, the tie-break rule
   (`created_at DESC, id DESC`), and why there is no index. Under "Verification", add the
   before/after numbers.
4. AI checkpoint — `AI_USAGE.md`, row 1: I asked the planner to fix the N+1 with ≤ 3 queries and
   a byte-identical response. It proposed a withCount + correlated subquery. My check was the
   query count, the hash compare against `before.json`, and the feature test, with their
   results. My decision and why.
5. Before/after record — `.claude/tasks/01-list-performance/build.md` (builder writes it, using
   the procedure below).

## Before/after check procedure (for build.md)
Stack running, dev DB seeded (1,200 / 4,800), `DB_LOG_QUERIES=true` (already set in `apps/api/.env`).
`S=/private/tmp/claude-501/-Users-niluka-Documents-Workplace-ai-native-fullstack-challenge-laravel-nextjs/56d6c0f1-94fd-4ed0-bc1e-03f686ab94e9/scratchpad`
1. Query count for one call:
   ```bash
   T=$(date -u +%Y-%m-%dT%H:%M:%SZ); sleep 1
   curl -s localhost:3001/requests -o $S/after.json
   sleep 1; docker compose logs api --since "$T" | grep -c 'query: '
   ```
   Expected: `1` (was 1,201).
2. Timing (5 runs):
   `for i in 1 2 3 4 5; do curl -s -o /dev/null -w '%{time_total}\n' localhost:3001/requests; done`
3. Response identity:
   `shasum -a 256 $S/before.json $S/after.json`. The hashes must match (prefix `850c2e52329fcf59`).
   If they don't match, run `diff <(jq -S . $S/before.json) <(jq -S . $S/after.json)` and explain
   every difference in `build.md`.
   Also check `jq length $S/after.json` → 1200.
4. Tests and types: `docker compose exec api php artisan test` and
   `docker compose exec web npm run typecheck`.
Record the commands, the raw outputs, and the before/after table in `build.md`.

## Tests
`apps/api/tests/Feature/RequestsListTest.php`. Use `RefreshDatabase` (it runs on MariaDB
`request_desk_test`, as `phpunit.xml` sets). There are no factories, so insert rows with
`DB::table(...)->insert` and explicit `id`/`created_at` values.
- **Counts and latest preview:** request A has 3 notes at t, t+1s, t+2s → `noteCount` 3,
  preview = body of the t+2s note. It must not be the first one inserted: insert them out of
  order.
- **Empty case:** request B has no notes → `noteCount` is `0` (an int, use `assertSame`) and
  `latestNotePreview` is `null`.
- **Order and shape:** items are sorted by request `created_at` desc. Each item has exactly the
  keys `id, message, status, category, confidence, noteCount, latestNotePreview, createdAt,
  updatedAt`.
- **Tie-break:** two notes with the same `created_at`, ids `...0001` and `...0002` → the preview
  is the body of `...0002`. This proves the documented rule.
- **Constant query count:** seed 2 requests and call the endpoint with `DB::enableQueryLog()`
  (flush the log after seeding). Then seed up to 25 requests with notes and call it again.
  Assert both counts are equal and `<= 3`. Expected: 1.
- Existing `KeywordClassifierTest` still passes. Run `npm run typecheck` (no frontend change).

## Risks
- **Byte drift:** `notes_count` comes back from PDO as an int on PHP 8.3/MariaDB. The `(int)`
  cast guarantees it. Key order must stay the same, or the hash changes. The hash compare
  catches both problems.
- **Tie semantics:** the behaviour is now defined (`id DESC`) where it was undefined before.
  This is visible only for same-second notes. It is documented, and seed data has none.
- **Column ambiguity:** both the outer and inner queries have `created_at`/`id`, so qualify the
  column names as shown above.
- **Scale:** the endpoint still returns every row, unpaginated. The payload size still grows
  with N. Pagination is out of scope.
- Out of scope: `GET /requests/{id}`, other endpoints, the frontend, and the index migration.

Status: READY FOR REVIEW

## Approval
Reviewer: niluka2a (human)
Decision: APPROVED
Notes: Approved as written on 2026-11-13 ("Yes, build").

Status: PASS
