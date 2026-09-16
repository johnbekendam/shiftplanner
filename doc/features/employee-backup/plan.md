# Employee Backup — Plan

Status: done — 6/6

Spec: `spec.md`. An admin can export and atomically import a versioned JSON
archive of employee configuration.

- [x] 1. Add focused failing feature and UI tests for admin access, archive
  download, validation, reference creation, and replacement by email.
- [x] 2. Add the admin backup routes and a controller that creates and
  imports the versioned archive in a database transaction.
- [x] 3. Add the backup page with export download, JSON file selection, and
  import result or validation errors.
- [x] 4. Remove the old CSV importer, including its routes, controller,
  page, navigation entry, translations, and tests.
- [x] 5. Add translations and the admin navigation entry for the backup
  page.
- [x] 6. Run focused tests, formatting, the full test suites, and the build.

## Follow-up

Status: done — 3/3

- [x] 1. Add failing tests for strict existing-reference matching and clear
  missing-reference errors.
- [x] 2. Change the archive importer to create or update employees only.
- [x] 3. Run focused tests and formatting.