# Step 3: Verify plan (human gate)

**Who:** a **human**. The AI must stop here and wait.
**Input:** `plan.md`
**Output:** an approval section added to the end of `plan.md`

## What the human checks

- [ ] The plan solves the requirement.
- [ ] The root cause makes sense and the evidence is real.
- [ ] The changes are small and the scope is right.
- [ ] The tests would actually prove the change works.
- [ ] The risks are acceptable.

## Record the decision

```markdown
## Approval
Reviewer: <name>
Decision: APPROVED | CHANGES NEEDED
Notes: <what to change, if anything>

Status: PASS | FAIL → back to step 2
```

## Pass or fail

- **PASS:** the human approves. Go to step 4.
- **FAIL:** the human asks for changes. Go back to step 2 with the notes.

> The AI never approves its own plan and never starts step 4 without this approval.
