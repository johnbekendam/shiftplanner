# Workcenter report

## Problem

Managers cannot see workcenter-assignment gaps. Nothing shows which
employees hold no workcenter assignment at all, and nothing lists who is
assigned to one specific workcenter.

## Solution

Add a Workcenter tab to the existing Reports page, right after the
Competences tab.

The tab has a mode selector with two modes:

- **Unassigned** (default) — lists employees with zero
  `employee_workcenter` rows, hard or soft, for any workcenter. No
  further control. One column: Name.
- **For workcenter** — reveals a workcenter picker listing active
  (non-archived) workcenters. Lists every employee with a hard or soft
  row for the picked workcenter. Columns: Name, Requirement/Preference
  (the row's `hard`/`soft` mode, worded the same as the employee
  Workcenters tab). Before a workcenter is picked, the tab shows an
  empty prompt and no rows.

Both tables also show Business line, Weekly hours, and Confirmed for
each employee, matching the missing-availability report's column
values and wording: the business line's abbreviation (or an em dash
with none set), the raw `weekly_hours` number, and Confirmed/Unconfirmed
text.

Both tables paginate at 15 rows, matching the Employees page's page
size, with the same compact footer paginator (item range plus
previous/page-numbers/next). Every column, including Requirement/
Preference and Confirmed, is sortable: clicking a header sorts by
that column ascending, clicking it again flips to descending. Picking
a page keeps the current sort and filters. Switching mode, picking a
workcenter, or sorting resets to page 1.

Clicking a row in either mode navigates to
`/employees/{id}/edit?tab=workcenters`, matching the Competences tab's
click-through to the employee edit page.

### Data access

`Workcenter` gains an `employees()` `belongsToMany` relation with
`withPivot('mode')`. This is the first place that reads the
`employee_workcenter` pivot from the workcenter side; the pivot's
original spec deferred this relation until something needed it.

## Key decisions

- **Two explicit modes, not a has/missing toggle on one workcenter.**
  The competence report's has/missing toggle answers "does this employee
  have this one item," which doesn't cover "has no item at all." This
  report needs both an all-workcenters gap view and a per-workcenter
  view, so it gets two distinct modes instead of forcing the gap view
  through a picker.
- **"Unassigned" means zero rows for any workcenter**, hard or soft.
  Mirrors the missing-availability and competence reports' "no row"
  definition of "not set."
- **"For workcenter" shows hard and soft rows together**, distinguished
  by a Requirement/Preference column, rather than filtering to one mode.
  A manager checking one workcenter's roster wants the full picture, not
  a second toggle to flip.
- **Archived workcenters are excluded from the picker.** They're
  retired; a manager reviewing assignments cares about active ones. This
  differs from the employee Workcenters tab, which does show an
  employee's stale row against an archived workcenter — that tab is
  about clearing stale data, this report is about coverage.
- **No business-line or confirmed-status filters, but both are shown as
  columns.** A manager asked for this context on every row after the
  first version shipped. This is a display addition, not a filter —
  the "keep this small" scope decision above was about filters, not
  columns.
- **Pagination and sorting share one query-param set across both
  tables.** Only one table is ever visible at a time (the mode
  selector), so `workcenter_sort`/`workcenter_direction`/
  `workcenter_page` apply to whichever is active rather than doubling
  the params. An invalid or not-applicable sort key (`mode` on the
  Unassigned table) falls back to Name.
- **Every column is sortable, including Confirmed and Requirement/
  Preference.** Unlike the Employees page, which leaves its
  checkbox-shaped Confirmed column non-sortable, this report's manager
  explicitly asked for every column to sort.
- **Row click navigates to the employee's Workcenters tab.** Same
  pattern as the competence report's click-through, giving a direct path
  to fix the gap.
- **Tab position: right after Competences.** Groups the two
  employee-attribute reports together, ahead of the operational
  uninformed-planning and missing-availability tabs.

## Non-goals / scope boundaries

- No business-line or confirmed-status filtering.
- No bulk email action from this report.
- No change to the employee Workcenters tab's own archived-workcenter
  handling.
- No automatic-planning behavior change — this is a read-only report.
