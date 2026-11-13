# Build: 02-ui-freshness

## Changes
- `apps/web/src/lib/api.ts`: `updateRequestStatus` returns `Promise<unknown>` (the API returns the raw snake_case model, the UI never reads it). Added `ClassifyResult` (`{ category, confidence, requestId }`) as the return type of `classifyMessage`.
- `apps/web/src/app/page.tsx`: `REQUESTS_KEY` constant used by the query and all three mutations. `statusMutation` gets `onMutate` (cancel in-flight list fetch, snapshot, patch the row's `status`), `onError` (restore snapshot) and `onSettled` (invalidate and return the promise). `classifyMutation` gets `onSettled` (invalidate and return the promise). No markup changes.
- `apps/web/package.json`, `apps/web/package-lock.json`: dev dependencies `vitest ^5.1.0`, `@vitejs/plugin-react ^6.3.0`, `jsdom ^31.0.1`, `@testing-library/react ^16.3.3`, `@testing-library/dom ^10.4.2` (installed inside the web container); `"test": "vitest run"`.
- `apps/web/vitest.config.ts`: jsdom environment, react plugin, `@` alias to `./src`.
- `.github/workflows/ci.yml`: new `Web tests` step after `Typecheck`.
- `DECISIONS.md`: task 2 entry. `AI_USAGE.md`: checkpoint 3.
- No PHP files changed. `apps/web/next-env.d.ts` (pre-existing unrelated change) left alone.

## Tests added
- `apps/web/src/app/page.test.tsx` (mocks `@/lib/api`, fresh `QueryClient` with `staleTime: 10_000`, `retry: false`):
  1. status shows the new value before the API call resolves, the list is refetched, and the value sticks;
  2. status rolls back to `open` when the update rejects, and the list is refetched;
  3. classify: the row shows `billing`, `(0.86)` and status `in_progress` from the refetch.

## Deviations from the plan
- None in the code. Notes: Vitest prints a harmless warning that `vitest.config.ts` is ESM loaded as CommonJS (no `"type": "module"` in package.json). I left it as planned rather than renaming or changing package.json.
- `npm run build` in the running container would overwrite `.next` used by the dev server, so I built a copy of the app inside the container (`/tmp/b`, deleted afterwards) instead.

## Verification

### Red (before the `api.ts`/`page.tsx` change)
```
$ docker compose exec web npm test
 × shows a changed status immediately and keeps it after the refetch
 × rolls the status back and refetches when the update fails
 × shows the classified category, confidence and server-set status
AssertionError: expected 'open' to be 'in_progress'
AssertionError: expected "vi.fn()" to be called 2 times, but got 1 times
TestingLibraryElementError: Unable to find an element with the text: /billing/
 Test Files  1 failed (1)
      Tests  3 failed (3)
```

### Green (after)
```
$ docker compose exec web npm test
 RUN  v5.1.0 /app
 ✓ src/app/page.test.tsx (3 tests) 112ms
 Test Files  1 passed (1)
      Tests  3 passed (3)

$ docker compose exec web npm run typecheck
> tsc -p tsconfig.json --noEmit        (no errors)

$ docker compose exec api php artisan test
Tests: 8 passed (21 assertions)

$ next build (copy of the app in the container)
✓ Compiled successfully
  Finished TypeScript
✓ Generating static pages (4/4)
Route (app): / , /_not-found , /history
```

### API behaviour via curl (request `56344912-3474-4a95-be73-f59cf9e41dd4`)
```
before:          {"status":"open","category":null,"confidence":null}
PATCH resolved -> GET /requests: {"status":"resolved",...}
classify:        {"category":"sales","confidence":0.8,"requestId":"5634..."}
GET /requests:   {"status":"in_progress","category":"sales","confidence":0.8}
```
Restored to `open`, category NULL, confidence NULL, `updated_at` 2026-11-13 16:19:51 (original), via the API and one SQL update (classify has no way to clear the category).

| Check | Result |
|-------|--------|
| Web tests | ✅ 3 passed (3 failed before the change) |
| Typecheck | ✅ no errors |
| API tests | ✅ 8 passed |
| Production build | ✅ passed (in a copy of the app) |
| Frontend tests cover the criteria and fail on current code | ✅ 3/3 red before, green after |
| Select shows new status immediately and keeps it | ✅ test 1 (human UI check still to do) |
| Failed update returns to previous value | ✅ test 2 |
| List refetched after status change (success and failure) | ✅ tests 1 and 2 |
| Classify shows category, confidence, server-set status | ✅ test 3; curl confirms the API stores `in_progress` + category |
| Frontend types match the API | ✅ `unknown` / `ClassifyResult`, typecheck passes |

### Left for a human
Browser check at http://localhost:3000 (not possible here): change a status and confirm the select keeps it with no flicker; classify an `open` row and confirm category, confidence and `in_progress` appear; reload and confirm they persist. Note the dev DB is shared, so restore any row you change.

Status: PASS

## Review fix
Review found test 2 did not prove the rollback (its refetch also returned `open`). Test-only change in `apps/web/src/app/page.test.tsx`: the second `fetchRequests` is a deferred promise that stays pending; the test asserts `fetchRequests` was called twice and the select is back to `open` while the refetch is pending, then resolves it with `open` and asserts the select stays `open`.

Red (rollback line `queryClient.setQueryData(REQUESTS_KEY, context.previous);` temporarily removed from `page.tsx`):
```
 ✓ shows a changed status immediately and keeps it after the refetch
 × rolls the status back and refetches when the update fails
 ✓ shows the classified category, confidence and server-set status
AssertionError: expected 'resolved' to be 'open'
 Tests  1 failed | 2 passed (3)
```
Green (`page.tsx` restored; `git diff` of it identical to before the experiment):
```
 ✓ src/app/page.test.tsx (3 tests)
 Tests  3 passed (3)
npm run typecheck: no errors
```

## Revision 2
Implements plan Revision 2 (approved): replace the cache-based optimistic update with the `variables` pattern and disable row controls while busy.

### Changes
- `apps/web/src/app/page.tsx`: removed `onMutate`/`onError` from `statusMutation` (kept `onSettled` returning the invalidate promise); added `isBusy = statusMutation.isPending || classifyMutation.isPending`; status `<select>` value is `statusMutation.variables.status` while pending for that row, else `row.status`, with `disabled={isBusy}`; Classify button `disabled={isBusy}`; added `disabled:opacity-50` to both; removed the unused `RequestListItem` import.
- `apps/web/src/app/page.test.tsx`: test 2 rewritten (PATCH rejects, refetch pending: select disabled, then `open` and enabled after the refetch); new test 4 (two rows; all selects and Classify buttons disabled while a status change is pending, and again while a classify is pending; enabled after each settles). Tests 1 and 3 unchanged.
- `DECISIONS.md`, `AI_USAGE.md`: updated. `api.ts`, CI, Vitest setup unchanged.

### Red (new tests against the Revision 1 `page.tsx`)
```
 ✓ shows a changed status immediately and keeps it after the refetch
 × keeps the select disabled after a failed update until the refetch lands
 ✓ shows the classified category, confidence and server-set status
 × disables every row control while an action is pending
AssertionError: expected false to be true   (x2)
 Tests  2 failed | 2 passed (4)
```

### Green (Revision 2)
```
$ docker compose exec web npm test
 ✓ src/app/page.test.tsx (4 tests)
 Tests  4 passed (4)

$ docker compose exec web npm run typecheck
> tsc -p tsconfig.json --noEmit        (no errors)

$ docker compose exec api php artisan test
Tests: 8 passed (21 assertions)

$ next build (scratch copy in the container, deleted afterwards)
✓ Compiled successfully
  Finished TypeScript
✓ Generating static pages (4/4)
Routes: / , /_not-found , /history
```

| Check | Result |
|-------|--------|
| Web tests | ✅ 4 passed (test 4 and rewritten test 2 failed on Revision 1) |
| Typecheck | ✅ no errors |
| API tests | ✅ 8 passed |
| Production build | ✅ passed |

Still for a human: browser check at http://localhost:3000 (select keeps the new value and controls are briefly disabled; classify shows category and `in_progress`; values persist after reload).

Status: PASS
