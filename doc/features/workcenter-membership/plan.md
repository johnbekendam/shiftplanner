# Workcenter Membership — Plan

Status: in progress — 4/5

- [x] **Membership gates eligibility.** `SchedulingEligibility::isWorkcenterIneligible`
  and `PlanEligibility::isWorkcenterIneligible` return true when the
  employee has no row for the workcenter, whatever its `mode`. An
  employee with no rows cannot be planned anywhere: the picker,
  `ShiftAssignmentController::store`, and the auto-planner. Update the
  eligibility tests and the planning fixtures that depend on
  "no rows = unrestricted".
- [x] **Each row sets shift visibility.** `Employee::effectiveShifts()` and
  `isShiftUnavailableForWorkcenter` count each row, not only `hard`
  rows. With no rows, the result is the `visible_by_default` shifts.
- [x] **Remove the workcenter-not-preferred flag.** Remove
  `isWorkcenterNotPreferred`, the `workcenter_not_preferred` JSON
  field, and the second triangle in `ShiftWeekTable.vue`.
- [x] **Remove the Reports "Mode" column.** Remove the column, the
  `mode` sort key, the `mode` field in the row payload, and
  `reports.workcenter.column.mode`.
- [ ] **Drop `mode`.** Add a migration that drops the column. Remove
  `withPivot('mode')`. Make `PUT /employees/{e}/workcenters/{w}`
  bodyless. Make `WorkcenterChecklist.vue` a checkbox-only list, with
  `EmployeeController` sending `{ workcenter_id }`. Remove `mode` from
  the audit entries and the employee-backup snapshot. Make
  `ApplicationBackup` import remove `mode` from old archives. Remove
  `workcenters.mode.*`. Update the test fixtures that attach with
  `mode`. Add a "superseded by" note to
  `doc/features/employee-workcenter-assignments/spec.md`.
