# Review: 01-list-performance

Diff reviewed: `RequestsService::list()`, `tests/Feature/RequestsListTest.php`, `DECISIONS.md`,
`AI_USAGE.md`. Checks were re-run independently after the build: 1 query per call, response
SHA-256 identical to the baseline (`850c2e52329fcf59195e…`), 7 tests passed, typecheck clean.

## Checklist
- [x] **Correct:** same keys, order and values as before (proven by the byte-identical hash on
      1,200 seeded requests). Empty case returns `0` / `null`. Column names are qualified, so the
      outer and inner `created_at`/`id` are not ambiguous.
- [x] **Simple:** the loop query and `setRelation` are removed. No new relation, no migration.
- [x] **Safe:** the subquery has no user input and no bindings. No secrets. Other endpoints are
      untouched.
- [x] **Tested:** the query-count test fails on the old code (2 queries vs 26). Tie-break and
      out-of-order tests insert rows in the opposite order to the expected result.
- [x] **Clear:** matches the existing style. The docblock return type still holds.

## Findings
| Severity | File | Issue |
|----------|------|-------|
| Minor | `apps/api/app/Services/RequestsService.php:35` | `(int)` on `notes_count` is redundant (PDO already returns an int). It's kept as a cheap guard for the byte-identical output, so no change. |
| Minor | `apps/api/tests/Feature/RequestsListTest.php:40` | `at()` uses `date()`/`strtotime()` with the PHP default timezone. It's safe because only relative order matters, but `Carbon` would read more like the rest of the codebase. Optional. |
| Minor | `AI_USAGE.md` (row 1) | Written in the first person on the human's behalf ("I approved the plan…"). The human should check the wording matches their own view. |
| Info | `GET /requests` | Still unpaginated, so the payload grows with N. Out of scope and recorded in `DECISIONS.md`. |

No blocking issues.

Status: PASS
