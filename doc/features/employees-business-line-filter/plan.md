Status: in progress — 2/4

- [x] 1. Backend: filter the employees index by business line.
      `EmployeeController::index` reads `business_lines[]` from the
      query string (a list of ids and/or the literal `'none'`). When
      present, constrain the query to
      `business_line_id IN (ids)` OR (`none` given) `business_line_id
      IS NULL`; unknown ids are dropped, not rejected. When absent, no
      filter applies. The controller passes `businessLines` (all lines,
      id + abbreviation) and `selectedBusinessLines` (the resolved
      active list — every line id plus `'none'` when the param was
      absent) to `Employees/Index`. Add feature tests to
      `tests/Feature/EmployeeBusinessLineTest.php` covering: filtering
      to one line, filtering to "no business line", combining multiple
      lines, an unknown id being ignored, and the default (no param)
      returning everyone with every id "selected".
- [x] 2. Frontend: the Business Lines filter menu on Employees.
      `Employees/Index.vue` gets a "Business lines" `ButtonSecondary`
      next to the search box. Clicking it opens a floating panel (outside
      click / Escape closes it, following `ShiftWeekTable.vue`'s
      spots-menu pattern) listing one `CheckboxInput` per business line
      plus "No business line", seeded from `selectedBusinessLines`.
      Toggling a box updates the URL's `business_lines[]` (omitted
      entirely when everything is checked) and reloads the list via the
      same `reload()` used by search/sort, resetting `selectedIds` and
      the page. Add `tests/js/EmployeesIndex.test.js` (create if it
      doesn't exist) covering: the menu renders one checkbox per line
      plus "No business line", all checked by default; unchecking one
      triggers `router.get` with the right `business_lines[]` query;
      checking every box again drops the param; the panel opens/closes
      on trigger click and outside click.
- [ ] 3. Backend: business line id on dashboard payload.
      `DashboardController::index`'s `lines` array currently omits the
      business line's `id`. Add it so the frontend can build the link
      target. Extend the existing dashboard feature test to assert the
      `id` is present per line.
- [ ] 4. Frontend: dashboard card headers link to filtered Employees.
      `Dashboard/Index.vue` wraps each card's header `<h2>` in a `Link`
      (`@inertiajs/vue3`): business-line blocks link to
      `/employees?business_lines[]=<id>`, the Overall block links to
      `/employees`. Update `tests/js/DashboardIndex.test.js` to assert
      the header link targets for an Overall block and a line block.
