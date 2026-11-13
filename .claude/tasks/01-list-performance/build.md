# Build: 01-list-performance (Revision 2)

Revision 1 was commit `73a14c2` (hand-written subquery, 1 query). This build replaces the subquery with an Eloquent relationship.

## Changes
- `apps/api/app/Models/CustomerRequest.php`: added `latestNote(): HasOne` (`hasOne(RequestNote::class, 'request_id')->latestOfMany('created_at')`) and the `HasOne` import.
- `apps/api/app/Services/RequestsService.php`: `list()` replaces the `addSelect` subquery with `->with('latestNote')`; `latestNotePreview` is `$row->latestNote?->body`. Removed the unused `RequestNote` import. Keys, order and docblock unchanged.
- `DECISIONS.md`: approach, tie-break and verification table updated for Revision 2.
- `AI_USAGE.md`: checkpoint 2 filled in.

## Tests added
- `apps/api/tests/Feature/RequestsListTest.php`: query-count test now also asserts exactly 2 queries; new `test_latest_note_relation_returns_newest_note` (notes out of order plus a same-second tie, asserts the greater id wins through `CustomerRequest::find()->latestNote`). Other 5 tests unchanged.

## Deviations from the plan
- `pint --test --dirty` cannot run in the container (`--dirty` needs git, which the api image does not have: "The [--dirty] option is only available when using Git."). I ran `pint --test` on the 3 changed PHP files instead.

## Verification
Dev DB `request_desk` was not reseeded or modified.

### Before / after
| Metric | Before | After (Rev 2) |
|--------|--------|---------------|
| Queries per call | 1,201 | 2 (Rev 1: 1) |
| Wall time (curl, 5 runs) | 0.32-0.37 s | 0.091, 0.077, 0.075, 0.076, 0.077 s |
| Response SHA-256 | `850c2e52329fcf59...` | `850c2e52329fcf59...` |
| Items | 1,200 | 1,200 |

Raw output:
```
queries: 2
0.090600
0.077052
0.074872
0.076056
0.076730
850c2e52329fcf59195e9cde4b1858930a78b1c8c4cc8fba69d9e4a510d470eb  .../scratchpad/before.json
850c2e52329fcf59195e9cde4b1858930a78b1c8c4cc8fba69d9e4a510d470eb  .../scratchpad/after-r2.json
1200   (jq length after-r2.json)
```

### Tests, types, style
```
$ docker compose exec api php artisan test
Tests: 8 passed (21 assertions)   (KeywordClassifierTest 2, RequestsListTest 6)

$ docker compose exec web npm run typecheck
> tsc -p tsconfig.json --noEmit     (no errors)

$ docker compose exec api ./vendor/bin/pint --test app/Models/CustomerRequest.php app/Services/RequestsService.php tests/Feature/RequestsListTest.php
PASS ... 3 files
```
Pre-existing Pint failures in files this task did not change (left alone): `app/Http/Controllers/RequestsController.php`, `bootstrap/providers.php`, `database/seeders/DatabaseSeeder.php`.

| Check | Result |
|-------|--------|
| API tests | ✅ 8 passed |
| Typecheck | ✅ no errors |
| Pint (changed files) | ✅ clean |
| Query count <= 3, constant | ✅ 2 in the dev log (was 1,201); test asserts equal for 2 and 25 requests, <= 3, and == 2 |
| Byte-identical response | ✅ SHA-256 equals baseline |
| `noteCount` correct, 0 when none | ✅ empty-case and 3-note tests |
| `latestNotePreview` newest, null when none | ✅ out-of-order, empty, tie-break and relation tests |
| Before/after recorded | ✅ table above |
| Feature test covers counts, preview, empty, query count | ✅ `RequestsListTest` |

Status: PASS
