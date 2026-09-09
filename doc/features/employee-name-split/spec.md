# Employee Name Split — Spec

Split the single `employees.name` column into `first_name` and
`last_name`, so mail and the shift note can address a person by first
name.

## Problem

An employee has one `name` string. Messages that greet the recipient can
only use the whole name ("Hi Anna Maria de Vries,"). The planner wants a
first-name greeting. There is no official name data yet, so we can reseed
the database and keep no existing rows.

## Solution

### Schema

Replace `employees.name` with two required `string` columns,
`first_name` and `last_name` (`max:255` each). Reseed; no data
migration.

Migration order: fold the change into the consolidated
`create_employees_table` migration rather than adding a follow-up
migration.

### Model — `Employee`

- `fillable`: `name` is replaced by `first_name`, `last_name`.
- New accessor `name` returns `"{first_name} {last_name}"`. Every place
  that shows a full name reads this accessor and needs no change.
- `scopeSearch` matches `first_name` OR `last_name` OR `email`.

### Employee admin

- `EmployeeFields.vue`: the one "Name" field becomes two stacked fields,
  "First name" then "Last name". Both honour `readonlyIdentity` and
  `disabled` exactly as the name field did, so the personal page shows
  them read-only.
- `Employees/Form.vue` form state: `name` is replaced by `first_name`,
  `last_name`.
- `EmployeeController`: validation, `store`, `update`, and the Inertia
  payloads carry both fields. The edit payload's `only([...])` list
  swaps `name` for the two fields.
- `Employees/Index.vue` and `EmployeeController@index`: the list keeps
  one "Name" column showing the `name` accessor. The `name` sort key
  maps to `ORDER BY first_name, last_name`. Default sort stays `name`.

### CSV import — `EmployeeImportController`

Three columns: first name, last name, email.

- Column-count check `2 → 3`. `import.error.columns` is unchanged (it
  uses `:count`).
- Row validation: `first_name` and `last_name` each
  `required|string|max:255`. Add `import.error.first_name` and
  `import.error.last_name`; drop `import.error.name`.
- `import.intro` text updated to name three columns.
- Match stays by `email`. On a match, update `first_name` / `last_name`
  when either differs.

### Mail and shift note — `:name` becomes the first name

`:name` is redefined to the first name wherever it is filled per
employee:

- `PersonalLinkMessage`: `:name => $employee->first_name`.
  `mailbox.preview.sample_name` becomes a first-name sample.
- `PlanningSettings::shiftNoteHtml(...)` is called with
  `$employee->first_name` at both call sites (`EmployeeController`,
  `PersonalPageController`).
- `Message.recipient_name` keeps the full name (`$employee->name`).
- Copy: `mailbox.compose.body_hint` and `shifts.note_hint` reword
  `:name` to say "first name". The `personal_page_link.body` template
  text keeps `:name` (now the first name).

### Reseed

`EmployeeFactory` and `EmployeeSeeder` produce `first_name` and
`last_name`.

## Key decisions

- **Two required columns, no nullable last name.** The prototype has no
  mononym data and none is expected. A nullable `last_name` would add
  edge cases to the form, import, and the `name` accessor for no gain.
- **`name` stays as a read accessor.** Most of the app shows a full
  name — list, mailbox recipient, dashboards, personal page. An
  accessor keeps that untouched and gives one place to change the
  format later.
- **`:name` is redefined, no new `:firstname` token.** The only current
  use of `:name` is a greeting. A second token to teach and document is
  not worth it. The `Message` record still stores the full
  `recipient_name` for the mailbox list.
- **Three explicit import columns.** A split-on-space rule is ambiguous
  for compound given names and surnames. An explicit column per part is
  unambiguous, and someone prepares the CSV by hand anyway.
- **Sort by first name, then last name.** The list shows "First Last",
  so this matches the visible order.
- **Fold into the consolidated migration.** We just squashed the
  migrations to per-table creates, and we expect a reseed. A follow-up
  migration has no reason to exist.

## Non-goals

- `users.name` is untouched.
- No preferred name, nickname, or display-name override.
- No mononym or nullable-last-name handling.
- No backfill or migration of existing employee rows.
- No new `:firstname` placeholder.
- No split of the list into separate first/last columns.
