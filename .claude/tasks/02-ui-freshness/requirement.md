# Requirement: UI freshness (status change and classify)

## Goal
Changing a request's status or classifying it succeeds in the API, but the table on `/` keeps
showing the old values until the page is reloaded. Both actions must update the table without a
manual refresh, and the table must show what the server actually stored.

## Evidence (2026-11-13, before any change)
- `statusMutation` and `classifyMutation` in `apps/web/src/app/page.tsx:32-40` have no
  `onSuccess`/`onSettled`. Only `createMutation` invalidates `['requests']` (`page.tsx:24-30`).
- `providers.tsx:12-13`: `staleTime: 10_000`, `refetchOnWindowFocus: false`, so nothing else
  refetches the list.
- The status `<select>` is controlled by the cached row (`page.tsx:116`), so after a change it
  snaps back to the old status.
- `POST /requests/classify` also moves `open` → `in_progress`
  (`RequestsController.php:96-98`), but its response is only `{category, confidence, requestId}`.
- `PATCH /requests/{id}/status` returns the raw Eloquent model (snake_case, with `notes`), not
  the `RequestListItem` that `updateRequestStatus` declares (`api.ts:46-49`).

## Acceptance criteria
- [ ] After picking a new status, the select shows the new value immediately and keeps it.
- [ ] If the status update fails, the select returns to the previous value.
- [ ] After a status change (success or failure), the list is refetched from the API.
- [ ] After classify, the row shows the new category and confidence, and the status the server
      set (`open` → `in_progress`), without a manual refresh.
- [ ] The frontend types match what the API returns for both actions.
- [ ] Frontend tests cover the above and fail on the current code.
- [ ] `npm run typecheck`, the new frontend tests and `php artisan test` pass.

## Out of scope
- API changes: validation, HTTP status codes for errors (200 + `{error}` today), response shapes.
  That is task 4.
- `createRequest` returns snake_case but is typed camelCase. It is unused by the UI, so it is
  left alone.
- Pagination, per-row success messages, styling.

## Open questions
- How to test the UI? → Proposal: add Vitest + React Testing Library + jsdom (free dev
  dependencies) and a `test` script, and run it in CI. **Human to confirm at plan approval.**
- Optimistic status update in scope? → Proposal: yes for status (it removes the snap-back); no
  for classify (it would duplicate the server's rules). **Human to confirm at plan approval.**

Status: PASS
