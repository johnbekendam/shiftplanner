# Employee Flexibility Column

## Problem

Planners need to compare employees on the employees page.

The page must show which shifts each employee can cover during a normal work week.

## Solution

Add one column to the employees table.

For each employee, the column shows one badge per shift.

Each badge shows the shift name and the employee coverage percentage for that shift.

The percentage uses Monday through Friday recurring availability.

## Key Decisions

- Show coverage per shift, not one total flexibility score.
  This makes it clear when an employee can cover one shift or multiple shifts.
- Count available and not preferred availability as covered.
  This makes the metric show capacity, not preference.
- Count unavailable availability as not covered.
  This keeps the percentage tied to real shift coverage.
- Use Monday through Friday as the percentage basis.
  This matches the current recurring availability model.
- Show the values as compact badges in the employees table.
  This keeps the list easy to scan.
- Keep the column display-only for now.
  Sorting, filtering, holiday logic, and calendar-period coverage are out of scope.

## Non-goals / Scope Boundaries

- Do not add sorting or filtering for the new column.
- Do not include holidays or calendar periods in the calculation.
- Do not change the employee availability editor.
- Do not add a new availability state.
