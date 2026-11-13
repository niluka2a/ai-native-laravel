# Requirement: Classification history

## Goal
README task 5. Staff can't see past classifications: `classifyAndStore()` overwrites `category`
and `confidence` on the request, and `GET /requests/history` and `/history` are stubs. Describe
the smallest useful release in `DECISIONS.md` and ship a thin slice: record new classifications,
serve them with a category filter through `GET /requests/history`, make `/history` useful, and
put the keyword classifier behind a provider interface so it can be swapped later.

## Evidence (2026-11-13, `origin/develop` 2a0b1a6)
- `apps/api/app/Http/Controllers/RequestsController.php:23-29`: `history()` returns a
  "not implemented yet" stub; `?category=nope` returns 200.
- `apps/api/app/Services/ClassificationService.php:21`: the service depends on the concrete
  `KeywordClassifier`. Task 4 deferred the interface to this task (DECISIONS.md).
- `apps/web/src/app/history/page.tsx`: a free-text input and a raw JSON dump.

## Acceptance criteria
- [ ] A classification stored on a request writes one history row (request, category,
      confidence, provider, time) in the same transaction as the request update.
- [ ] Classify without `requestId`, and failed classify calls (404, 422), write no row.
- [ ] `KeywordClassifier` implements a `ClassifierProvider` interface bound in the container;
      `ClassificationService` and its tests depend on the interface. A fake provider bound in a
      test is used and recorded.
- [ ] `GET /requests/history?category=` validates the category (422 otherwise), returns newest
      first, paginates, and runs a constant number of queries.
- [ ] `/history` has a category select, a table, loading/empty/error states and paging;
      classify on `/` refreshes history.
- [ ] The classify response shape is unchanged. The app runs locally with the keyword
      classifier; no API key or paid service.
- [ ] API tests, web tests and typecheck pass; Pint is clean on changed PHP files.
- [ ] `DECISIONS.md` "Classification history" and `AI_USAGE.md` are updated.

## Out of scope
- Backfilling existing classifications, recording previews, editing or deleting history.
- Filters other than category; who triggered a classification (no auth); retention.
- A model-backed provider, config-driven provider selection.

## Open questions
- Record classify calls without `requestId`? → No (human, 2026-11-13).
- Pagination? → `simplePaginate(50)` with Previous/Next (human, 2026-11-13).
- Classify with `requestId` goes from 2 to 3 queries? → Accepted (human, 2026-11-13).

Status: PASS
