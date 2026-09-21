# Employee Optional Email — Plan

Status: done — 8/8

Each step is one slice. Write the test first, then the code, then commit.
Add every new user-facing text to `resources/lang/en.json`.

- [x] 1. **Optional email on create and edit.** Add a migration that makes
      `employees.email` nullable. Change the `EmployeeController`
      validation to `nullable|email|max:255|unique`. Store an empty string
      as NULL. The email field has no required state, so the
      frontend needs no change. Test: create and edit
      with no email, clear an existing email, and reject a duplicate or
      invalid email. Add a regression test that self-signup with a new
      email creates a second employee.
- [x] 2. **Employees list marker and "send link" guard.** Disable the
      "send link" button in the list. Make `EmployeeController::sendLink` refuse an
      employee with no email. Test: the list props, the disabled
      button, and the refusal. Test that "copy personal-page link" still works.
- [x] 3. **Mailbox recipient picker.** Leave employees with no email out
      of the employee list in `MailboxController`, and out of the
      preselected recipient. Test: the picker data and the Compose page.
- [x] 4. **Planning email and Uninformed planning report.** Make
      `PlanningNotifier` skip employees with no email. Their planning
      stays uninformed. Show the marker in `UninformedPlanningReport.vue`
      and skip these rows in "email selected". Test: the notifier and the
      report.
- [x] 5. **Missing availability report.** Show the marker and skip
      employees with no email in "email selected". Test: the report data
      and the frontend selection.
- [x] 6. **Employee backup import and export.** The export route uses the
      full backup (step 7), so it needs no change here. Accept a null email
      on import. Match an existing employee that has
      no email by first and last name (case-insensitive), or create one.
      Reject two records with the same name and no email. Test: a
      round-trip, a repeated import that creates no duplicate, and the
      duplicate rejection.
- [x] 7. **Full application backup.** Test that `ApplicationBackup` exports
      and restores a null email without change. The test passed with no
      code change.
- [x] 8. **Personal page.** Hide the read-only email field in
      `Personal/Show.vue` when the email is empty. Test: the page shows no
      email field for an employee with no email, and shows it for one with
      an email.
