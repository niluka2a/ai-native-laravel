# Step 6: Code review

**Who:** the AI reviews first, and a human makes the final call.
**Input:** the diff (`git diff develop...HEAD`)
**Output:** `.claude/tasks/<task-name>/review.md`

## What to check

- [ ] **Correct:** it does what the requirement says, including edge cases.
- [ ] **Simple:** there's no dead code and no needless complexity.
- [ ] **Safe:** no secrets, no broken behaviour, and the inputs are validated.
- [ ] **Tested:** the tests cover the change and would fail without it.
- [ ] **Clear:** the names are readable and the code matches the existing style.

## Template

```markdown
# Review: <task name>

## Findings
| Severity | File | Issue |
|----------|------|-------|
| Blocking | `path:line` | <problem> |
| Minor | `path:line` | <suggestion> |

Status: PASS | FAIL → back to step 5
```

## Pass or fail

- **PASS:** there are no blocking issues. The task is done, so commit and open a PR.
- **FAIL:** there is a blocking issue. Go back to step 5. If the fix needs code, step 5 sends it back to step 4.
