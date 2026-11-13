# AI usage

List the tools you used and what you used them for. Add two brief checkpoints as you work.
Use a short excerpt or paraphrase of the instruction you gave. Point to a reproducible check
and record what happened when you ran it.

| Checkpoint | What I delegated and the constraint I set | What AI proposed | My check and what it showed | My decision and why |
| --- | --- | --- | --- | --- |
| 1 | I delegated planning of the `GET /requests` N+1 fix to the planner agent, with the constraint "at most 3 queries, byte-identical response, no paid AI". A builder agent implemented the approved plan. | `withCount('notes')` plus a correlated subquery for the latest note body (1 query, no index), with an explicit `created_at DESC, id DESC` tie-break. | Before/after on the seeded data: queries 1,201 to 1, time 0.32-0.37 s to about 0.06 s, response SHA-256 identical (`850c2e52329fcf59...`, 1,200 items). `php artisan test`: 7 passed, including `tests/Feature/RequestsListTest.php`. `npm run typecheck`: no errors. Details in `.claude/tasks/01-list-performance/build.md`. | I approved the plan after reading it. I accepted the single-query approach and no index because the measured gain from an index is under 1 ms, and I accepted the documented tie-break since the old order was undefined. |
| 2 | I asked the planner to replace the hand-written subquery with an Eloquent relationship (idiomatic Laravel) after the human added a CLAUDE.md rule on framework best practices and PSR style. | `latestNote()` as `hasOne()->latestOfMany('created_at')`, eager loaded with `withCount('notes')`, no column constraint; the planner verified the tie-break (`MAX(id)` equals `ORDER BY id DESC` on MariaDB's UUID type). | Same seeded data: queries 1,201 to 2, time about 0.075 to 0.091 s, SHA-256 identical to baseline (`850c2e52329fcf59...`, 1,200 items). `php artisan test`: 8 passed; `npm run typecheck`: no errors; Pint clean on the 3 changed PHP files. Details in `.claude/tasks/01-list-performance/build.md`. | I approved Revision 2 and accepted 2 queries instead of 1 in return for idiomatic, reusable code. |

You can link to a file, commit, test, or command output. Leave out secrets, personal data,
and full chat logs.
