Status: done — 5/5

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

- [x] 3. Full-suite regression check
      Run the PHP and JS test suites in full. Fix any regression this
      feature introduced. No new behavior in this step.

      Ran clean after step 2: 909 PHP tests, 762 JS tests, no failures.

- [x] 4. Business line, weekly hours, and confirmed-status columns
      Add `business_line`, `weekly_hours`, `confirmed` to both
      `unassignedWorkcenterReport()` and `workcenterReport()` in
      `ReportController`, matching `missingAvailability()`'s field
      shape and values. Add the matching columns to both tables in
      `Reports/Index.vue`, and `reports.workcenter.column.business_line`
      / `.weekly_hours` / `.confirmed`, `.no_business_line`, and
      `.confirmed.yes`/`.no` language keys.

      `tests/Feature/ReportsTest.php` and `tests/js/Reports.test.js`
      cover the new fields on both tables, including the no-business-line
      and unconfirmed cases. Full suite ran clean: 917 PHP tests, 763 JS
      tests.

- [x] 5. Pagination and sortable columns on both tables
      `ReportController`: both `unassignedWorkcenterReport()` and
      `workcenterReport()` now return a 15-per-page `LengthAwarePaginator`
      (custom page name `workcenter_page`, since both tables share one
      query-param set — only one is ever visible at a time) instead of a
      plain array. New `applyWorkcenterSort()` helper handles
      Name/Business line/Weekly hours/Confirmed (plus Mode on the
      For-workcenter table) via a validated `workcenter_sort` +
      `workcenter_direction` pair, falling back to Name on an invalid or
      not-applicable key. Business line sorts through a correlated
      subquery (`BusinessLine::select('abbreviation')->whereColumn(...)`)
      to avoid colliding with the pivot's auto-selected columns on the
      For-workcenter table's `belongsToMany` query.

      `Reports/Index.vue`: `workcenterSort`/`workcenterDirection` refs
      seeded from `filters`, included in the existing `reload()` watcher
      (so changing sort or mode drops any `page` param and naturally
      resets to page 1). Column headers on both tables become sort
      buttons with a chevron icon, reusing the Employees page's icon/
      button styling. A compact pagination footer (item range, page
      links) matching the Employees page's paginator, shown only on the
      `workcenter` tab and only when the active table's `last_page > 1`.

      `tests/Feature/ReportsTest.php` and `tests/js/Reports.test.js`
      cover: 15-per-page pagination and the second page on both tables,
      sorting by `weekly_hours` descending and by `mode` descending, an
      unknown sort key falling back to `name`, clicking a column header
      reloading with the new sort, clicking the same header again
      flipping direction, and the pagination footer showing only when
      there's more than one page. Full suite ran clean: 922 PHP tests,
      767 JS tests.
