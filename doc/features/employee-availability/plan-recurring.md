# Employee Availability — Recurring Grid Plan

Status: in progress — 3/4

Spec: `spec.md`, Part 2. Follows `plan.md` (holidays, done).

- [x] 1. **Backend: table, model, endpoints.** Migration
  `recurring_availabilities` (`employee_id` FK cascade, `weekday`
  unsigned tiny int, `daypart` string, `level` string, unique
  (`employee_id`, `weekday`, `daypart`), timestamps).
  `RecurringAvailability` model (`$fillable`, `DAYPARTS` and `LEVELS`
  consts, `belongsTo` Employee). `Employee::recurringAvailabilities()`
  hasMany. `RecurringAvailabilityFactory`. A `RecurringAvailabilityController`
  (manager) and `PersonalRecurringAvailabilityController` (token), each
  one `update` action: `PUT .../availability/{weekday}/{daypart}` with
  `level` in `available` | `not_preferred` | `unavailable`. `available`
  deletes any row for that cell. The others `updateOrCreate`. Routes
  constrain `weekday` to `1`–`7` and `daypart` to the three values.
  `EmployeeController::edit` and `PersonalPageController::show` add an
  `availability` array of `{ weekday, daypart, level }`. Feature tests:
  set `not_preferred`, change to `unavailable` (one row, not two), set
  `available` (row gone), invalid weekday and daypart rejected, personal
  route scoped to its token, `edit`/`show` payload shape. Full PHP suite
  green.

- [x] 2. **`AvailabilityGrid` component.** Add
  `resources/js/components/AvailabilityGrid.vue`. Props: `availability`
  (the array from the payload) and `endpoint` (base URL). Renders a
  3-by-7 grid: daypart rows, weekday columns, a header row, and a legend.
  Each cell is a button coloured by state through
  `--color-badge-standard-*` (available), `--color-badge-warning-*` (not
  preferred), `--color-badge-error-*` (unavailable). A click cycles
  `available` → `not_preferred` → `unavailable` → `available` and sends
  `router.put(`${endpoint}/${weekday}/${daypart}`, { level }, {
  preserveScroll: true, preserveState: true })`. New `availability.grid.*`,
  `availability.daypart.*`, `availability.weekday.*`, and
  `availability.state.*` keys in `en.json`. Vitest: 21 cells, initial
  state from the prop, a click sends the right URL and next level, and
  `unavailable` cycles to `available`. Front-end suite green.

- [x] 3. **Wire the grid into both tabs.** In the Availability tab of
  `Employees/Form.vue`, put `AvailabilityGrid` above `HolidayList` with a
  subheading for each. Pass `/employees/${employee.id}/availability` and
  the `availability` prop. Do the same in `Personal/Show.vue` with
  `/personal/${token}/availability`. Vitest: both pages mount the grid
  with the right endpoint. Front-end suite green.

- [ ] 4. **Docs + checks.** Update `plan.md` deferred note, `plan.md`'s
  parent references, `doc/roadmap.md` phase 4 row, and `doc/concept.md`
  (the employee record carries a recurring availability grid). Run Pint,
  `php artisan test`, `npm run test`, `npm run build` — all green.

## Not done / deferred

- The `/solve` service and daypart-to-shift mapping (needs phase 3).
- Fairness weights for `not_preferred` cells.
- Manual browser pass — AGENTS.md leaves UI verification to the user.
