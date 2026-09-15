# Scheduling Week Drilldown — Spec

Second step of reworking `/scheduling` into a calendar-first page (see
`doc/features/scheduling-calendar/`). That step shipped a read-only
month calendar with a workcenter/shift filter and day coloring; day
click was a deliberate no-op, deferred to "a later step" — this is that
step. It also brings back the editing capability (assign/remove/pin,
per-date spot overrides) that step 1 removed from the page along with
the old week-grid, using the same backend endpoints that shipped with
`doc/features/scheduling/` and have sat unused since.

## Problem

The calendar shows which days have open spots, but a manager can't act
on that from the page — there's no way to see who's assigned or fill a
gap. Clicking a day does nothing.

## Solution

### Trigger

`Calendar`'s `enable-day-selection` turns on. Clicking a day selects
the Monday–Sunday week containing it. The week containing today shows
automatically on first page load, matching `Calendar`'s own
default-selected-day behavior (it already highlights today on mount).
Month prev/next already resets `Calendar`'s selection to day 1, so
navigating months shows that month's first week by default too.

### Layout

Below the existing calendar+filter row: one `Card` per checked
workcenter, stacked vertically, header = workcenter name. Inside each
card, one table per checked-and-relevant shift (relevant = attached to
that workcenter with spots > 0 on at least one day that week):

- Columns: the week's 7 days (Mon–Sun), each showing a short label
  (e.g. "Mon 14") and, beneath it, a small click-to-edit spot count for
  that shift/date (reusing the inline-`NumberInput` pattern from the
  old `SchedulingCell`), with a reset icon when that date carries an
  override.
- Rows: one per spot slot. Row count = the max spots needed across the
  week's 7 days for that shift/workcenter.
- A cell within that day's own spot count:
  - Filled → the employee's name (a small pin mark if `fixed`);
    clicking it reveals inline pin-toggle and remove controls.
  - Empty → "Open", clickable to open the existing eligible-employee
    search popover (`GET /scheduling/eligible-employees`) and assign.
- A cell in a row beyond that day's own spot count (another day that
  week needs more spots than this one does) → a plain, non-interactive
  dash — visually distinct from "Open" so a manager doesn't try to
  fill a slot that doesn't exist that day.

Per day, existing assignees fill rows fixed-first, then alphabetically,
top-down.

A checked workcenter with nothing relevant that week gets no card. A
checked shift with nothing relevant that week gets no table. Nothing
renders just to announce emptiness.

### Data

`SchedulingController@index` gains an optional `date` query param
(`Y-m-d`), defaulting to today if the viewed month is the current
month, else the 1st of the viewed month. It computes the Monday–Sunday
week containing that date and adds two props:

- `weekStart` — the Monday, `Y-m-d`.
- `weekCells` — one entry per `(workcenter, shift, date)` triple across
  **all active workcenters × all shifts × the week's 7 days**
  (unfiltered by the current checkbox selection — same "ship it all,
  filter client-side" approach the month `coverage` payload already
  uses): `{ workcenter_id, shift_id, date, spots, overridden,
  assignments: [{ id, employee_id, employee_name, fixed }] }`, bulk
  loaded (no per-cell `spotsFor()` calls).

Every write endpoint — `POST /scheduling/assignments`,
`PUT /scheduling/assignments/{id}`, `DELETE /scheduling/assignments/{id}`,
`PUT /scheduling/spots/{workcenter}/{shift}/{date}`,
`DELETE /scheduling/spots/{workcenter}/{shift}/{date}` — already
responds with Laravel's `back()`, which Inertia resolves as "reload the
current page's props." No new endpoint for writes; the frontend calls
them with `putAsync`/`postAsync`/`deleteAsync`
(`@/utils/inertiaAsync`), and the week/month props refresh
automatically. `GET /scheduling/eligible-employees` stays a plain
`axios` call, unchanged, backing the assign popover.

### Frontend

`Calendar`'s `change` event now also fires when only the selected day
changes (not just month/year); `Scheduling.vue`'s handler visits
`/scheduling` with `{ year, month, date }` whenever the resolved date
differs from the currently loaded one.

Two new components:

- `WorkcenterScheduleCard.vue` — one per shown workcenter; header =
  name; iterates its relevant shifts, rendering one
  `ShiftWeekTable.vue` each.
- `ShiftWeekTable.vue` — the spot-rows × day-columns table for one
  (workcenter, shift) pair across the week: per-day spot editing,
  per-cell assign/remove/pin, the assign popover. Adapts
  `SchedulingCell.vue`'s write logic (spot commit/reset, assignment
  assign/toggle-fixed/remove, eligible-employee fetch) into the new
  per-cell shape.

`checkedWorkcenterIds`/`checkedShiftIds` (already client-side state
from step 1) also drive which workcenter cards and shift tables render
from `weekCells` — no new state needed for filter reactivity.

## Key decisions

- **Week, not single day.** Raised during grilling: a single day's
  schedule is a thin sliver: a week gives a manager useful context
  (who's working the days around this one) for roughly the same number
  of clicks, and the "click a day → see its week" model still works
  since every week has 7 clickable entry points on the calendar.
- **Spot-rows × day-columns matrix**, not 7 stacked single-day tables.
  Reads like a roster at a glance; 7 separate tables per shift would
  make each workcenter card very tall.
- **Row identity is positional, not stored.** The data model has no
  "slot 1 / slot 2" concept — only a spots count and a flat assignment
  list per date. Row-to-assignee mapping is a rendering convenience
  (fixed-first, then alphabetical), not a persisted association;
  different days' same row index aren't "the same slot" in any
  tracked sense.
- **Dash vs "Open" distinguishes "no such spot today" from "unfilled
  spot today."** Prevents a manager from trying to fill a slot that
  doesn't exist that day.
- **Full unfiltered week payload, client-side filtering.** Matches step
  1's month `coverage` approach — one fetch per week/write, instant
  filter toggling, no re-fetch traffic for something already loaded.
- **Inertia props + `back()` writes, no new JSON endpoint.** The write
  controllers were already built this way (unused since step 1 removed
  their only caller) — this step is what finally wires them up as
  originally intended, not a redesign of them.
- **`SchedulingCell.vue` is deleted**, not adapted in place. Its
  compact "count + list" shape doesn't fit the matrix; its write calls
  are the reference for the new components, not reused code (Vue
  components don't share logic well via inheritance here, and the
  interaction model — click-to-edit spots, assign popover — is small
  enough to reimplement cleanly in the new shape).

## Non-goals

- Bulk actions (copy a week forward, fill a shift automatically) — per
  `doc/features/scheduling/spec.md`'s non-goals, still out of scope.
- Any change to eligibility rules, holiday/unavailable/overlap
  validation — reused as-is from `SchedulingEligibility`.
- A dedicated week-navigation control independent of the calendar —
  clicking any day within a different week on the currently-viewed
  month already changes which week shows.
- Highlighting the specifically-clicked day within the week
  matrix — all 7 days render equally once a week is selected.
