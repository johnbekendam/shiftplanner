# Employee Availability — Plan

Status: in progress — 3/5

Spec: `spec.md`. Roadmap phase 4 (holiday half).

- [x] 1. **Backend: holidays table, model, endpoints.** Migration
  `employee_holidays` (`employee_id` FK cascade, `start_date` date,
  `end_date` date, `note` nullable, timestamps). `EmployeeHoliday` model
  (`$fillable`, `casts` dates, `belongsTo` Employee). `Employee::holidays()`
  hasMany, ordered by `start_date`. `EmployeeHolidayFactory`. New
  `EmployeeHolidayController` with `store` + `destroy`, validating
  `start_date` (required, date), `end_date` (required, date,
  `after_or_equal:start_date`), `note` (nullable, string, max 255). Manager
  routes `POST /employees/{employee}/holidays` and
  `DELETE /employees/{employee}/holidays/{holiday}` behind `auth`; personal
  routes `POST /personal/{token}/holidays` and
  `DELETE /personal/{token}/holidays/{holiday}` token-only, reusing
  `PersonalPageController` or a sibling that resolves the token and scopes
  the holiday to that employee. `EmployeeController::edit` and
  `PersonalPageController::show` add `holidays` (id, start_date, end_date,
  note) to their Inertia payloads. Feature tests: manager add + validation
  (`end_date` before `start_date`, missing dates) + delete + list in
  payload; personal add + delete + scoped-to-token; deleting another
  employee's holiday by wrong route 404s. Full PHP suite green.

- [x] 2. **`Tabs` component.** `resources/js/components/ui/Tabs.vue` — a
  tab bar for a `Card` header. Props: `tabs` (array of `{ value, label }`),
  `modelValue`. Emits `update:modelValue`. Uses `--color-tab-*` tokens
  (active/inactive/hover bg, text, border, separator). Vitest: renders one
  button per tab, marks the active one with the active tokens, emits the
  value on click.

- [x] 3. **Manager editor: tabbed card + Availability.** Add
  `resources/js/components/HolidayList.vue` — a table of holiday rows
  (start date, end date, note, delete) plus an add-row form using
  `DateInput` + `TextInput` and `ButtonPrimary`/`ButtonSecondary`. Props:
  `holidays`, `endpoint` (base URL). Add and delete post to
  `${endpoint}` / `${endpoint}/${id}` with Inertia `router`, reload on
  success. Rewrite `Employees/Form.vue`: wrap `EmployeeFields` and
  `HolidayList` in a `Card` whose header is `Tabs` (Details, Availability).
  Details tab keeps the existing save button. `en.json` keys under
  `availability.*` (tab labels, column headers, add-row labels, delete,
  empty). Vitest: `HolidayList` renders rows, submits an add to the given
  endpoint, deletes a row; `Employees/Form` shows both tabs and switches.
  Front-end suite green.

- [ ] 4. **Personal page: tabbed card + Availability.** Rewrite
  `Personal/Show.vue`: same `Card` + `Tabs` (Details, Availability).
  Details tab keeps `EmployeeFields` with `readonly-identity` and the hours
  save. Availability tab renders `HolidayList` pointed at
  `/personal/${token}/holidays`. Vitest: both tabs render, `HolidayList`
  gets the token endpoint, identity stays read-only. Front-end suite green.

- [ ] 5. **Docs + checks.** Update `doc/roadmap.md` phase 4 row (holidays
  shipped, recurring model specified in this feature) and `doc/concept.md`
  (employee record carries holidays; availability model partly resolved).
  Run Pint, `php artisan test`, `npm run test`, `npm run build` — all
  green.

## Not done / deferred

- All recurring-availability code (spec Part 2 is design only).
- Manual browser pass — AGENTS.md leaves UI verification to the user.
- Everything under the spec's "Non-goals".
