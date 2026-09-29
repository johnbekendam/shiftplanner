# Default-Visible Shift Availability — Spec

## Problem

`workcenter-scoped-availability` made the availability grid show only the
shifts that the employee's workcenters run. An employee with a workcenter
therefore loses every default-visible shift that their workcenter does not
run. Scheduling uses a different rule
(`SchedulingEligibility::isShiftUnavailableForWorkcenter`): a shift is
available when it is visible by default, or when the employee's workcenter
runs it. Availability and scheduling do not agree.

## Solution

### Effective shift list

`Employee::effectiveShifts()` returns the union of:

- every shift where `visible_by_default` is true, and
- every shift that one of the employee's workcenters runs (a
  `workcenter_shift` row), whatever its `visible_by_default` value.

An employee with no workcenter gets only the first set. This is the
current behavior.

### Weekdays and date exceptions

`shiftWeekdays()` and `shiftDateExceptions()` select the capacity source
for each shift:

- **A shift that one of the employee's workcenters runs:** the capacity
  and date overrides of the employee's workcenters. This is the current
  behavior.
- **Any other effective shift:** the capacity and date overrides of every
  active workcenter. This is the rule that already applies to an employee
  with no workcenter.

### Where it applies

The manager grid (`EmployeeController::edit`), the personal page
(`PersonalPageController::show`) and the write paths
(`SetsRecurringAvailability`, `SetsDateAvailability`) all use these
methods. They follow the new rule and need no separate change.

## Key decisions

- **Default-visible shifts always show.** The employee's workcenter only
  adds shifts. It never removes a default-visible shift.
- **Availability matches scheduling eligibility.** The union rule is the
  same rule that `isShiftUnavailableForWorkcenter` and `PlanningVerifier`
  already use.
- **Capacity source per shift.** A shift that the employee's workcenter
  does not run has no capacity at that workcenter. Without the fallback,
  the shift would show with every cell closed. The fallback uses the
  existing no-workcenter rule.

## Non-goals

- Changes to scheduling eligibility, the planning verifier or the plan
  generator.
- Changes to stored availability. Rows for shifts that show again become
  active again.
- A UI indicator that tells the employee why a shift shows.
