Status: done — 2/2

- [x] 1. Stacked mode renders a single green sum line, no base line.
      In `Dashboard/Index.vue`, `baseAvailable` is `null` for the `both`
      filter (drop the `activeEmployeeFilter.value === 'both'` branch
      that currently passes `available_confirmed`), and
      `availableStroke` for `both` is
      `var(--color-badge-success-text)` instead of
      `unconfirmedLineColor`. Confirmed/Unconfirmed strokes unchanged.
      Update `tests/js/DashboardIndex.test.js` assertions that currently
      expect a base line and gray stroke in `both` mode.
- [x] 2. Stacked selector button shows a green line marker.
      Add a `lineClass` (`bg-[var(--color-badge-success-text)]`) to the
      `both` entry in `employeeFilterOptions`. Add/update a
      `tests/js/DashboardIndex.test.js` assertion that the Stacked
      button renders `dashboard-employee-filter-line` in that color.
