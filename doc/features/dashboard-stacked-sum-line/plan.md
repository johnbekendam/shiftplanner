Status: done — 4/4

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
- [x] 3. Stacked mode also draws confirmed and unconfirmed as their own
      lines. `FteLineChart` gains `secondaryAvailable` /
      `secondaryAvailableStroke` props, rendered as a third independent
      polyline (`data-testid="fte-secondary-line"`), styled like the
      existing base line. `Dashboard/Index.vue` passes
      `baseAvailable`/`baseAvailableStroke` (confirmed, brand blue) and
      `secondaryAvailable`/`secondaryAvailableStroke` (unconfirmed,
      gray) only when `both` is active. Update
      `tests/js/FteLineChart.test.js` and
      `tests/js/DashboardIndex.test.js` accordingly.
- [x] 4. Reorder the selector to Unconfirmed, Confirmed, Stacked and
      swap colors: Unconfirmed stays gray, Confirmed becomes green
      (`--color-badge-success-text`), Stacked becomes blue
      (`--color-brand-bg`). Update `employeeFilterOptions` order and
      `lineClass` values, `confirmedLineColor`/`stackedLineColor`
      constants, and the affected assertions in
      `tests/js/DashboardIndex.test.js`.
