# Step 5: Verify build

**Who:** the AI runs the checks, and a human can spot-check them.
**Input:** the code changes and `build.md`
**Output:** a check results section added to `build.md`

## Run the checks

```bash
docker compose exec api php artisan test
docker compose exec web npm run typecheck
```

Then check every acceptance criterion from `requirement.md`, for example with a curl call, the UI, or the query log.

## Record the results

```markdown
## Verification
| Check | Result |
|-------|--------|
| API tests | ✅ 12 passed |
| Typecheck | ✅ no errors |
| Criterion 1 | ✅ <how it was checked> |
| Criterion 2 | ❌ <what failed> |

Status: PASS | FAIL → back to step 4
```

Paste the real output. Never write "should work".

## Pass or fail

- **PASS:** every check is green. Go to step 6.
- **FAIL:** a check failed. Go back to step 4 and fix it.
