# Build: 01-list-performance

## Changes
- `apps/api/app/Services/RequestsService.php`: `list()` now runs one query: `withCount('notes')` plus a correlated subquery (`addSelect`) for the latest note body, ordered by `created_at DESC, id DESC`, `LIMIT 1`. The per-request notes query and `setRelation` were removed. `noteCount` is `(int) $row->notes_count`. Output keys and order are unchanged.
- `DECISIONS.md`: approach, tie-break, no-index decision, before/after table.
- `AI_USAGE.md`: checkpoint 1.

## Tests added
- `apps/api/tests/Feature/RequestsListTest.php` (5 tests): counts and latest preview (notes inserted out of order), empty case (`0` int and `null`), order by `created_at` desc and exact key list, tie-break by greater id, constant query count (2 vs 25 requests, equal and <= 3).

## Deviations from the plan
- None.

Status: PASS

## Verification
Commands run from the repo root; dev DB `request_desk` was not reseeded or modified.

### Before / after
| Metric | Before | After |
|--------|--------|-------|
| Queries per call | 1,201 | 1 |
| Wall time (curl, 5 runs) | 0.32-0.37 s | 0.072, 0.060, 0.060, 0.058, 0.058 s |
| Response SHA-256 | `850c2e52329fcf59...` | `850c2e52329fcf59...` |
| Items | 1,200 | 1,200 |

Raw output:
```
queries: 1
0.071945
0.059616
0.060073
0.057835
0.057800
850c2e52329fcf59195e9cde4b1858930a78b1c8c4cc8fba69d9e4a510d470eb  .../scratchpad/before.json
850c2e52329fcf59195e9cde4b1858930a78b1c8c4cc8fba69d9e4a510d470eb  .../scratchpad/after.json
1200   (jq length after.json)
```

### Tests and types
```
$ docker compose exec api php artisan test
PASS Tests\Unit\KeywordClassifierTest (2 tests)
PASS Tests\Feature\RequestsListTest (5 tests)
Tests: 7 passed (19 assertions)

$ docker compose exec web npm run typecheck
> tsc -p tsconfig.json --noEmit     (no errors)
```

| Check | Result |
|-------|--------|
| API tests | ✅ 7 passed |
| Typecheck | ✅ no errors |
| Query count <= 3, constant | ✅ 1 query in the dev log (was 1,201); feature test asserts equal counts for 2 and 25 requests and <= 3 |
| Byte-identical response | ✅ SHA-256 equal to baseline (`850c2e52329fcf59...`) |
| `noteCount` correct, 0 when none | ✅ `test_request_without_notes_has_zero_count_and_null_preview` (assertSame int 0) and the 3-note test |
| `latestNotePreview` newest by `created_at`, null when none | ✅ out-of-order insert test, empty test, tie-break test |
| Before/after recorded | ✅ table above |
| Feature test covers counts, preview, empty, query count | ✅ `RequestsListTest` |

Status: PASS
