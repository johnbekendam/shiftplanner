# Employee Details Layout — Plan

Status: in progress — 3/5

Spec: `spec.md`. Business line editable on the personal page, shared
detail fields reordered, weekly hours moved to the Availability tab,
Details tabs auto-save, admin create is a single card.

- [x] 1. **Backend: admin redirects + personal business-line endpoint.**
  Tests: `EmployeeAdminTest`, `EmployeeBusinessLineTest`,
  `EmployeeChangeLockTest` — `store` and `update` redirect to
  `/employees/{id}/edit`. `PersonalPageTest` — `show` payload carries
  `employee.business_line_id` and a `businessLines` list; `PUT
  /personal/{token}` with `business_line_id` sets it, accepts `null`,
  rejects an unknown id, and still works with only `weekly_hours`.
  `EmployeeChangeLockTest` — the same PUT is forbidden when changes are
  off.
  Code: `EmployeeController@store` / `@update` redirect to the edit page.
  `PersonalPageController@show` adds `business_line_id` to the `employee`
  payload and `businessLines` (`{ id, abbreviation }`).
  `PersonalPageController@update` validates `business_line_id`
  (`nullable`, `integer`, `exists:business_lines,id`) and persists it
  alongside `weekly_hours`.
  `php artisan test` green. `npm test` green (front end untouched).

- [x] 2. **Move weekly hours to the Availability tab; reorder the shared
  fields.**
  Tests: `EmployeeFields.test.js` — no weekly-hours select. First and
  Last name share one row. The order is first/last, email, business
  line. New `WeeklyHoursField.test.js` — renders the 20–48 options plus
  the below-minimum option, emits on change. `EmployeesForm.test.js` and
  `PersonalShow.test.js` — the weekly-hours field sits on the
  Availability tab and auto-saves on change. The personal page passes
  `businessLines` to `EmployeeFields` and seeds `business_line_id`.
  Code: `EmployeeFields.vue` drops the weekly-hours field, puts First
  and Last name on a two-column grid row, orders the fields per the
  spec. New `WeeklyHoursField.vue` (labeled select, option list moved
  from `EmployeeFields`). `Employees/Form.vue` (edit) and
  `Personal/Show.vue` render it at the top of the Availability tab with
  auto-save. `Personal/Show.vue` receives `businessLines` and adds
  `business_line_id` to the form and its save transform.
  Full suite green.

- [x] 3. **Admin create is a single card.**
  Tests: `EmployeesForm.test.js` — on create the component shows the
  Details fields with a Create button and no tab bar and no other
  panels; on edit the tabbed layout is unchanged.
  Code: `Employees/Form.vue` renders only the Details card when
  `employee` is null. `en.json` — `employees.action.create`.
  Full suite green.

- [ ] 4. **Auto-save the Details tabs.**
  Tests: `EmployeesForm.test.js` — on edit, a committed Details field
  fires `form.put`. Switching away from the Details tab with unsaved
  changes fires `form.put`. The Save button stays.
  `PersonalShow.test.js` — changing the business line fires the save.
  Leaving the Details tab with a dirty form fires the save. The Save
  button stays when editable.
  Code: `Employees/Form.vue` and `Personal/Show.vue` — a per-field
  commit handler and a `tab` watcher that saves a dirty form when it
  leaves `details`.
  Full suite green.

- [ ] 5. **Full suite, lint, build, docs.**
  `php artisan test` and `npm test` green. `vendor/bin/pint --dirty`
  clean. `npm run build` green. Mark `plan.md` `5/5`.

## Not done / deferred

- Everything under the spec's Non-goals: the Employees list table is
  unchanged, the availability sub-sections save as before, no auto-save
  on admin create, `users` untouched, no business-line management UI.
