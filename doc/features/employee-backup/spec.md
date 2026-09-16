# Employee Backup — Spec

An admin can export employee configuration to a versioned JSON file. An
admin can import that file to move employees to another server or restore
a temporary backup.

## Problem

Employee configuration is stored across several related records. An admin
needs a portable backup before server changes. The admin also needs to move
that configuration to another server without entering it again.

## Solution

Add an admin-only employee backup page with export and import actions.

The export downloads a versioned JSON file. It contains each employee and
their configuration. The import accepts this JSON file and validates all
records before it changes the database.

The import matches employees by email. It creates employees that do not
exist. For a matching employee, it replaces the configuration from the
file. All referenced business lines, competences, shifts, and availability
questions must already exist on the destination server.

## Key Decisions

- The file format is versioned JSON. The configuration has nested records,
  so CSV is not suitable.
- Only an admin can export or import employee data. These actions expose and
  change all employee configuration.
- The archive contains names, email, weekly hours, business line,
  confirmation status, competences, recurring availability, holidays, and
  availability answers.
- The archive does not contain shift assignments, published schedules, user
  accounts, or personal links. These records are operational history or
  server-specific access data.
- The import uses email as the employee identity. A matching employee gets
  the archived configuration.
- The import only creates or updates employees. It matches each referenced
  business line, competence, shift, and availability question on the
  destination server.
- A missing reference rejects the archive. The error gives the employee
  record, reference type, and missing name.
- The import is all-or-nothing. Any invalid record rejects the complete
  archive and makes no changes.
- The JSON schema includes a version number. A later schema can fail with a
  clear error instead of applying an incompatible archive.
- The JSON archive replaces the old CSV employee importer. Remove its page,
  routes, controller, navigation entry, translations, and tests.

## Non-goals

- No export or import of shifts, shift assignments, or published weeks.
- No export or import of user accounts, passwords, or personal links.
- No deletion of employees that are absent from an imported archive.
- No partial import or background processing.
- No spreadsheet or CSV backup format.