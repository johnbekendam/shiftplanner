# Roster — Spec

## Problem

A manager wants to see who works on which day. The planning editor is
admin-only and is organized by workcenter, not by person. A manager
cannot see the days of an employee in one view.

## Solution

A new read-only page, **Roster**, at `/roster`.

### Access

- All logged-in users can open the page (admin and manager).
- The sidebar shows "Roster" after "Employees".

### What the page shows

- One ISO week. One column for each day, Monday to Sunday.
- One row for each employee who has one or more published assignments
  in the week. Rows are sorted by name.
- The row shows the employee name and the business line abbreviation.
- A day cell has one line for each assignment: the shift name, and
  below it the workcenter name in muted text. A day with no
  assignment shows a muted dash.
- The column of today has a highlight.
- If the week has no published assignments, the page shows an empty
  state.

### Published only

- An assignment shows only if a manager published its week for its
  workcenter (`published_weeks`).
- One week can be published for one workcenter and not for another.
  The page then shows only the assignments of the published workcenter.

### Filter

- The page uses the business line multi-select of the Employees page,
  with the "no business line" option.
- The filter is in the URL query.
- On the first visit in a browser tab, the filter defaults to the
  business line of the user. This is the Employees page behavior.

### Week navigation

- The page opens on the current week. The week is in the URL
  (`?week=YYYY-MM-DD`, the Monday).
- Previous and next buttons move one week. They have no limit.

## Key decisions

- **Planned assignments, not availability.** The page answers "who
  works", not "who can work". The assignment also gives the shift and
  the workcenter.
- **Published only.** The page matches the personal page and the live
  screen. Managers do not act on a draft roster.
- **Employee × day grid, one week.** This is the fastest way to see
  the days of one person. Two weeks makes the grid too wide.
- **Only planned employees.** The list stays short. The page shows who
  works, not who is free.
- **Reuse the Employees filter.** Users know the filter. The code and
  the default behavior already exist.
- **Shift and workcenter names in the cell.** Shifts and workcenters
  have no abbreviation. One employee can have more than one assignment
  on one day.
- **Name "Roster".** It is short and standard. It is different from
  "Planning" (the admin editor).
- **No navigation limit.** An unpublished week shows the empty state.
  A clamp to the planning period adds edge cases for little value.

## Non-goals

- Edits of any kind.
- Draft (unpublished) assignments.
- Availability or holidays.
- Export or print.
- Rows for employees with no assignment in the week.
- A filter on workcenter or shift.
