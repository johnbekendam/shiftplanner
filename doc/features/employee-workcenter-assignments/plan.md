# Employee Workcenter Assignments — Plan

Status: done — 8/8

## Steps

- [x] **Migration + model relation.** `employee_workcenter` pivot table
      (`employee_id`, `workcenter_id`, `mode`, composite primary key),
      mirroring `competence_employee`. `Employee::workcenters()`
      `belongsToMany` with `withPivot('mode')`.
- [x] **`SchedulingEligibility`.** Add `isWorkcenterIneligible` and
      `isWorkcenterNotPreferred`, next to `isUnavailable`/`isNotPreferred`.
      Covered via the feature tests below (no standalone unit test file
      exists for this service today; it's exercised the same way
      `isUnavailable`/`isNotPreferred` already are).
- [x] **Attach/detach endpoints.** `TogglesWorkcenter` trait mirroring
      `TogglesCompetence` (validates `mode`), `EmployeeWorkcenterController`
      (`update`, `destroy`), routes. Feature test mirroring
      `EmployeeCompetenceTest` (`EmployeeWorkcenterTest`).
- [x] **`EmployeeController@edit` payload.** Add `workcenters` and
      `employeeWorkcenterAssignments`. Feature tests mirroring the
      competences payload test.
- [x] **Enforcement — picker list.** `EligibleEmployeeController::index`
      loads the target `Workcenter`, rejects on `isWorkcenterIneligible`,
      adds `workcenter_not_preferred` to the JSON. Feature tests added to
      `EligibleEmployeeTest`.
- [x] **Enforcement — server-side hard block.** `ShiftAssignmentController::store`
      adds the hard-block check and `scheduling.error.workcenter_ineligible`
      message. Feature tests added to `ShiftAssignmentTest`.
- [x] **Language keys.** `workcenters.employee_tab`,
      `workcenters.checklist_empty`, `workcenters.archived_suffix`,
      `workcenters.mode.hard` ("Requirement"), `workcenters.mode.soft`
      ("Preference") — this page's own wording, distinct from
      `planning_rules.mode.hard`/`.soft`'s "Hard"/"Soft" — and
      `scheduling.error.workcenter_ineligible`.
- [x] **Frontend.** New `WorkcenterChecklist.vue` (checkbox +
      Requirement/Preference select per row, reveal-on-check, uncheck
      clears), with a component
      test mirroring `TagChecklist.test.js`/`PlanningRuleList.test.js`.
      Wired a **Workcenters** tab into `Employees/Form.vue` after
      Competences, following the explicit-save `registry.register(...)`
      pattern the Competences tab uses (diff pending vs. saved rows,
      `Promise.allSettled` of one PUT/DELETE per changed row). Added
      assertions to `EmployeesForm.test.js`. Added the second
      warning-triangle icon to `ShiftWeekTable.vue`'s assign popover,
      driven by `employee.workcenter_not_preferred` (wrapped in a plain
      `<span data-testid>` — Heroicons' compiled functional components
      only fall through `class`/`style`, not custom attributes, so a
      `data-testid` placed directly on `<Icon>` is silently dropped).

## Verification

- `php artisan test`: 554 passed.
- `npx vitest run`: 534 passed.
- `npx vite build`: clean.

## Notes

- Follows the recurring-availability hard/soft pattern exactly — no new
  enforcement shape.
- `EmployeeController@index` is explicitly out of scope for this pass
  per spec.md.
