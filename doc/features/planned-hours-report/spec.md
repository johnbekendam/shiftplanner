# Planned hours report

## Problem

Managers cannot see the planned hours per workcenter per day. Nothing
shows this data, and nothing lets them export it for further use.

## Solution

Add a "Planned hours" tab to the existing Reports page, as the last tab
(after Uninformed planning).

The tab has two date inputs, "From" and "To", defaulting to the current
week (Monday to Sunday). Below them, a flat table lists one row per
workcenter and day that has planned hours in the picked range: Workcenter,
Date, Hours. Rows with zero hours are left out, and there are no subtotal
rows.

The table covers every active (non-archived) workcenter — no workcenter
picker. It is sortable by any column (ascending, then descending on a
second click) and paginated at 15 rows, matching the Workcenter report's
table.

An "Export CSV" link next to the date inputs downloads the full result set
for the picked range — every matching row, not just the current page — as
a CSV file.

### Hours counted

A row's hours come from `ShiftAssignment` rows whose `(week_start,
workcenter_id)` pair is published, per `PublishedWeek`. Draft
assignments — not yet published — are left out, since they are not a
firm commitment yet and could still change.

Every employee's assignments count, confirmed or not.

Each assignment's hours come from its shift's start and end time, wrapping
past midnight for an overnight shift. This calculation moves to a new
`Shift::durationHours()` method, and the two existing copies of it (in
`SchedulingEligibility` and `PlanAssignmentSet`) call the new method
instead of repeating the calculation.

### Data access

`ReportController` gains a `plannedHoursReport(Carbon $from, Carbon $to)`
method: for each active workcenter and each week the range touches, checks
`PublishedWeek::pairs()` for that workcenter, then sums the published
assignments' hours per day. Returns a paginated, sorted list of
`{ workcenter, date, hours }` rows.

A new route and controller method serve the CSV export, running the same
query without pagination and streaming the result with `fputcsv`.

## Key decisions

- **Published hours only, not draft.** A draft assignment can still
  change before publishing, so counting it would overstate a workcenter's
  real planned hours and shift confusingly as a planner edits. This
  matches how `PlannedShifts::forEmployee()` already distinguishes
  published from draft.
- **No confirmed-employee filter.** Unlike the Workcenter report, this
  report counts every employee's published hours. The report is about a
  workcenter's total planned hours, not about employee status.
- **Custom date range, not a week picker.** A manager may want a report
  spanning several weeks or a partial week, which a single-week picker
  cannot give. The range defaults to the current week so the tab shows
  data immediately.
- **Flat rows, not a workcenter-by-day matrix.** A matrix would grow too
  wide for a multi-week range and would not fit the existing paginated,
  sortable table pattern the other reports already use. Flat rows scale
  to any range length.
- **No subtotal rows.** Keeps the table and the CSV export the same
  shape. A manager who wants totals can compute them from the exported
  rows.
- **Rows with zero hours are left out.** Matches how the other reports
  only list rows that represent something real, rather than every
  possible workcenter-and-day combination.
- **All active workcenters shown together, no picker.** The report's
  purpose is comparing hours across workcenters for the same days, which
  a single-workcenter picker would work against.
- **CSV export is a dedicated server route, not a client-side build.**
  The export must cover the full filtered result set, not just the
  visible page, so it needs the server to re-run the same query
  unpaginated and stream it. This is the first CSV export in the
  codebase, so it sets the pattern: a plain `<a>` link, styled to match
  `ButtonPrimary`, pointing at the export route with the current date
  range as query params.
- **`Shift::durationHours()` replaces two existing duplicates.** The
  overnight-wrap calculation this report needs already exists twice, in
  `SchedulingEligibility` and `PlanAssignmentSet`. Adding a fourth copy
  was rejected in favor of one shared method both existing call sites
  now use.
- **Tab position: last, after Uninformed planning.** Added as the newest
  report without reordering the existing tabs' grouping.

## Non-goals / scope boundaries

- No workcenter picker or business-line filter.
- No subtotal or grand-total rows, on screen or in the CSV.
- No change to how `ShiftAssignment` or `PublishedWeek` themselves work.
- No automatic-planning behavior change — this is a read-only report.
