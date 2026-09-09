# Employee Import — Spec

An admin-only page that loads employees from a two-column CSV (name,
email) in one action, and is safe to re-run on the same file.

## Problem

Employees are added one at a time through `Employees/Form.vue`. Onboarding
a group means repeating the form for each person. An admin has the roster
as a spreadsheet already: a name and an email per row. They need to load
that batch in one step, and re-upload a corrected file without creating
duplicates.

## Solution

### Route + access

Admin-only, in the `Route::middleware('admin')` group:

- `GET  /import` → `EmployeeImportController@index` — renders the page.
- `POST /import` → `EmployeeImportController@store` — parses, validates,
  writes; returns JSON.

Path is `/import`, not `/employees/import` — the sidebar `isActive` match
is a `startsWith(href + '/')`, so nesting it under `/employees` would light
up the Employees item too.

### Sidebar

`AppLayout.vue` `navItems`, inside the `if (isAdmin.value)` block: a new
`{ label: __('nav.import'), href: '/import', icon: 'upload' }`. The
`upload` icon (`ArrowUpTrayIcon`) is already in `Icon.vue`'s `iconMap`.

### Page — `resources/js/pages/EmployeeImport.vue`

`AppLayout` + `Head` + `Card`, matching `ThemeBuilder.vue`'s shell.

- A drop zone: a styled `<div>` with `@dragover.prevent` / `@drop.prevent`
  that reads `event.dataTransfer.files[0]`. It also composes the existing
  `FileInput` (`accept=".csv,text/csv"`) for click-to-browse via its
  `trigger` slot prop. No new input component.
- On file pick/drop, POST it as multipart (`FormData`, field `file`) with
  `router` / `axios`; keep the response on the page.
- Result panel below the zone:
  - success → `__('import.result.summary', { created, updated })`
    ("N created, M updated").
  - failure → the list of row errors, each `line N: <reason>`. Nothing was
    written.
- The drop zone stays after either outcome for the next file.

### Parsing — `EmployeeImportController@store`

Native PHP, no new dependency.

1. Validate the upload: `required|file|mimes:csv,txt|max:2048`
   (2048 KB). Reject other types with a single file-level error.
2. Read contents. Strip a leading UTF-8 BOM (`\xEF\xBB\xBF`).
3. Split into lines. Detect the delimiter from the header line: `;` if it
   contains more semicolons than commas, else `,`.
4. Drop the first line (header) unconditionally.
5. Reject the file if there are no data rows left.
6. Per data line, `str_getcsv($line, $delimiter)`:
   - exactly two fields, else row error `line N: expected 2 columns, got X`.
   - `trim()` both. `name` → `required, max:255`. `email` →
     `required, valid email, max:255`. Row error on failure.
   - lower-cased email seen earlier in this file → row error
     `line N: duplicate email <email>`.
7. **All-or-nothing.** If the row-error list is non-empty after the whole
   file, write nothing and return `422` with `{ errors: [...] }`.

### Writes

Match on `email` (the column is already `unique`). Case: compare on the
raw submitted value; no forced lower-casing of stored emails (keeps parity
with `EmployeeController`, which stores as typed).

- No match → `Employee::create(['name' => ..., 'email' => ...])`.
  `weekly_hours` falls to the column default (20); `business_line_id`
  stays null.
- Match → if `name` differs, `$employee->update(['name' => ...])`, count
  as updated; else untouched (not counted).

Return `{ created: int, updated: int }`.

### i18n — `resources/lang/en.json`

- `nav.import` — "Import"
- `import.title`, `import.heading`, `import.intro` (one line on the
  format: header row skipped, two columns name + email)
- `import.dropzone` — "Drop a CSV here or click to browse"
- `import.result.summary` — ":created created, :updated updated"
- `import.result.errors_heading` — "Import failed — fix these rows and
  upload again:"
- `import.error.columns`, `import.error.name`, `import.error.email`,
  `import.error.duplicate`, `import.error.empty`, `import.error.file`

## Key decisions

- **Upsert on email, name is the only mutable field.** "Idempotent based
  on email" with a name correction in the file should land. Re-running an
  unchanged file is a pure no-op.
- **All-or-nothing, no partial import.** A rejected file returns a row
  list the admin fixes and re-uploads. Half-loaded state with a separate
  error report is harder to reason about and to undo. No DB transaction
  needed — validation completes before the first write.
- **Admin-only.** Matches the other standalone sidebar tools (Mailbox,
  Users, Settings). Bulk mutation is heavier than the per-row form, which
  stays open to managers.
- **`/import`, top-level.** Avoids the sidebar active-state collision with
  `/employees`.
- **Comma or semicolon, auto-detected.** European Excel exports use `;`.
  Detection is per-file from the header line; no user setting.
- **Exactly two columns, enforced.** A row with a stray comma or an extra
  column is a mistake worth surfacing, not silently trimming.
- **In-file duplicate email is a row error.** Two names for one email in
  one file is ambiguous; the file is wrong and the admin should say which
  row wins.
- **No new CSV dependency.** Two columns and `str_getcsv` do not justify
  `league/csv` or Laravel Excel.
- **`weekly_hours` from the column default.** The CSV has no hours; 20 is
  the existing default and `MIN_WEEKLY_HOURS`. An admin sets real hours
  later in the form.

## Non-goals

- No `weekly_hours`, `business_line`, competence, or role columns.
- No delete/deactivate of employees missing from the file.
- No partial import, no downloadable error report, no template-file
  download.
- No queued/background processing — the request is synchronous.
- No manager access.
- No import history or audit of who imported what.
- No user-account creation for imported employees (that flow is separate).
- No `xlsx` or other spreadsheet formats.
