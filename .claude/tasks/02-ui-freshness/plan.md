# Plan: 02-ui-freshness

## Problem
The list query `['requests']` is the only source for the table. The two row actions change
server state but never touch that cache:

- `page.tsx:32-35` `statusMutation`: only a `mutationFn`. The controlled `<select value={row.status}>`
  (`page.tsx:116`) re-renders with the cached status, so it snaps back.
- `page.tsx:37-40` `classifyMutation`: only a `mutationFn`. Category, confidence and the server's
  `open` → `in_progress` side effect (`RequestsController.php:96-98`) never reach the table.
- `providers.tsx:12-13` (`staleTime: 10_000`, no refetch on focus) means nothing else refreshes it.

Evidence that the API side works: the PATCH and classify handlers save through Eloquent
(`RequestsService::updateStatus`, `RequestsService::save`), and `GET /requests/{id}` returns the
saved values. The bug is entirely in the frontend cache.

## Approach
Use TanStack Query's standard pattern. The server stays the source of truth:
- **Both mutations:** `onSettled` invalidates `['requests']` and **returns** the promise, so the
  mutation stays pending until the fresh list has arrived.
- **Status only:** optimistic update (`onMutate` → `cancelQueries`, snapshot, `setQueryData`
  patching just `status` on that row; `onError` → restore the snapshot). This removes the
  snap-back.
- **Classify:** no optimistic update. Guessing the category or status would copy server rules
  into the client. The refetch brings the real values.
- We do not write the mutation responses into the cache, because their shapes do not match
  `RequestListItem` (see requirement).

## Changes
1. `apps/web/src/lib/api.ts`
   - `updateRequestStatus`: return type `Promise<RequestListItem>` → `Promise<unknown>`. The UI
     never reads it, and the old type was untrue (the API returns the raw snake_case model).
   - `classifyMessage`: add `export type ClassifyResult = { category: string; confidence: number;
     requestId: string | null }` and use it as the return type (today it is implicit `any`).
2. `apps/web/src/app/page.tsx`
   - `const REQUESTS_KEY = ['requests'] as const;`, used by the query and all three mutations.
   - `statusMutation`: add `onMutate` / `onError` / `onSettled` as described above. The context
     type is `{ previous?: RequestListItem[] }`.
   - `classifyMutation`: add `onSettled: () => queryClient.invalidateQueries({ queryKey: REQUESTS_KEY })`.
   - No markup changes.
3. Frontend test setup in `apps/web`:
   - Dev dependencies: `vitest`, `@vitejs/plugin-react`, `jsdom`, `@testing-library/react`,
     `@testing-library/dom`. Install them with
     `docker compose exec web npm install -D …` so `package-lock.json` is updated. node_modules
     lives in the `web-node-modules` volume.
   - `vitest.config.ts`: `environment: 'jsdom'`, react plugin, `@` → `./src` alias.
   - `package.json`: `"test": "vitest run"`.
   - The tests import `describe`/`it`/`expect`/`vi` from `vitest` explicitly (no globals), so
     `tsconfig.json` does not need to change.
4. `.github/workflows/ci.yml`: add a `Web tests` step (`npm test --prefix apps/web`) after
   `Typecheck`. This adds one step and leaves the existing steps alone.
5. `DECISIONS.md`: a task 2 entry covering invalidate-on-settle, optimistic status only, the
   reasons for not using the response payloads, and the new test runner. `AI_USAGE.md`: a
   checkpoint.

## Tests (`apps/web/src/app/page.test.tsx`)
The tests mock `@/lib/api` with `vi.mock`, and render `HomePage` inside a fresh `QueryClient`
with the same defaults as `providers.tsx` (`staleTime: 10_000`) plus `retry: false`. The stale
window is therefore reproduced: without invalidation, nothing refetches.

1. **Status shows immediately and sticks:** `updateRequestStatus` returns a deferred promise.
   After the user changes the select to `in_progress`, the select reads `in_progress` *before*
   the promise resolves. After it resolves, `fetchRequests` has been called again, and the select
   still reads `in_progress` (the second fetch returns it).
2. **Status rolls back on failure:** `updateRequestStatus` rejects. The select returns to
   `open`, and the list is refetched.
3. **Classify refreshes the row:** the second `fetchRequests` returns `category: 'billing',
   confidence: 0.86, status: 'in_progress'`. After a click on Classify, the row shows
   `billing (0.86)` and the select reads `in_progress`.

Red/green: on the current code, tests 1, 2 and 3 fail. The builder confirms this by running
them before the `page.tsx` change, and records it in `build.md`.

The API is unchanged, but `php artisan test` still runs. `npm run typecheck` checks the config
and test files too (`tsconfig` includes `**/*.ts(x)`).

## Verify build
- `docker compose exec web npm test`, `docker compose exec web npm run typecheck`, and
  `docker compose exec api php artisan test` all pass.
- Manual check at http://localhost:3000: change a status, and the select keeps the value without
  a refresh. Classify an `open` row, and the category appears and the status becomes
  `in_progress`. Then reload, and the values persist. Record the result in `build.md`.

## Risks
- **Full-list refetch per action:** `GET /requests` is 2 queries and about 50 ms after task 1,
  and returns all 1,200 rows. That is acceptable for now. Revisit it if pagination arrives.
- **Overlapping status changes:** each `onSettled` invalidates, and `cancelQueries` stops an
  in-flight fetch from overwriting a newer optimistic value. The worst case is one extra
  refetch, which is acceptable.
- **API errors returned as 200 + `{error}`** (for example, an unknown status) still count as
  success. The refetch then shows the server's real value, so the UI self-corrects. The proper
  fix is task 4.
- **New dev dependencies and a CI step:** this makes CI slightly longer. If the human prefers no
  frontend test runner, the fallback is manual verification only, and that breaks the "test every
  behaviour" rule.
- **Container:** the `web` container's `npm ci` on start needs the updated lockfile. It must be
  installed inside the container (above) so the lockfile and volume stay in sync.

## Decisions for the human to confirm
1. Add Vitest + RTL + jsdom and a CI step (recommended), or manual verification only?
2. Optimistic update for status (recommended), or invalidate only (the select snaps back for
   one round trip)?

Status: READY FOR REVIEW

## Approval
Reviewer: niluka2a (human)
Decision: APPROVED
Notes: Approved on 2026-11-13 with both recommended options: (1) add Vitest + React Testing
Library + jsdom and a `Web tests` CI step; (2) optimistic update for status only. No auto commit:
the human commits manually.

Status: PASS

---

# Plan: 02-ui-freshness (Revision 2)

## Revision note
Revision 1 was approved and built: an optimistic cache update for status, plus invalidate on
settle. On review, the human asked for simpler code and for the inputs to be disabled until the
request succeeds or fails. This revision replaces the cache-based optimistic update with the
TanStack Query v5 "optimistic UI via `variables`" pattern. Everything else in Revision 1 stands
(`api.ts` types, Vitest setup, CI step, docs).

## Changes
1. `apps/web/src/app/page.tsx`
   - `statusMutation`: remove `onMutate` and `onError`. Keep
     `onSettled: () => queryClient.invalidateQueries({ queryKey: REQUESTS_KEY })`, and return the
     promise so `isPending` stays true until the fresh list arrives.
   - `classifyMutation`: unchanged (already invalidates on settle).
   - `const isBusy = statusMutation.isPending || classifyMutation.isPending;`
   - Status `<select>`: `value` is `statusMutation.variables.status` while that mutation is
     pending for this row; otherwise it is `row.status`. Add `disabled={isBusy}`.
   - Classify button: `disabled={isBusy}`, styled with `disabled:opacity-50` like the existing
     Add button.
   - Remove the `RequestListItem` import if it becomes unused.
2. `apps/web/src/app/page.test.tsx`
   - Test 1 (unchanged intent): the select shows the new value before the PATCH resolves, and
     keeps it after the refetch.
   - Test 2 (rewritten): the PATCH rejects and the refetch is a pending deferred. While it is
     pending, the select is disabled. After the refetch resolves with `open`, the select shows
     `open` and is enabled.
   - Test 3 (unchanged): classify shows the category, the confidence and the server-set status.
   - New test 4: while a status change is pending, every status select and Classify button is
     disabled. Do the same while a classify is pending. All are enabled again once the
     action and its refetch settle.
   - Red/green: test 4 must fail on the Revision 1 code (nothing is disabled).
3. `DECISIONS.md`: update the task 2 entry to cover the UI-level pending value, disabling all row
   controls while busy, and why. `AI_USAGE.md`: add a short note on the checkpoint.
4. `.claude/tasks/02-ui-freshness/build.md`: add a Revision 2 section with the real output.

## Behaviour change vs Revision 1
- On failure, the select keeps showing the attempted value (disabled) until the refetch lands,
  then shows the server's value. Revision 1 reverted at once. The refetch takes tens of ms.
- Only one row action can be in flight at a time. This removes the overlap flicker (review
  minor finding 3).

## Risks
- **All row controls disabled while busy:** brief, and acceptable for 25 visible rows. Per-row
  disabling would need `useMutationState`, which is out of scope.
- **Slow API:** the controls stay disabled for the whole round trip plus the refetch. That is
  intended, because it prevents conflicting edits.

Status: READY FOR REVIEW

## Approval (Revision 2)
Reviewer: niluka2a (human)
Decision: APPROVED
Notes: Approved in chat on 2026-11-13 ("Ok, do it") after reviewing the proposed code and
trade-offs. No auto commit.

Status: PASS
