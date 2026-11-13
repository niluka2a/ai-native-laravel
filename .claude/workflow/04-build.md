# Step 4: Build

**Who:** the `builder` agent (Sonnet)
**Input:** the approved `plan.md`
**Output:** code changes, plus `.claude/tasks/<task-name>/build.md`

## What to do

1. Follow the approved plan step by step. Don't redesign it.
2. Keep the changes small and match the existing code style.
3. Add or update a test for every behaviour you change.
4. If the plan turns out to be wrong, stop. Don't improvise.

## Template

```markdown
# Build: <task name>

## Changes
- `path/to/file`: <what changed>

## Tests added
- `tests/...`: <what it checks>

## Deviations from the plan
- None | <what was different, and why>

Status: PASS | FAIL → back to step 3
```

## Pass or fail

- **PASS:** the code and tests are written. Go to step 5.
- **FAIL:** the plan can't be built as written. Go back to step 3 so the human can decide, which may mean returning to step 2.
