# Dashboard — Planned Hours Line and Line Toggles — Plan

Status: done — 6/6

- [x] 1. Backend planned series: `DashboardController` adds a `planned`
  weekly-FTE series to the overall block and each business-line block.
  It counts published and draft assignments over full ISO weeks, split
  by the employee's business line. Feature tests in `DashboardTest`.
- [x] 2. Backend total series: add an `available_total` series
  (confirmed + unconfirmed) to each block, independent of the
  `employees` filter. Feature tests in `DashboardTest`.
- [x] 3. Chart lines list: `FteLineChart` takes a `lines` prop (a list
  of `{ key, values, stroke }`) in place of the fixed line props. It
  scales the y-axis to the visible lines and the target. Update
  `FteLineChart.test.js`.
- [x] 4. Toggle buttons: `Dashboard/Index.vue` replaces the selector
  with four toggles (Unconfirmed, Confirmed, Total available, Planned).
  The `?lines=` query string holds the set, and the default is Total
  available + Planned. Add the Planned color and the notice rule. Update
  `en.json` and `DashboardIndex.test.js`.
- [x] 5. Backend cleanup: remove the `employees` query filter,
  `employeeStatusFilter`, the filter-selected `available` series, and
  `available_hours`. Update `DashboardTest`.
- [x] 6. Checkbox legend: replace the four toggle buttons with a compact
  row of `CheckboxInput`s. Each checkbox shows its line-color marker and
  label. Update `DashboardIndex.test.js`.
