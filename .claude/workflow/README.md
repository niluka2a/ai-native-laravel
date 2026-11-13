# Workflow

Every task goes through six steps, in order.

| # | Step | Who | Passes when |
|---|------|-----|-------------|
| 1 | [Requirement](01-requirement.md) | Human + AI | The goal and acceptance criteria are clear |
| 2 | [Plan](02-plan.md) | planner (Opus) | The plan covers every acceptance criterion |
| 3 | [Verify plan](03-verify-plan.md) | **Human** | The human approves the plan |
| 4 | [Build](04-build.md) | builder (Sonnet) | The code is written and the tests are added |
| 5 | [Verify build](05-verify-build.md) | AI | The tests, typecheck and acceptance criteria all pass |
| 6 | [Code review](06-code-review.md) | AI + Human | There are no blocking issues |

## The one rule: if a step fails, go back

```
1 → 2 → 3 → 4 → 5 → 6 → Done
    ↑   ↑   ↑   ↑   ↑
    └── a failed step goes back one step ──┘
```

- If a step fails, go back **one step** and fix it there.
- If that step also can't be fixed, go back one more step.
- When the earlier step passes again, move forward again from there.
- **Step 3 is a human gate.** No code is built until a human approves the plan.

## Where the work is kept

Each task gets its own folder: `.claude/tasks/<task-name>/`

```
requirement.md   ← step 1
plan.md          ← steps 2–3 (includes the approval)
build.md         ← steps 4–5 (changes and check results)
review.md        ← step 6
```

Every file ends with a status line: `Status: PASS`, or `Status: FAIL → back to step N`.
