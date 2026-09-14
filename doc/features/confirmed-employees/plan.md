# Confirmed Employees - Plan

Status: done - 5/5

Spec: `spec.md`. Add an employee confirmed status that gates dashboard
coverage and scheduling eligibility.

- [x] 1. **Storage and model.** Add an `employees.confirmed` boolean with a
  default of `false`. Cast it on `Employee` and make sure factories keep the
  default unless a test opts in.
- [x] 2. **Employee list toggle.** Add a sortable or non-sortable Confirmed
  column to `Employees/Index.vue`, include the status in the index payload,
  add a tooltip to the column header, and add a small update endpoint for
  immediate row saves.
- [x] 3. **Dashboard counts.** Filter dashboard coverage to confirmed
  employees and pass a global unconfirmed employee count to the dashboard
  page.
- [x] 4. **Scheduling eligibility.** Exclude unconfirmed employees from the
  eligible employee endpoint and from assignment validation.
- [x] 5. **Tests and checks.** Add failing feature and component tests first,
  then implement until the targeted tests, full test suite, and build pass.