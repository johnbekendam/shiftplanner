Status: complete — 3/3

- [x] 1. Add `Employee::effectiveShifts(): Collection` — no hard
  `employee_workcenter` row returns `Shift::where('visible_by_default', true)`;
  one or more hard rows returns the union of shifts linked, through
  `workcenter_shift`, to those workcenters, ignoring `visible_by_default`.
  Soft-only or no rows falls through to the default-visible branch. Unit
  tests cover: no rows, soft-only row, one hard row (including a
  not-visible-by-default shift the workcenter runs), and two hard rows
  (union, no duplicates).

- [x] 2. Replace the inline `Shift::all()->where('visible_by_default', true)`
  pattern in `EmployeeController::edit` and `PersonalPageController::show`
  with `$employee->effectiveShifts()`, for both the `shifts` and
  `availability` payload keys. Feature tests on both routes: an employee
  with a hard workcenter sees only that workcenter's shifts (including a
  hidden-by-default one the workcenter runs, excluding a
  visible-by-default one it does not run); an employee with no hard row
  keeps today's default-visible set.

- [x] 3. Replace `SetsRecurringAvailability::setCell()`'s
  `! $shift->visible_by_default` guard with
  `! $employee->effectiveShifts()->contains($shift)`. Feature tests: a
  write for a shift outside the employee's effective set is rejected
  (both the manager and personal routes, matching the existing
  `test_hidden_shift_rejects_new_manager_and_personal_availability_writes`
  pattern); a write for a hidden-by-default shift that the employee's
  hard workcenter runs succeeds.
