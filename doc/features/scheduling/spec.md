# Scheduling — Spec

Roadmap phase 3 continuation. Third of three features that build on
`doc/features/workcenters/spec.md` and
`doc/features/workcenter-shift-assignments/spec.md`. Those two define
workcenters, attach shifts to them, and set an open-spot count per
weekday. This feature puts employees into those spots.

## Problem

A manager cannot put an employee into a shift on a date, or see who is
already in one. The open-spot counts from `workcenter-shift-assignments`
are a target with nothing to compare against. Nothing records a
one-off exception to a weekday's spot count either — that was left for
this feature's calendar view.

## Solution

### Data

New `shift_assignments` table:

| column | notes |
| --- | --- |
| `id` | |
| `employee_id` | `constrained`, cascade on delete |
| `workcenter_id` | `constrained`, cascade on delete |
| `shift_id` | `constrained`, cascade on delete |
| `date` | date |
| `fixed` | boolean, default `false` |
| timestamps | |

Unique on `(employee_id, workcenter_id, shift_id, date)`. A
`ShiftAssignment` model, `belongsTo` on all three, `toPayload()` →
`{ id, employee_id, employee_name, fixed }`.

Reuses `workcenter_shift_date_overrides` (migrated by
`workcenters/spec.md`, unused until now): this feature is the first to
write to it. `Workcenter::spotsFor()` already reads it.

### Page — `/scheduling`

A new sidebar item, **Scheduling**, admin-only (`nav.scheduling`, icon
`calendar-days`), after Workcenter Shifts. Route behind `admin`.

One workcenter, one week, at a time:

- A Workcenter `SelectInput` (active workcenters, position order).
  Switching it reloads the grid.
- Week navigation: previous/next-week buttons and a "this week" reset,
  showing the date range (for example "Sep 14 – Sep 20, 2026"). A week
  runs Monday to Sunday, matching the weekday capacity's ISO numbering.
- A grid: one row per shift attached to the workcenter (start-time
  order), one column per day of the week.

Each cell covers one (workcenter, shift, date):

- **Spot count** — shows `assigned / spots`, `spots` from
  `Workcenter::spotsFor()`. Clicking it swaps in a `NumberInput`. On
  blur it writes the new value, immediately, as a
  `workcenter_shift_date_overrides` row (create or update). A reset
  icon appears next to an overridden cell only and deletes that row,
  reverting to the weekday default. Lowering the count below the
  cell's current assignee count is rejected with an inline message —
  the field reverts and nothing is written.
- **Assignees** — a list of assigned employees, each with a pin toggle
  (`fixed`) and a remove (×) button. Both act immediately: a click
  fires the write, the list updates from the response. No confirm on
  remove — the same non-destructive tone as the rest of the app,
  though this is a real delete, not a pre-Save local change, since this
  page has no Save step.
- **Add** — a button opens a small popover: a search box over the
  eligible-employee list for that exact cell (fetched fresh each open,
  see below). Clicking a name assigns them immediately, `fixed: false`,
  and closes the popover.

Every write on this page is immediate. There is no Save/Cancel bar.
Each action is small (one assignment, one cell's spot count) and a
manager works this page like a live roster, not a form.

### Eligible-employee lookup

`GET /scheduling/eligible-employees?workcenter_id&shift_id&date` — a
plain JSON endpoint (`axios`, not an Inertia visit, since it backs a
transient popover rather than page state). Returns every employee
except:

- Already assigned to this exact cell.
- On holiday that date (`EmployeeHoliday` range, inclusive).
- Marked `unavailable` for that weekday and shift in
  `recurring_availabilities` (no row, including every Saturday and
  Sunday since that grid is weekday-only, means available).
- Already assigned elsewhere that date to a shift whose clock range
  overlaps this one.

A remaining employee marked `not_preferred` for that weekday and shift
still appears, flagged so the popover can show a warning badge. The
list is not paginated — employee counts here stay small enough that a
plain scrollable list is enough (matches `EmployeeMultiSelect.vue`'s
assumption).

### Writes

All behind `admin`, all immediate, all re-validating server-side (the
picker already filters, this is defense in depth, not the only check):

- `POST /scheduling/assignments` — `{ employee_id, workcenter_id,
  shift_id, date }`. Rejects a full cell, a duplicate pair, a holiday
  or `unavailable` employee, or a same-date overlapping assignment.
  `fixed` starts `false`.
- `PUT /scheduling/assignments/{assignment}` — `{ fixed }`. Toggles the
  pin.
- `DELETE /scheduling/assignments/{assignment}` — removes it, fixed or
  not. A manager can unassign a fixed employee same as any other. Fixed
  only ever protects against the future auto-planner.
- `PUT /scheduling/spots/{workcenter}/{shift}/{date}` — `{ spots }`.
  Upserts the override row. Rejects a value below the cell's current
  assignee count.
- `DELETE /scheduling/spots/{workcenter}/{shift}/{date}` — deletes the
  override row, reverting to the weekday default.

`SchedulingController@index` builds the grid payload: for each shift ×
date in the visible week, `spots` (`Workcenter::spotsFor()`) and
`assignments` (`ShiftAssignment::toPayload()` list, employee name
joined in).

## Key decisions

- **Immediate writes, not explicit-save.** Every other Settings-area
  list just moved to edit-until-Save. This page is different: it is a
  live roster a manager works shift by shift, not a form filled in
  once and submitted. Batching a week's worth of assign/remove/pin
  clicks into one Save adds risk (a large batch partially failing) for
  no benefit here, since each action is already small and
  self-contained.
- **This feature owns the per-date override**, reversing
  `workcenter-shift-assignments/spec.md`'s non-goal. That spec named
  "this feature's calendar view" as the intended owner before this
  feature existed to confirm it. The override belongs next to where a
  manager is already looking at a date's coverage, not on a separate
  flat-table page.
- **One workcenter, a week at a time.** Matches how a manager actually
  plans — staffing one line for the week ahead — and keeps the grid a
  fixed, small size (a handful of shifts by 7 days) regardless of how
  many workcenters or shifts exist.
- **Hard-blocked employees are hidden from the picker, not shown
  disabled.** A manager never sees an option that only errors back.
  `not_preferred` still shows, with a warning, since it is a soft
  signal they can knowingly override.
- **Shrinking spots below the assignee count is rejected**, not
  allowed into an over-capacity warning state. Keeps a cell's shown
  count always accurate — nowhere on this page can more people be
  assigned than the spot count says.
- **Delete removes a fixed assignment too, no separate unfix step.**
  Fixed protects against the (not yet built) auto-planner only. A
  manager already has full manual control. Requiring an unfix-then-
  delete sequence would add friction for no protection gained, since
  no automatic process runs yet.
- **A dedicated `axios` GET for the picker**, not an Inertia prop.
  Eligibility is per-cell and only needed while its popover is open.
  Computing and shipping it for every cell on every page load would be
  wasted work and a much larger payload.
- **Admin-only**, matching every other page in this area.

## Non-goals

- Rule-based automatic planning, hard/soft rules, and the OR-Tools
  `/solve` contract — a later, separate feature. Nothing here writes a
  rule or calls a solver.
- Competence-to-workcenter gating — still deferred, per
  `workcenters/spec.md`.
- An employee-facing schedule view — roadmap phase 6 ("Publish and
  employee schedule view"), not this feature.
- Bulk actions: copying a week's assignments forward, moving an
  assignment to a different cell in one step, or filling a cell
  automatically. Assign and remove are the only two employee-facing
  actions.
- Manager-level (non-admin) access.
