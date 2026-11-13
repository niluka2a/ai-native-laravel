# Review: 04-classification-structure

## Independently re-run (not taken from build.md)
- `php artisan test`: 43 passed (120 assertions)
- `pint --test` on the 10 new/changed PHP files and `RequestsService.php`: PASS
- `RequestsController.php` fails Pint on its **original** version too (`HEAD`), so the failure
  isn't from this task. Left alone under the "don't reformat files" rule.
- `npm run typecheck`: no errors. `npm test`: 4 passed.

## Findings
- **Fixed:** `RequestsService::save()` had no callers left after the refactor (classify was its
  only one), so it was removed.
- **Fixed (second pass):** the word count now calls `preg_split` with a limit of 3, so a
  2000-character message stops splitting after 3 pieces. The result is the same because the
  rule only checks `< 3`. A 5-word case was added to the unit test.
- **Fixed (second pass):** renamed `ClassificationService::classifyRequest()` to
  `classifyAndStore()`, because it was easy to confuse with the `ClassifyRequest` FormRequest.
  After both fixes: 43 tests passed (121 assertions), Pint clean, typecheck clean, web tests 4
  passed, and curl results unchanged.
- **OK:** the controller only wires; the FormRequest, service, model method, enum and value
  object each have one job. The response shape is unchanged for success cases.
- **OK:** `ClassificationService::classify()` trims again even though `TrimStrings` already
  did. That's harmless, and it keeps the service correct for non-HTTP callers.
- **Note, not blocking:** `preg_split('/\s+/')` has no `/u` flag, so it doesn't split on
  Unicode spaces. That is unchanged from before and is covered by the preserve rule.
- **Follow-ups for later tasks:** `store` and `updateStatus` still validate inline and return
  200 `{error}`. A `Status` enum. The provider interface (task 5).

No blocking issues.

Status: PASS
