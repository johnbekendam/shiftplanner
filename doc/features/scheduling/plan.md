# Scheduling — Plan

Status: in progress — 4/7

Spec: `spec.md`. Every write on this page is immediate — no
explicit-save diffing, no `TabSaveBar`. A shared
`App\Services\SchedulingEligibility` class holds the
holiday/unavailable/overlap checks once, used by both the
eligible-employee lookup (to filter) and the assignment store endpoint
(to re-validate).

- [x] 1. **Backend: schema, read-only grid, nav.** Migration
  `shift_assignments` (`employee_id`, `workcenter_id`, `shift_id`
  `constrained` cascade on delete each; `date`; `fixed` boolean default
  `false`; timestamps; unique on `(employee_id, workcenter_id,
  shift_id, date)`). `ShiftAssignment` model (`belongsTo` on all three;
  `toPayload()` → `{ id, employee_id, employee_name, fixed }`).
  `ShiftAssignmentFactory`. `SchedulingController@index`: query params
  `workcenter_id` (default the first active workcenter by position, or
  `null` payload if none exist) and `week_start` (`Y-m-d`, defaults to
  the current week's Monday, normalized to that week's Monday if a
  mid-week date is passed). Renders `Inertia::render('Scheduling', [...])`
  with `workcenters` (`{ id, name }`, active only), the selected
  `workcenter_id`, `week_start`, `days` (7 `Y-m-d` strings), `shifts`
  (the selected workcenter's attached shifts, start-time order,
  `{ id, name, start_time, end_time }`), and `cells` (one entry per
  shift × day: `{ shift_id, date, spots, assignments: [...] }`, `spots`
  from `Workcenter::spotsFor()`). Route `GET /scheduling` behind
  `admin`. `AppLayout.vue` gains `{ label: __('nav.scheduling'), href:
  '/scheduling', icon: 'calendar-days' }` after Workcenter Shifts.
  `AppLayoutNav.test.js` updated. `en.json`: `nav.scheduling`. Feature
  test `SchedulingIndexTest` (guest and manager blocked; payload shape
  for a workcenter with shifts and existing assignments; defaults to
  the first active workcenter; `week_start` normalizes to that week's
  Monday; an empty-workcenters payload; asserts `component('Scheduling',
  false)` since the page component ships in step 5, not yet on disk).
  Full PHP suite green (437 passed). Pint clean.

- [x] 2. **Backend: assignment writes.** `App\Services\SchedulingEligibility`:
  `isOnHoliday(Employee, Carbon)`, `isUnavailable(Employee, int $weekday,
  Shift)`, `isNotPreferred(Employee, int $weekday, Shift)`,
  `hasOverlap(Employee, Carbon, Shift, ?int $excludeAssignmentId)` (any
  other same-date assignment whose shift's clock range intersects this
  one's). `ShiftAssignmentController@store` — validates
  `employee_id`/`workcenter_id`/`shift_id` exist, `date` is a valid
  date; rejects (`ValidationException`) a full cell (assignee count
  `>=` `Workcenter::spotsFor()`), a duplicate pair, a holiday or
  unavailable employee, or an overlap; creates with `fixed: false`.
  `@updateFixed` — `{ fixed }` boolean, updates. `@destroy` — deletes
  regardless of `fixed`. Routes behind `admin`:
  `POST /scheduling/assignments`,
  `PUT /scheduling/assignments/{shiftAssignment}`,
  `DELETE /scheduling/assignments/{shiftAssignment}`. Feature test
  `ShiftAssignmentTest` (guest and manager blocked on store, update,
  and destroy; store creates an assignment; store rejects a full cell,
  a duplicate pair, a holiday employee, an unavailable employee, and a
  same-date overlapping assignment; a `not_preferred` employee is
  accepted; a non-overlapping same-day shift is accepted; updateFixed
  toggles; destroy removes a fixed assignment with no extra step).
  Full PHP suite green (451 passed). Pint clean.

- [x] 3. **Backend: spot-count writes.** `ScheduleSpotController@update`
  (`Workcenter $workcenter, Shift $shift, string $date` route-bound) —
  `{ spots }` (`integer|min:0`), rejects a value below the cell's
  current assignee count (`ValidationException`), upserts a
  `WorkcenterShiftDateOverride` row. `@destroy` — deletes the override
  row for that `(workcenter, shift, date)`, reverting to the weekday
  default (a no-op, not an error, if no override exists). Routes
  behind `admin`: `PUT /scheduling/spots/{workcenter}/{shift}/{date}`,
  `DELETE /scheduling/spots/{workcenter}/{shift}/{date}`, `date`
  constrained to `\d{4}-\d{2}-\d{2}`. Fixed a latent bug found along
  the way: `WorkcenterShiftDateOverride::$casts` had `date` cast to
  plain `date`, which stores a full `H:i:s` datetime and broke
  `updateOrCreate`'s plain-string match — changed to `date:Y-m-d`,
  matching `EmployeeHoliday`. Feature test `ScheduleSpotTest`
  (guest and manager blocked; update creates an override; update on an
  already-overridden date replaces it; update rejects a value below
  the assignee count; destroy removes an override; destroy is a no-op
  on a date with no override). Full PHP suite green (459 passed). Pint
  clean.

- [x] 4. **Backend: eligible-employee lookup.**
  `EligibleEmployeeController@index` — query params `workcenter_id`,
  `shift_id`, `date`; returns every employee except one already
  assigned to that exact cell, on holiday, or marked `unavailable` for
  that weekday/shift (via `SchedulingEligibility`), or with a
  same-date overlapping assignment; each returned entry is
  `{ id, name, not_preferred: bool }`, name-ordered. A plain JSON
  response (`response()->json()`), not an Inertia render. Route
  `GET /scheduling/eligible-employees` behind `admin`. Feature test
  `EligibleEmployeeTest` (guest and manager blocked; excludes an
  already-assigned, a holiday, an unavailable, and a same-date
  overlapping employee; includes a `not_preferred` employee with the
  flag set; includes an otherwise-unconstrained employee). Full PHP
  suite green (467 passed). Pint clean.

- [ ] 5. **Frontend: page skeleton, read-only grid.** New
  `resources/js/pages/Scheduling.vue`: a Workcenter `SelectInput`
  (`router.get('/scheduling', { workcenter_id })` on change,
  `preserveState`), week navigation (`router.get` with a shifted
  `week_start`, and a "this week" reset), the date range heading, and
  a table (shift rows × 7 day columns) rendering each cell's `spots`
  count and assignee names as plain text — no editing yet. Empty
  states: no active workcenters, and a workcenter with no attached
  shifts. `en.json`: `scheduling.*` (title, workcenter label, this
  week, date-range join text, no_workcenters, no_shifts). Vitest
  `Scheduling.test.js` (renders the grid from the `cells` prop;
  switching the workcenter select navigates with the new
  `workcenter_id`; week navigation buttons navigate with a shifted
  `week_start`; both empty states). `npm run test` and `npm run build`
  green.

- [ ] 6. **Frontend: cell interactivity.** Spot count becomes
  click-to-edit: a `NumberInput` on click, `PUT
  /scheduling/spots/{workcenter}/{shift}/{date}` on blur (via
  `putAsync`, reloading the page's `cells`), reverting the field
  without a request if the response is a validation error; a reset
  icon shown only on an overridden cell fires the `DELETE`. Each
  assignee row gets a pin toggle (`PUT
  /scheduling/assignments/{id}` `{ fixed }`) and a remove button
  (`DELETE /scheduling/assignments/{id}`, no confirm). An "Add" button
  per cell opens a popover: on open, `axios.get('/scheduling/eligible-employees',
  { params: { workcenter_id, shift_id, date } })`; a `SearchInput`
  filters the returned list client-side (name match); a
  `not_preferred` entry shows a warning badge; clicking a name fires
  `POST /scheduling/assignments` and closes the popover. Vitest
  additions to `Scheduling.test.js` (editing a spot count fires the
  PUT and reflects the reload; a rejected edit reverts the field; the
  reset icon appears only on an overridden cell and fires the DELETE;
  toggling the pin fires the PUT; remove fires the DELETE with no
  confirm; opening Add fetches eligible employees and lists them,
  `not_preferred` flagged; clicking one fires the POST and closes the
  popover). `npm run test` and `npm run build` green.

- [ ] 7. **Docs and full checks.** `doc/roadmap.md` — phase 3 row and
  section note that scheduling shipped (manual assignment, the fixed
  flag, per-date capacity overrides now live here; automatic planning
  still later). `doc/concept.md` — a Scheduling entry under Core Data;
  the Workcenters entry's "not edited on this page" override line
  updated to point here. Set this `plan.md` header to `7/7`. Run Pint,
  `php artisan test`, `npm run test`, `npm run build`, and
  `php artisan migrate` on the dev database — all green.

## Not done / deferred

- Everything under the spec's non-goals: rule-based automatic
  planning, the `/solve` contract, competence gating, the
  employee-facing schedule view, bulk actions, manager-level access.
- Manual browser pass — AGENTS.md leaves UI verification to the user.
