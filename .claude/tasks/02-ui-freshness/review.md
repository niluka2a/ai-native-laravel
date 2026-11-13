# Review: 02-ui-freshness

Reviewed the working-tree diff against `develop` (f1e2ba1). I reran the checks myself:
`npm test` 3 passed, `npm run typecheck` clean, `php artisan test` 8 passed.

## Checked
- **Correct:** both mutations invalidate `['requests']` in `onSettled` and return the promise.
  Status uses the standard TanStack optimistic pattern (cancel, snapshot, patch, restore).
  Classify relies on the refetch, so the server's `open` → `in_progress` rule is not duplicated.
- **Simple:** about 20 lines in `page.tsx`, with no markup changes. The types in `api.ts` are
  now honest.
- **Safe:** no PHP changes and no secrets. The lockfile diff only adds packages: 100 added,
  0 removed, and 0 existing versions changed. It was checked against `develop`'s lockfile and
  includes the rolldown and lightningcss bindings for every platform, so `npm ci` works on the
  ubuntu CI runner.
- **Clear:** matches the existing style. The comments explain why.

## Findings
| Severity | File | Issue |
|----------|------|-------|
| Blocking | `apps/web/src/app/page.test.tsx` (test 2, "rolls the status back…") | The test does not prove the rollback. Its refetch also returns `status: 'open'`, so the final assertion passes whether or not `onError` restores the snapshot. **Verified:** with the `setQueryData(REQUESTS_KEY, context.previous)` line removed in a scratch copy, all 3 tests still pass. Fix: keep the second `fetchRequests` pending (a deferred promise), assert the select is back to `open` *before* the refetch resolves, then resolve it. |
| Minor | `apps/web/vitest.config.ts` | Vite warns "ESM syntax in a file loaded as CommonJS". This is harmless today. Renaming to `.mts` would silence it, but `tsconfig` `include` only covers `**/*.ts`, so typecheck would stop checking the config. Leave it as is; noted for later. |
| Minor | `apps/web/src/app/page.tsx:52` | When status changes on two rows overlap, the first `onSettled` refetch can briefly show the second row's old status until its own refetch lands. It corrects itself, and this matches the risk accepted in the plan. A possible later fix is to invalidate only when `isMutating` drops to the last mutation. |

## Left for a human
A browser check at http://localhost:3000, as listed in `build.md`.

Status: FAIL → back to step 5 (test-only fix in step 4)

## Re-review (after the test fix)
- Test 2 now keeps the refetch pending and asserts `open` before it resolves, so only the
  rollback can explain the value.
- **Verified:** in a scratch copy with the rollback line removed, test 2 fails
  (`expected 'resolved' to be 'open'`; 1 failed, 2 passed). With the real code, 3 of 3 pass.
- `npm run typecheck` is clean. `page.tsx` is unchanged by the fix.
- The blocking finding is resolved. The two minor findings stand as accepted.

Status: PASS

## Re-review (Revision 2: simpler code, disable while busy)
- `page.tsx`: `onMutate`/`onError` and the cache snapshot are gone. The pending value comes
  from `statusMutation.variables`, the controls use `disabled={isBusy}`, and both `onSettled`
  handlers return the invalidate promise. Net: fewer lines than Revision 1, and no manual cache
  writes.
- **Rerun:** `npm test` 4 of 4 passed, typecheck clean. The builder recorded red (2 failed on
  the Revision 1 page) and the API tests (8 passed) in `build.md`.
- **Mutation check:** in a scratch copy, I made `onSettled` stop returning the invalidate promise.
  Test 2 then fails ("keeps the select disabled after a failed update until the refetch lands"),
  so the tests guard the "disabled until the refetch lands" behaviour too.
- Minor finding 3 (overlap flicker) is resolved: only one row action can be in flight.
  Minor finding 2 (Vite config warning) stands as accepted.

Status: PASS
