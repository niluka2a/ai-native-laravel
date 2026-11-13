# Requirement: List performance (`GET /requests`)

## Goal
`GET /requests` is slow with the seeded data (1,200 requests, 4,800 notes). Change how the API
loads the list so it runs a small, fixed number of queries. The response must stay exactly the
same: same items, same order, same `noteCount` and `latestNotePreview`.

## Baseline (measured 2026-11-13, before any change)
- Queries per call: **1,201** (1 for requests + 1 per request for notes), counted from the
  `DB_LOG_QUERIES` output in `docker compose logs api`.
- Wall time (curl, 5 runs): 0.32–0.37 s.
- Response: 1,200 items, SHA-256 prefix `850c2e52329fcf59`.
- Cause: `RequestsService::list()` (`apps/api/app/Services/RequestsService.php`) queries
  `request_notes` inside the loop and loads every note row just to count it and read the first.

## Acceptance criteria
- [ ] `GET /requests` runs a constant number of queries (≤ 3) whatever the number of requests.
- [ ] On the same seeded data, the response after the change is byte-identical to the baseline
      (same hash), or any difference is explained and accepted.
- [ ] `noteCount` is correct, including 0 for a request with no notes.
- [ ] `latestNotePreview` is the body of the newest note by `created_at`, and `null` with no notes.
- [ ] A before/after check (query count, timing, response hash) is recorded in `build.md`.
- [ ] A feature test covers counts, latest preview, the empty case and the query count.
- [ ] `php artisan test` and `npm run typecheck` pass.

## Out of scope
- Pagination or any change to the response shape (the frontend reads the whole array).
- Changes to `GET /requests/{id}` and other endpoints.
- Frontend changes.

## Open questions
- Add a composite index `(request_id, created_at)` on `request_notes`? → Planner to decide;
  only if it helps measurably, since it needs a migration.
- Tie on note `created_at` (same second): which note is "latest"? Today the order is undefined.
  → Proposal: break ties by `id`, as `latestOfMany` does, and note it in `DECISIONS.md`.

Status: PASS
