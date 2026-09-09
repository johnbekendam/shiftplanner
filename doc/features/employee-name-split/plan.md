# Employee Name Split — Plan

Status: in progress — 3/4

Spec: `spec.md`. Replace `employees.name` with `first_name` + `last_name`,
keep a `name` read accessor, and redefine the `:name` mail/shift-note
placeholder as the first name. Reseed, no data migration.

The schema rename is atomic — every backend caller of `name` breaks at
once — so step 1 covers the schema, the model, and both controllers
together. Steps 2 and 3 are separable because the front end reads props
and the `:name` placeholder still resolves through the accessor until it
is changed.

- [x] 1. **Schema, model, backend, reseed.**
  Tests: `tests/Feature/EmployeeNameTest.php` — the `name` accessor
  returns `"First Last"`; `scopeSearch` matches `first_name`,
  `last_name`, or `email`. Update `EmployeeAdminTest`,
  `EmployeeBusinessLineTest`, `EmployeeImportTest`,
  `EmployeeChangeLockTest`, `PersonalPageTest`, `MailboxTest`,
  `ShiftNoteTest` — factory setups and request bodies send `first_name`
  + `last_name`; each is `required`; the `name` sort orders by
  `first_name` then `last_name`; import takes three columns (1, 2, 4 are
  errors); an over-long first or last name is a row error. Leave `:name`
  and shift-note assertions on the full name for now.
  Code: consolidated `create_employees_table` migration →
  `first_name` + `last_name` (`string`, 255, required). `Employee`:
  `fillable`, `name` accessor, widened `scopeSearch` (done).
  `EmployeeFactory` (done) and `EmployeeSeeder` produce both fields.
  `EmployeeController` — validation, `store`, `update`, `index` order,
  `SORT_COLUMNS`, payloads / `only([...])`. `EmployeeImportController` —
  parse three fields, validate `first_name` / `last_name`, create and
  update both. `AccountController@linkEmployee` splits the user's one
  name string on the first space when it creates the employee. `en.json`
  — `import.error.first_name`, `import.error.last_name`, drop
  `import.error.name`, reword `import.intro`.
  `php artisan test` green. `npm test` green (components unchanged).

- [x] 2. **Front-end form.**
  Tests: `EmployeeFields.test.js`, `EmployeesForm.test.js`,
  `PersonalShow.test.js` — the form renders "First name" and "Last name"
  (three inputs), both toggling with `readonlyIdentity`; the list column
  still shows `"First Last"`. `PersonalPageTest` asserts the split
  fields.
  Code: `Employees/Form.vue` and `Personal/Show.vue` form state →
  `first_name`, `last_name`. `EmployeeFields.vue` renders two stacked
  fields, both honoring `readonlyIdentity` and `disabled`.
  `PersonalPageController` payload carries the split fields. `en.json` —
  `employees.field.first_name`, `employees.field.last_name`, drop
  `employees.field.name`; keep `employees.column.name`.
  Full suite green.

- [x] 3. **`:name` becomes the first name.**
  Tests: `MailboxTest` and `ShiftNoteTest` — the personal-link `:name`
  placeholder and `shiftNoteHtml` resolve to the first name only;
  `Message.recipient_name` still stores the full name.
  Code: `PersonalLinkMessage` fills `:name` from `first_name`; preview
  sample uses a first name. `EmployeeController` and
  `PersonalPageController` call `shiftNoteHtml($employee->first_name)`.
  `en.json` — reword `mailbox.compose.body_hint`, `shifts.note_hint`,
  `mailbox.preview.sample_name`; `personal_page_link.body` keeps
  `:name`.
  Full suite green.

- [ ] 4. **Full suite, lint, build, docs.**
  `php artisan test` and `npm test` green. `vendor/bin/pint --dirty`
  clean. `npm run build` green. `php artisan migrate:fresh --seed`
  against a scratch database. Mark `plan.md` `4/4`.

## Not done / deferred

- Everything under the spec's Non-goals: `users.name` untouched, no
  preferred name / nickname, no mononym or nullable last name, no
  backfill, no `:firstname` token, no split of the list column.
