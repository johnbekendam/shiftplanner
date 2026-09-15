# Scheduling Week Drilldown — Plan

Status: done — 3/3

Spec: `spec.md`. Every write on this page is immediate, via Inertia's
`back()` pattern — no explicit-save diffing. The write endpoints
already existed (`ShiftAssignmentController`, `ScheduleSpotController`,
`EligibleEmployeeController`) and were fully tested; this plan only
wires new frontend up to them and extends `SchedulingController@index`.

- [x] 1. **Backend: selected date and week-cells payload.**
  `SchedulingController@index` gains `resolveSelectedDate()` (an
  optional `date` query param, `Y-m-d`; defaults to today if the
  browsed month is the current month, else the 1st of that month) and
  computes `weekStart` (the Monday of that date's week). Extracted
  `$attachments` (`workcenter_shift` pivot rows) and `$capacities` once
  in `index()`, shared by both `coverage()` (month, unchanged
  behavior) and the new `weekCells()` (all `(workcenter, shift, date)`
  triples for every attached pair across the week's 7 days, including
  zero-spot days — unlike `coverage()`, nothing is skipped) and a
  shared `spotsFor()` helper (override wins over weekday capacity).
  `shifts` payload gains `start_time`/`end_time` (needed for the shift
  table headers). New response props: `date`, `weekStart`,
  `weekCells` (`{ workcenter_id, shift_id, date, spots, overridden,
  assignments: [{ id, employee_id, employee_name, fixed }] }`).
  9 new `SchedulingIndexTest` cases: default date/week-start, a
  non-current month's default, an explicit date's week, a week
  spanning two months, full assignment/overridden detail, a zero-spot
  day still appearing, a non-attached shift excluded, archived
  workcenters excluded, exactly 7 entries per attached shift. Full PHP
  suite green (498 passed; 4 pre-existing, unrelated
  `GraphTransportTest` failures from a missing `composer/ca-bundle`
  dependency). Pint clean.

- [x] 2. **Frontend: `ShiftWeekTable.vue` and `WorkcenterScheduleCard.vue`.**
  New `resources/js/components/scheduling/ShiftWeekTable.vue`: the
  spot-rows × day-columns matrix for one (workcenter, shift) pair
  across a week. Row count = `max(spots)` across the 7 cells. Per
  cell: filled → employee name (fixed-first, then alphabetical),
  click reveals inline pin-toggle/remove; empty within that day's
  spot count → "Open", click opens the eligible-employee popover
  (`GET /scheduling/eligible-employees`, `axios`) and assigns on
  click (`POST /scheduling/assignments`); empty beyond that day's
  spot count → a plain dash. Each day column header carries a
  click-to-edit spot count (`PUT /scheduling/spots/{wc}/{shift}/{date}`)
  with a reset icon when overridden (`DELETE`, same URL). All writes
  via `putAsync`/`postAsync`/`deleteAsync`
  (`@/utils/inertiaAsync`), matching the pre-existing endpoints'
  `back()` response pattern. New
  `resources/js/components/scheduling/WorkcenterScheduleCard.vue`:
  header = workcenter name; renders one `ShiftWeekTable` per schedule
  entry under a shift name + time-range heading. Deleted
  `SchedulingCell.vue` and its test — fully superseded, its compact
  "count + list" shape doesn't fit the matrix; its write-call pattern
  was the reference for the new components, not reused code.
  `en.json`: `scheduling.open_spot`. Vitest `ShiftWeekTable.test.js`
  (6 cases: spot counts and reset icon; filled/open/dash cell
  rendering; spot commit fires PUT, unchanged fires nothing; reset
  fires DELETE; pin toggle and remove; assign popover fetch, list,
  and POST-and-close) and `WorkcenterScheduleCard.test.js` (header
  name, one table per shift with its name/time range).

- [x] 3. **Frontend: wire into `Scheduling.vue`.** `Calendar` gets
  `enable-day-selection="true"` and an `initial-day` computed from
  the `date` prop (so a fresh page load's visual selection matches
  the server-resolved date; a live click already updates Calendar's
  own internal selection instantly, independent of the prop). New
  props `date`, `weekStart`, `weekCells`. `onCalendarChange` now also
  compares the emitted day (built into a `Y-m-d` string) against
  `props.date`, navigating with `{ year, month, date }` whenever
  either differs — covers month nav (which resets Calendar's
  selection to day 1) and a direct day click alike, while the
  mount-time `change` emit (whose date already matches the
  server-resolved default) causes no navigation. `weekDays`,
  `cellsFor()`, `scheduleFor()`, and `visibleWorkcenters` derive the
  per-workcenter schedule from `weekCells`, filtered live by the
  existing `checkedWorkcenterIds`/`checkedShiftIds` state — a
  workcenter or shift with nothing relevant that week (per the
  checked filter) is omitted entirely, no empty-state message.
  Rendered as a `WorkcenterScheduleCard` list below the
  calendar+filter row. `Scheduling.test.js` rewritten: added cases for
  day-click navigation, next-month navigation now carrying `date`
  too, a workcenter card appearing only when it has relevant coverage
  that week, and unchecking the only relevant workcenter removing its
  card. `npm run test` (473 passed) and `npm run build` green.

## Not done / deferred

- Everything under the spec's non-goals: bulk actions, eligibility
  rule changes, a dedicated week-navigation control, highlighting the
  specifically-clicked day within the matrix.
- Manual browser pass — AGENTS.md leaves UI verification to the user.
