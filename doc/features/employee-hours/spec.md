# Employee Hours — Spec

Follows `doc/features/employee-admin/`. Roadmap phase 1.

## Problem

The employee-admin prototype gave each employee a department and a shift
preference. Both were placeholders. The team will rebuild them later in a
different form:

- Department becomes a configurable option set.
- Shift preference becomes part of a wider availability model.

Neither belongs in the current increment. The increment does need the
number of hours an employee will work. Later scheduling work reads that
number as a constraint.

## Solution

Replace the two placeholder fields with a single `weekly_hours` field on
the employee.

- A manager sets `weekly_hours` when creating or editing an employee.
- An employee changes their own `weekly_hours` from the personal page.
- The value is stored as an integer. The UI offers a fixed set of
  choices.

Allowed values: 20, 24, 28, 32, 36, 40, 44, 48. The minimum is 20. An
employee raises it in steps of 4 up to 48.

### Manager workflow

1. The manager opens the employee form.
2. The form shows name, email, and weekly hours.
3. A new employee starts at 20 hours. The manager can raise it.
4. The employees list shows name, email, weekly hours, and link status.

### Employee workflow

1. The employee opens the personal-page preview link.
2. The page shows the same fields as the manager form.
3. Name and email are read-only. Weekly hours is editable.
4. The employee picks a value and saves. The page confirms the save.

## Data model

### `employees`

Remove `department_id` (column and foreign key). Remove `shift_preference`.

Add:

- `weekly_hours` — unsigned small integer, not null, default 20.

### Departments

Remove the `departments` table, its migration, the `Department` model,
`DepartmentFactory`, and `DepartmentSeeder`. Remove the `DepartmentSeeder`
call from `DatabaseSeeder`. Remove the `department()` relation from
`Employee`.

### `employee_personal_links`

No change. The preview-link mechanism stays as built.

## Backend structure

- `Employee::WEEKLY_HOURS_OPTIONS = [20, 24, 28, 32, 36, 40, 44, 48]`.
  Add `weekly_hours` to `$fillable`. Drop `SHIFT_PREFERENCES` and
  `department_id`.
- `EmployeeController`: replace `department_id` and `shift_preference` in
  the validation rules, the index payload, and the edit payload with
  `weekly_hours`. Validation: `required`, `integer`,
  `Rule::in(Employee::WEEKLY_HOURS_OPTIONS)`. Drop `departmentOptions()`
  and the `departments` prop passed to the form.
- `PersonalPageController`: `show` returns `name`, `email`, and
  `weekly_hours`. `updatePreference` becomes `update` (route and method
  renamed), validating `weekly_hours` with the same rule.

## Frontend structure

- `resources/js/components/EmployeeFields.vue` — the shared field block:
  name, email, weekly hours. Props for the current values, the error bag,
  and a `readonlyIdentity` flag. When `readonlyIdentity` is true, the name
  and email inputs render `disabled`.
- `Employees/Form.vue` uses `EmployeeFields` with editable identity. Drops
  the department and preference selects and the `departments` prop.
- `Personal/Show.vue` uses `EmployeeFields` with `readonlyIdentity`. Keeps
  `CenteredLayout`, the `Card`, the save button, and the
  `form.recentlySuccessful` confirmation. No manager navigation.
- The weekly-hours select offers eight options. Labels read "20 hours"
  through "48 hours". A new employee's form starts at 20.
- `Employees/Index.vue`: replace the department and preference columns
  with one weekly-hours column.

## i18n

Under `resources/lang/en.json`:

- Remove `employees.field.department*`, `employees.field.preference`,
  `employees.preference.*`, `personal.preference*` and any
  department/preference labels.
- Add `employees.field.weekly_hours` and `employees.hours_option`
  (`":count hours"`).

## Key decisions

- One field, not a range. A single integer keeps the later scheduling
  contract simple. A minimum-plus-maximum pair is not needed while the
  choice set is fixed.
- Fixed choice list, stored as an integer. The database holds a plain
  number so scheduling code reads it directly. The UI constrains input to
  the eight valid values.
- Default 20, not null. Every employee has a usable value from creation.
  "Not yet set" is not a state the prototype needs.
- Shared field component. The personal page must match the manager form.
  One component stops the two from drifting apart.
- Migrations edited in place. Nothing is committed yet, so there is no
  history to preserve and no need for alter migrations.

## Non-goals

- Configurable departments.
- The wider availability and wishes model that replaces shift preference.
- A per-day or per-week hours breakdown.
- Any change to authentication, the preview-token mechanism, or the
  manager login.
- Validation of hours against contracts, rosters, or legal limits.

## Acceptance criteria

- A manager creates an employee with name, email, and weekly hours. The
  form defaults to 20.
- The form rejects a weekly-hours value outside the eight allowed numbers.
- The employees list shows the weekly-hours value for each row.
- The personal page shows name and email as read-only and weekly hours as
  editable.
- An employee saves a new weekly-hours value from the personal page and
  sees the confirmation.
- No department or shift-preference field, column, route, model, or
  translation remains.
- The suite and the front-end build pass.
