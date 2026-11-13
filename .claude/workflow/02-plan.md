# Step 2: Plan

**Who:** the `planner` agent (Opus). It reads the code only and doesn't edit it.
**Input:** `requirement.md`
**Output:** `.claude/tasks/<task-name>/plan.md`

## What to do

1. Read the requirement and the relevant code.
2. Find the root cause and show evidence for it: a file and line, a log, or command output.
3. Write the simplest plan that meets every acceptance criterion.

## Template

```markdown
# Plan: <task name>

## Problem
<what is wrong and why, with evidence>

## Changes
1. <step> — `path/to/file`
2. <step> — `path/to/file`

## Tests
- <test that proves criterion 1>
- <test that proves criterion 2>

## Risks
- <what could break>

Status: READY FOR REVIEW
```

## Pass or fail

- **PASS:** every acceptance criterion is covered. Go to step 3.
- **FAIL:** the requirement is wrong or unclear. Go back to step 1.
