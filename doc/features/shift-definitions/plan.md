# Shift Definitions — Plan

Status: in progress — 4/5

Spec: `spec.md`. Roadmap phase 3. The Settings CRUD mirrors
`BusinessLineController` without the `move` action. The grid change
swaps `recurring_availabilities.daypart` for a `shift_id` foreign key.

- [x] 1. **Backend: Shift record and Settings CRUD.** Migration `shifts`
  (`name` string unique, `start_time` time, `end_time` time,
  timestamps). `Shift` model (`$fillable` the three fields; time casts
  to `H:i`; order-by-`start_time`-then-`name` global scope;
  `recurringAvailabilities()` hasMany; `toPayload()` →
  `{ id, name, start_time, end_time }`). `ShiftFactory`.
  `ShiftController` with `store` (name required, string, max 50,
  case-insensitive unique; `start_time`/`end_time` required,
  `date_format:H:i`; `end_time` `after:start_time`), `update` (same
  rules, unique ignoring self), `destroy` (delete). Shared `validated()`
  helper with a `Closure` uniqueness rule like
  `BusinessLineController::uniqueName`. `SettingsController@index` adds
  `shifts` as `{ id, name, start_time, end_time }` in start-time order.
  Routes behind `admin`: `POST /settings/shifts`,
  `PUT /settings/shifts/{shift}`, `DELETE /settings/shifts/{shift}`.
  Feature test `ShiftConfigTest` (guest and manager blocked; index
  payload in start-time order; create appends; blank, too-long, and
  duplicate-case name rejected; missing or malformed time rejected;
  `end_time` equal to or before `start_time` rejected; update; delete).
  Full PHP suite green.

- [x] 2. **Frontend: the Shifts Settings tab.** New
  `resources/js/components/ShiftList.vue`: a row per shift with a name
  `TextInput` and two `TimeInput` fields, each writing
  `PUT /settings/shifts/{id}` with the whole row on blur or `Enter`; a
  delete button behind a plain confirm; an add row
  (`POST /settings/shifts`) with the three fields; an empty state.
  `Settings/Index.vue` gains a `shifts` tab after `business_lines` and
  before `period`, rendering `ShiftList` with a `shifts` prop. `en.json`:
  `settings.tab.shifts` and the `shifts.*` key set (name, start_time,
  end_time, add, add labels, delete, delete_confirm, list_empty,
  error.name_taken, error.time_range, flash.added/updated/deleted).
  Vitest `ShiftList.test.js` (row edit writes the row; add writes;
  delete confirms then writes) and `SettingsIndex.test.js` (the new tab
  mounts the list on its endpoint). `npm run test` and `npm run build`
  green.

- [x] 3. **Backend: recurring availability keyed by shift.** Migration
  on `recurring_availabilities`: drop `daypart`, add `shift_id`
  (`foreignId`, `constrained`, `cascadeOnDelete`), drop the old unique
  and add `(employee_id, weekday, shift_id)`. It runs on synthetic data;
  no row migration. `RecurringAvailability`: remove `DAYPARTS`, swap
  `daypart` for `shift_id` in `$fillable`, add `shift()` belongsTo,
  `toPayload()` → `{ weekday, shift_id, level }`. `RecurringAvailabilityFactory`
  swaps `daypart` for a `Shift` factory relation.
  `SetsRecurringAvailability::setCell` takes `Shift $shift`, upserts on
  `(weekday, shift_id)`, `available` deletes. `RecurringAvailabilityController`
  and `PersonalRecurringAvailabilityController` `update` signatures take
  `Shift $shift`. Routes: the `{daypart}` segment becomes `{shift}`,
  `where('shift', '[0-9]+')`, model-bound, on both the manager and the
  personal route. `EmployeeController@edit` and `PersonalPageController@show`
  payloads add `shifts` (`{ id, name, start_time, end_time }`, start-time
  order) and change `availability` items to `{ weekday, shift_id, level }`.
  Update `RecurringAvailabilityTest` and the personal-page availability
  assertions: a cell write upserts against a shift; `available` clears
  it; deleting the shift cascades the cell; the `edit`/`show` payloads
  carry `shifts` and the new `availability` shape. Full PHP suite green.

- [x] 4. **Frontend: grid rows from shifts.** `AvailabilityGrid.vue`
  gains a `shifts` prop (`{ id, name, start_time, end_time }`), drops the
  `DAYPARTS` constant, builds rows from `shifts`, keys cells by
  `${weekday}-${shiftId}`, and writes to `${endpoint}/${weekday}/${shiftId}`.
  The row header shows `shift.name` with `start_time – end_time` below in
  `--color-text-secondary`. When `shifts` is empty it renders the
  empty-state text instead of the table. `availability` items are read by
  `shift_id`. `Employees/Form.vue` and `Personal/Show.vue` pass the
  `shifts` prop; the manager editor passes a flag or key so its empty
  state adds the "Add them on the Settings page." line. `en.json`: remove
  `availability.daypart.*`; add `availability.grid.no_shifts` and
  `availability.grid.no_shifts_manager`; change `availability.grid.cell`
  to take `:shift` instead of `:daypart`. Vitest `AvailabilityGrid.test.js`
  (one row per shift, in order; row header shows name and time; a cell
  click writes to the shift URL; empty `shifts` shows the message) and
  the `PersonalShow.test.js` / `EmployeesForm.test.js` availability
  assertions. `npm run test` and `npm run build` green.

- [ ] 5. **Docs and full checks.** `doc/roadmap.md` — phase 3 row and
  section note that shifts shipped (name and clock range; headcount and
  the workcenter link still later); phase 4 section note that the grid
  rows are the defined shifts, not dayparts. `doc/concept.md` — a Shifts
  entry under Core Data; update the employee-record grid line and the
  "dayparts map to named shifts" note. `doc/features/employee-availability/spec.md`
  — a short note that shift definitions superseded the three dayparts.
  Set this `plan.md` header to `5/5`. Run Pint, `php artisan test`,
  `npm run test`, `npm run build`, and `php artisan migrate` on the dev
  database — all green.

## Not done / deferred

- Everything under the spec's "Non-goals": workcenter assignment,
  required headcount, calendar recurrence, overnight shifts, per-Business-Line
  shift sets, `/solve` changes, and any availability-row data migration.
- Manual browser pass — AGENTS.md leaves UI verification to the user.
