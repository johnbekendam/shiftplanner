# Employee Planning Tables

## Problem

The Planning tab on the employee edit page and on the employee's own page shows a list grouped by week, with a Published/Draft marker on each row. It is hard to scan, and published and draft shifts are mixed together.

## Solution

On the employee edit page, show two tables on the Planning tab: one for published planning and one for draft planning. The employee's own page shows one table, because it only holds published planning. Each table has these columns:

1. Week number (ISO week)
2. Date (`dd-mm-yyyy`)
3. Day of the week
4. Shift (name)
5. Workcenter
6. Contact person (see `doc/features/workcenter-responsible/`)

Rows are sorted by date. An empty table shows a short "none" message.

## Key Decisions

- Split published and draft in the browser. The controller already sends a `published` flag for each assignment.
- Add one `PlanningTable` component and use it on both pages.
- Delete `PlannedShiftsList` and its test. Nothing uses it after this change.
- The responsible-person column started as a placeholder. `doc/features/workcenter-responsible/` fills it.

## Non-goals

- Do not show shift times in the tables.
- Do not change the backend.
