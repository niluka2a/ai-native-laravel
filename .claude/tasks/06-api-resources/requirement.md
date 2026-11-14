# Requirement: API resources and request validation

## Goal
Every endpoint should build its JSON with a Laravel API Resource rather than a hand-built array
or a raw model, so all request endpoints return the same camelCase shape. Hand-written input
checks become Form Requests, so bad input gets a 422 like `classify` and `history`.

## Evidence (2026-11-14, `origin/develop` f4d7739)
- `apps/api/app/Http/Controllers/RequestsController.php:61-65`: `classify()` builds its response by hand.
- `apps/api/app/Services/RequestsService.php:21-35`: `list()` builds the JSON array inside the service.
- `show`, `store` and `updateStatus` return the raw `CustomerRequest` model, which is serialised in
  snake_case with every column. The web type `CreatedRequest` (`apps/web/src/lib/api.ts:25-31`)
  already expects `createdAt` and `updatedAt`, so it doesn't match the real response today.
- `store` and `updateStatus` validate by hand and return **200** with `{error}`. `updateStatus`
  accepts any status string.

## Acceptance criteria
- [ ] One `CustomerRequestResource` serves `GET /requests`, `GET /requests/{id}`, `POST /requests`
      and `PATCH /requests/{id}/status`. The keys are the same camelCase keys `GET /requests` uses today.
- [ ] The `GET /requests` response is unchanged: same keys, same order, same values, and the
      query count still doesn't grow with the number of rows.
- [ ] `GET /requests/{id}` includes `notes` (`id`, `body`, `authorName`, `createdAt`) through a
      `RequestNoteResource`. An unknown id returns 404.
- [ ] `POST /requests` returns 201 with the new request. A missing or non-string `message` returns 422.
- [ ] `PATCH /requests/{id}/status` returns the updated request. A missing status, or one that isn't
      `open|in_progress|resolved`, returns 422. An unknown id returns 404.
- [ ] `POST /requests/classify` uses a resource. Its response is unchanged: `{category, confidence, requestId}`.
- [ ] The `GET /requests/history` response is unchanged (paginated, wrapped in `data`).
- [ ] The web types in `api.ts` match the new responses. The web app works as before.
- [ ] API tests, web tests and typecheck pass. Pint is clean on the PHP files that changed.
- [ ] `DECISIONS.md` and `AI_USAGE.md` are updated.

## Out of scope
- New endpoints, auth, or changing the classifier or its confidence rules.
- A status enum used across the app (the allowed values stay a list on the model).
- Changes to the frontend UI.

## Open questions
- Change show/store/update to camelCase? → Yes, use one shared resource (human, 2026-11-14).
- Add Form Requests for store/updateStatus in this task? → Yes (human, 2026-11-14).

Status: PASS
