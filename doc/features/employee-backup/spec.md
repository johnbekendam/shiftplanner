# Employee Backup — Spec

An admin can export the complete durable ShiftPlanner application state to
a versioned JSON file. An admin can import that file into a new instance.

## Problem

Application state is stored across several related records. An admin needs
a portable backup before server changes and a way to recreate an instance
without entering the configuration and schedule again.

## Solution

Add an admin-only employee backup page with export and import actions.

The export downloads a versioned JSON file. Version 2 contains all durable
application tables: users, employees, configuration, availability,
workcenters, capacities, assignments, publication state, planning rules,
message templates, and mailbox history. The import validates the archive
and restores it in one transaction.

Version 1 employee archives remain importable. Version 2 restores rows and
their relationships with their exported IDs, so it can recreate a fresh
instance without pre-existing reference data.

## Key Decisions

- The file format is versioned JSON. The configuration has nested records,
  so CSV is not suitable.
- Only an admin can export or import employee data. These actions expose and
  change all employee configuration.
- Version 2 contains the durable application tables needed to recreate the
  instance, including shift assignments, published weeks, user accounts,
  personal links, and mailbox data.
- Password hashes and personal-link bearer tokens are included because a
  complete restore must preserve access. The archive omits remember tokens,
  short-lived login links, sessions, queues, cache data, and migrations.
- Plan generation runs (`plan_generation_runs`) are transient status
  records, not durable data. The archive omits them and a version 2 import
  deletes them, so no stale or active run blocks Generate after a restore.
- After a version 2 import on PostgreSQL, the import moves each table's ID
  sequence past the highest restored ID. New records then get free IDs.
- A test compares the archive's table list with the database schema. A new
  table must join the archive or the list of excluded tables.
- The archive is sensitive. Only an admin can export or import it, and it
  must be stored and transferred like a credential backup.
- The import uses email as the employee identity. A matching employee gets
  the archived configuration.
- Version 1 import only creates or updates employees and uses existing
  references. Version 2 replaces the durable application data on import.
- A missing reference rejects the archive. The error gives the employee
  record, reference type, and missing name.
- The import is all-or-nothing. Any invalid record rejects the complete
  archive and makes no changes.
- The JSON schema includes a version number. A later schema can fail with a
  clear error instead of applying an incompatible archive.
- The JSON archive replaces the old CSV employee importer. Remove its page,
  routes, controller, navigation entry, translations, and tests.

## Non-goals

- No export or import of framework runtime state such as sessions, cache,
  queues, failed jobs, or migrations.
- No export or import of plan generation runs.
- No export or import of short-lived login links or remember tokens.
- No deletion of employees that are absent from an imported archive.
- No partial import or background processing.
- No spreadsheet or CSV backup format.