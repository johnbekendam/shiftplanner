# Shift Visibility and Weekly Hours - Spec

## Problem

All employees can enter availability for every shift. Planners can also
assign every employee to every shift. Managers need shift eligibility that
matches each employee's work arrangements.

The weekly-hours field uses fixed options and a fixed minimum of 20 hours.
Managers need a configurable minimum and optional employee-specific minimums.

## Solution

### Shift visibility

Each shift has a `visible_by_default` checkbox. Existing and new shifts use
`true` by default.

Each employee can have explicit visibility overrides for individual shifts.
An employee inherits the shift default when no override exists. An explicit
override stays unchanged when a manager changes the shift default.

The employee edit page gets a **Settings** tab. The tab shows the effective
visibility for all shifts and lets a manager set explicit overrides. The
personal page does not show this tab or these controls.

A hidden shift has these effects immediately:

- The employee cannot enter new recurring availability for the shift.
- The scheduling picker excludes the employee for the shift.
- The assignment endpoint rejects a new assignment for the shift.

Existing assignments stay unchanged, including assignments on future dates.
Existing recurring availability stays stored but dormant. It becomes active
again when the shift becomes visible.

### Weekly-hours minimum

Planning settings store a global weekly-hours minimum. The initial value is
20. A manager can set a whole number from 1 through 48 on the Settings page.

Each employee can have a nullable weekly-hours minimum override on the new
employee Settings tab. A null value inherits the current global minimum. The
personal page does not show or edit this value.

The weekly-hours select becomes a `NumberInput`. It accepts whole numbers
from 0 through 48. Zero remains a valid value and means no weekly-hours
requirement.

When a positive value is below the effective minimum, the form shows a
warning. A save keeps the entered value unchanged.

A global minimum change does not rewrite employee records. Values below the
new minimum show a warning when a manager or employee views the form.

## Key decisions

- **Visibility controls eligibility.** It is not a presentation-only filter.
- **Visibility changes apply immediately to new actions.** Existing planning
  stays unchanged.
- **Employees inherit live shift defaults.** Explicit overrides protect
  employee-specific choices from later default changes.
- **Stored availability stays dormant.** A temporary restriction does not
  delete an employee's preferences.
- **Managers control visibility and minimum overrides.** Employees cannot
  change these rules on the personal page.
- **Employees inherit the global minimum by default.** Managers only store an
  override when an employee needs a different rule.
- **Below-minimum hours stay unchanged.** The minimum provides a warning and
  does not change stored employee hours.
- **Zero does not show a warning.** Zero is an intentional disabled value.

## Non-goals

- Dated or scheduled visibility changes.
- Changes to existing shift assignments when visibility changes.
- Deleting recurring availability when visibility changes.
- Employee control of shift visibility or minimum-hours rules.
- Decimal weekly-hours values or values above 48.
- Bulk normalization after a global minimum change.

## Acceptance criteria

- A manager sets the default visibility when creating or editing a shift.
- A manager sets explicit shift visibility for an employee.
- An employee only sees visible shifts in the availability grid.
- The server rejects availability writes for hidden shifts.
- The scheduling picker excludes employees for hidden shifts.
- The server rejects new assignments to hidden shifts.
- Existing assignments remain after a visibility change.
- Stored availability remains after a visibility change.
- A manager sets the global weekly-hours minimum from 1 through 48.
- A manager sets or clears an employee minimum override.
- Manager and personal forms accept whole weekly hours from 0 through 48.
- A positive value below the effective minimum shows a warning.
- Saving a positive value below the effective minimum keeps that value.
- The personal page does not expose the employee Settings tab.
- Backend tests, frontend tests, and the frontend build pass.