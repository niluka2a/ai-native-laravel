# Step 1: Requirement

**Who:** the human and the AI together
**Output:** `.claude/tasks/<task-name>/requirement.md`

## What to do

1. Write down the goal in one or two sentences.
2. List the acceptance criteria, each one something you can check.
3. Note what is out of scope.
4. If anything is unclear, the AI asks the human. It doesn't guess.

## Template

```markdown
# Requirement: <task name>

## Goal
<what we want and why>

## Acceptance criteria
- [ ] <checkable outcome 1>
- [ ] <checkable outcome 2>

## Out of scope
- <what we are not doing>

## Open questions
- <question> → <answer>

Status: PASS
```

## Pass or fail

- **PASS:** the goal is clear and every criterion can be checked. Go to step 2.
- **FAIL:** the requirement is unclear. Ask the human and stay on this step.
