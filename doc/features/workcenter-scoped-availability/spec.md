# Workcenter-Scoped Availability — Spec

A grilling session with the user settled this design before this document
was written.

## Problem

The availability grid shows every shift where `visible_by_default` is
true, for every employee. `doc/features/employee-workcenter-assignments/`
lets a manager mark an employee's hard workcenter or workcenters. This
is the allowlist that already restricts scheduling assignment.
Availability does not use that restriction. An employee assigned to one
workcenter can still enter availability for a shift that no workcenter
of theirs ever runs. Also, a shift their workcenter runs but hides by
default never appears.

## Solution

### Effective shift list

A new `Employee::effectiveShifts()` method replaces the inline
`Shift::all()->where('visible_by_default', true)` pattern duplicated in
`EmployeeController::edit` and `PersonalPageController::show`:

- **The employee holds no hard `employee_workcenter` row:** every shift
  where `visible_by_default` is true. This matches today's behavior.
- **The employee holds one or more hard rows:** the union of shifts
  linked, through `workcenter_shift`, to those workcenters —
  `Shift::whereHas('workcenters', fn ($q) => $q->whereIn('workcenters.id', $hardWorkcenterIds))`.
  This branch does not check `visible_by_default`. A workcenter's shift
  shows because the workcenter runs it, whether or not that shift is
  visible by default.

Soft rows are ignored. A soft-only employee (no hard rows) falls into
the first branch, same as an employee with no rows at all. This matches
`employee-workcenter-assignments/spec.md`'s existing rule: soft never
restricts anything.

### Where it applies

- `EmployeeController::edit` — the manager-side availability grid.
- `PersonalPageController::show` — the employee's own token-based page.
- `SetsRecurringAvailability::setCell()` — the write path shared by the
  manager and personal recurring-availability update routes. This
  replaces its `! $shift->visible_by_default` guard with
  `! $employee->effectiveShifts()->contains($shift)`, so a write fails
  exactly when the grid would not have shown that shift.

Unaffected: `EligibleEmployeeController` (the scheduling picker) and
`ShiftAssignmentController` (the assignment endpoint) still check
`visible_by_default`, exactly as today. Workcenter-based assignment
eligibility already has its own, separate check
(`SchedulingEligibility::isWorkcenterIneligible`). This feature does not
touch it.

## Key decisions

- **"Assigned to a workcenter" means a hard `employee_workcenter` row.**
  Soft rows stay purely advisory everywhere in the app, including here.
  Extending soft to also restrict availability would give it a second,
  inconsistent meaning.
- **Workcenter membership decides visibility outright.**
  `visible_by_default` does not layer on top for a restricted employee.
  The feature request is explicit: a workcenter's shift shows even when
  it is not visible by default. No per-employee override table remains
  to reconcile against — `shift-visibility-weekly-hours` built
  `employee_shift_visibility_overrides`, and migration
  `2026_09_15_140000_remove_employee_shift_visibility_overrides` later
  dropped it. This feature is the mechanism that replaces it.
- **Multiple hard workcenters union their shifts.** This matches how
  hard-mode assignment eligibility already treats multiple hard rows as
  one combined allowlist.
- **The write path enforces the same rule as the display.** This matches
  the existing pattern: `SetsRecurringAvailability::setCell()` already
  rejects a write for a shift the grid would not show.
- **The scheduling picker and assignment endpoint stay out of scope.**
  They already have their own, separate workcenter-eligibility check
  (`isWorkcenterIneligible`). This feature only changes which shifts an
  employee can see and set availability for, not which workcenter they
  can be assigned to.

## Non-goals

- Any change to `EligibleEmployeeController` or `ShiftAssignmentController`.
- Any change to soft-row behavior or to `isWorkcenterIneligible`/
  `isWorkcenterNotPreferred`.
- A UI note that explains why a shift disappeared or appeared. The grid
  just reflects the current set, with no banner or tooltip.
- Retroactive changes to stored `RecurringAvailability` rows that fall
  outside the effective set after a workcenter assignment changes. They
  stay stored but stop rendering — the same dormant-not-deleted behavior
  `visible_by_default` already has.
