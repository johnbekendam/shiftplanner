# Employee Hours — Plan

Status: in progress — 2/4

Spec: `spec.md`. Roadmap phase 1.

Steps 1-3 of the first draft could not each leave the suite green on their
own — deleting the `Department` model breaks `EmployeeController` at once.
The backend change is one atomic slice.

- [x] 1. **Backend: schema, model, controllers, routes.** Edit the
  `employees` migration in place: drop `department_id` and
  `shift_preference`, add `unsignedSmallInteger('weekly_hours')->default(20)`.
  Delete the `departments` migration, `Department` model,
  `DepartmentFactory`, `DepartmentSeeder`, and its `DatabaseSeeder` call.
  `Employee`: `$fillable` swap to `weekly_hours`, drop `SHIFT_PREFERENCES`
  and `department()`, add `WEEKLY_HOURS_OPTIONS = [20,24,28,32,36,40,44,48]`.
  `EmployeeFactory`: drop department + preference, set `weekly_hours` to a
  random allowed value. `EmployeeController`: replace `department_id` +
  `shift_preference` with `weekly_hours`
  (`required|integer|Rule::in(Employee::WEEKLY_HOURS_OPTIONS)`) in
  `validated()`, `index`, and `edit`; drop `departmentOptions()` and the
  `departments` prop. `PersonalPageController`: `show` returns `name`,
  `email`, `weekly_hours`; `updatePreference` → `update`, validates
  `weekly_hours`. Route `personal.preference` → `personal.update`
  (`PUT /personal/{token}`). Update `EmployeeAdminTest` and
  `PersonalPageTest` for the new field, out-of-set rejection, and the new
  `show` payload. Full PHP suite green.

- [x] 2. **Shared field component + manager form + list + i18n.** Add
  `resources/js/components/EmployeeFields.vue` (name, email, weekly-hours
  select; `readonlyIdentity` prop → name/email `disabled`; reads
  `form.errors`). Options built from the eight values, labels via
  `employees.hours_option`. Rewrite `Employees/Form.vue` to use it
  (editable identity, `weekly_hours` default 20 on create, no
  department/preference). Update `Employees/Index.vue` columns
  (department + preference → weekly hours). `en.json`: drop
  `employees.column.department`, `employees.column.preference`,
  `employees.preference.*`, `employees.field.department*`,
  `employees.field.preference`; add `employees.column.weekly_hours`,
  `employees.field.weekly_hours`, `employees.hours_option`; retarget the
  `personal.*` strings at hours. Vitest: `EmployeeFields` renders eight
  options, disables name/email when `readonlyIdentity`, binds
  `weekly_hours`. Front-end suite green.

- [ ] 3. **Personal page view.** Rewrite `Personal/Show.vue` to use
  `EmployeeFields` with `readonlyIdentity`, on `CenteredLayout`, with the
  `Card`, save button, and `form.recentlySuccessful` confirmation, posting
  to `personal.update`. Vitest if the page has testable logic. Front-end
  suite green.

- [ ] 4. **Final checks.** Confirm no department/preference key, string,
  or reference is left anywhere. Run Pint, `php artisan test`,
  `npm run test`, `npm run build` — all green.

## Not done / deferred

- Manual browser pass — AGENTS.md leaves UI verification to the user.
- Configurable departments and the availability model that replaces shift
  preference (spec "Non-goals").
