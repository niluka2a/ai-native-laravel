# Review: 01-list-performance (Revision 2)

Revision 1 (`73a14c2`, `addSelect` subquery, 1 query) passed review. This review covers
Revision 2, which replaces the subquery with an Eloquent relationship.

Diff reviewed: `CustomerRequest::latestNote()`, `RequestsService::list()`,
`tests/Feature/RequestsListTest.php`, `DECISIONS.md`, `AI_USAGE.md`, `CLAUDE.md`. Checks were
re-run independently after the build:
- 2 queries per call: the `withCount` select, then the `latestOfMany` eager load.
- Response SHA-256 identical to the original baseline (`850c2e52329fcf59195e…`).
- 8 tests passed (21 assertions). Typecheck clean.
- Pint passes on the 3 changed PHP files.

## Checklist
- [x] **Correct:** byte-identical output on 1,200 seeded requests. Empty case: `withCount` gives
      0, and with no note `latestNote` is `null`, so the `?->body` preview is `null`. The
      generated SQL takes `MAX(created_at)` and then `MAX(id)` per request (checked in the API
      log), which matches the documented tie-break.
- [x] **Simple:** idiomatic Eloquent. No hand-written SQL in the service. The unused
      `RequestNote` import is removed.
- [x] **Safe:** no user input reaches the query. No secrets. Other endpoints are untouched.
- [x] **Tested:** the query-count test pins exactly 2 queries. The new relation test covers
      out-of-order notes and a same-second tie on the relation directly.
- [x] **Clear:** follows PSR-12 (Pint passes) and has a typed `HasOne` return.

## Findings
| Severity | File | Issue |
|----------|------|-------|
| Minor | `apps/api/app/Services/RequestsService.php:29` | `(int)` on `notes_count` is redundant. It's kept as a guard for the identical output. |
| Minor | `apps/api/app/Services/RequestsService.php:16` | `with('latestNote')` loads all note columns. Limiting them saves about 1 ms but risks silent `null` previews if `request_id` is left out, so it's deliberately not done (see plan). |
| Minor | `CLAUDE.md` | The Pint rule's `--dirty` flag fails in the container (no git). Fixed during review to list the files explicitly. |
| Minor | `AI_USAGE.md` (row 2) | Written in the first person on the human's behalf. The human should check the wording. |
| Info | `GET /requests` | About 15 ms slower than Revision 1 (≈0.077 s vs ≈0.06 s) because of the second query. Accepted when the plan was approved. Still unpaginated, which is out of scope. |
| Info | other PHP files | `pint --test` fails on `RequestsController.php`, `bootstrap/providers.php` and `DatabaseSeeder.php`. These failures existed before this task and the files are untouched. Suggest a separate style-only cleanup. |

No blocking issues.

Status: PASS
