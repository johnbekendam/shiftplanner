# Workcenter Membership — Spec

Replaces the hard/soft model from
`doc/features/employee-workcenter-assignments/spec.md`. A grilling
session with the user settled this design.

## Problem

A new requirement says that an employee must have a workcenter
assignment before the planner can put them on a shift. Today an
employee with no `employee_workcenter` row is unrestricted. The
planner can put them on a shift at any workcenter.

The `mode` column (`hard` = "Requirement", `soft` = "Preference") is now
obsolete:

- A `hard` row is an allowlist. That becomes the only meaning of a row.
- A `soft` row only adds a warning triangle in the `/planning` assign
  popover. It shows at the workcenter that the row names, so the
  "Preference" label and the warning are in conflict. The auto-planner
  ignores `soft` rows.

## Solution

### Data

`employee_workcenter` becomes a plain membership pivot:
(`employee_id`, `workcenter_id`), with the same primary key. A migration
drops `mode`. Each existing row stays, `hard` and `soft` alike.

`Employee::workcenters()` and `Workcenter::employees()` lose
`withPivot('mode')`.

### Eligibility rule

A row means "the planner can put this employee on shifts at this
workcenter". If an employee has no row for a workcenter, the planner
cannot put them there. If an employee has no rows, the planner cannot
put them on any shift.

This is a fixed rule of the data model. It is not a planning rule, and
a user cannot disable it.

The rule applies to all three places that make assignments:

- `SchedulingEligibility::isWorkcenterIneligible`. The `/planning`
  picker and `ShiftAssignmentController::store` use it. They keep the
  `workcenter_ineligible` block reason and its message, "This employee
  is not assigned to this workcenter."
- `PlanEligibility::isWorkcenterIneligible` (the auto-planner).
- `SchedulingEligibility::isShiftUnavailableForWorkcenter`. Each row
  counts now, not only `hard` rows.

The app does not examine or change existing `ShiftAssignment` rows.

### Shift visibility

`Employee::effectiveShifts()`:

- No rows: the shifts with `visible_by_default`. This is the same as
  today. A new employee can give their availability before a manager
  assigns a workcenter to them.
- One or more rows: all the shifts that those workcenters run.

### Removals

- The Requirement/Preference `SelectInput` in `WorkcenterChecklist.vue`.
  Each row is a checkbox only.
- `mode` in the `PUT /employees/{employee}/workcenters/{workcenter}`
  request. The request has no body. It attaches the row if it does not
  exist.
- `SchedulingEligibility::isWorkcenterNotPreferred`, the
  `workcenter_not_preferred` field in the eligible-employee JSON, and
  the second warning triangle in `ShiftWeekTable.vue`.
- The "Mode" column and its sort key in the Reports workcenter table.
- `mode` in the employee-backup snapshot and in audit entries. The
  audit logger records `workcenter_changed` only for a new attachment.
  It records `workcenter_detached` for a removal.
- The language keys `workcenters.mode.hard`, `workcenters.mode.soft`,
  and `reports.workcenter.column.mode`.

### Application backup

`ApplicationBackup` keeps version 2. On import, it removes the `mode`
field from each `employee_workcenter` row. Thus it can restore old
archives into the new table.

## Key decisions

- **A data-model change, not a planning rule.** A rule would keep
  `mode` and add a second layer on top of it. A membership pivot gives
  one concept with one meaning.
- **The rule is always on.** The business requirement applies to all
  employees. A switch would only add a state that nobody uses.
- **Soft rows become memberships.** A manager made each link
  intentionally. An employee who has only soft rows can work only at
  those workcenters after this change. In the local data, 2 rows are
  soft, and no employee has both types.
- **No rows gives the default-visible shifts on the availability
  grid.** Self-signup employees can give their availability before a
  manager assigns a workcenter.
- **The planner does not revalidate existing assignments.** This
  agrees with the policy for recurring availability and for the old
  hard rows.
- **The Unassigned workcenter report is the worklist.** It already
  shows the confirmed employees who have no rows. Those employees
  cannot be planned after this change (5 in the local data).

## Non-goals

- The dashboard available-FTE lines. They continue to count employees
  who have no workcenter.
- A new planning rule, or changes to `competence_required` or
  `business_line_preference`.
- Personal-page editing of workcenter memberships.
- Bulk-assign tools. Managers use the Employee page and the Unassigned
  report.
