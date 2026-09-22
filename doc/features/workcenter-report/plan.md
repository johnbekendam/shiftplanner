Status: in progress — 2/3

- [x] 1. `Workcenter::employees()` relation and controller data
      Add `Workcenter::employees(): BelongsToMany` with
      `withPivot('mode')`, mirroring `Employee::workcenters()`.

      Extend `ReportController@index`:
      - Read `workcenter_mode` (`unassigned` default, or `for_workcenter`)
        and `workcenter` (nullable int) query params.
      - `unassignedWorkcenterReport()`: employees with
        `whereDoesntHave('workcenters')`, ordered by name, mapped to
        `{ id, name }`.
      - `workcenterReport(?Workcenter $workcenter)`: when no workcenter is
        picked, returns `[]`. Otherwise, the picked workcenter's
        `employees` (ordered by name) mapped to
        `{ id, name, mode: 'hard' | 'soft' }` from the pivot.
      - Pass `workcenters` prop: active (non-archived) workcenters as
        `{ id, name }`, for the picker.
      - Pass `filters.workcenter_mode` and `filters.workcenter_id`.

      `tests/Feature/ReportsTest.php` covers:
      - Unassigned mode lists an employee with zero `employee_workcenter`
        rows and excludes one with a row (hard or soft).
      - No `workcenter` param in `for_workcenter` mode returns an empty
        `workcenterReport` list.
      - `for_workcenter` mode with a `workcenter` param lists employees
        holding a hard or soft row for that workcenter, each with the
        right `mode`, and excludes employees assigned elsewhere.
      - The `workcenters` prop excludes archived workcenters.

- [x] 2. Workcenter tab UI: mode selector, picker, and both tables
      Add a `workcenter` tab to `resources/js/pages/Reports/Index.vue`'s
      `tabs` array, positioned right after `competences`. Add
      `workcenterMode` and `workcenterId` refs seeded from
      `props.filters`, included in the `reload()` watcher.

      Tab body:
      - A mode `SelectInput` ("Unassigned" / "For workcenter").
      - In `for_workcenter` mode, a workcenter `SelectInput` next to it.
      - `unassigned` mode: table with one Name column from
        `unassignedWorkcenterReport` prop.
      - `for_workcenter` mode, no workcenter picked: empty prompt, same
        style as the competence tab's `empty_selection` text.
      - `for_workcenter` mode, workcenter picked, no rows: empty-results
        text.
      - `for_workcenter` mode with rows: table with Name and
        Requirement/Preference columns from `workcenterReport` prop.
      - Each row (both modes) navigates to
        `/employees/{id}/edit?tab=workcenters` on click, matching the
        competence tab's `openEmployeeCompetences` pattern.

      Add `reports.tab.workcenter` and the other `reports.workcenter.*`
      keys to `resources/lang/en.json`.

      `tests/js/Reports.test.js` covers:
      - The Workcenter tab renders the unassigned table by default from
        `unassignedWorkcenterReport` prop rows.
      - Switching to "For workcenter" mode reveals the workcenter picker
        and navigates with `workcenter_mode=for_workcenter`.
      - Picking a workcenter navigates with the `workcenter` query param.
      - `workcenterReport` prop rows render Name and mode text.
      - Clicking a row navigates to the employee edit page's workcenters
        tab.

- [ ] 3. Full-suite regression check
      Run the PHP and JS test suites in full. Fix any regression this
      feature introduced. No new behavior in this step.
