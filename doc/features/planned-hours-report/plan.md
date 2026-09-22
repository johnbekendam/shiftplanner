Status: in progress — 2/5

- [x] 1. `Shift::durationHours()` and retrofit the two duplicates
      Add `Shift::durationHours(): float` to `app/Models/Shift.php`,
      moving in the start/end-time diff logic (minutes, plus 24 hours
      when the result is zero or negative, for an overnight shift).

      Update `App\Services\SchedulingEligibility::shiftDurationHours()`
      and `App\Services\Planning\PlanAssignmentSet::shiftDurationHours()`
      to call `Shift::durationHours()` instead of repeating the
      calculation. Keep each method's existing signature so callers are
      unaffected.

      `tests/Unit/ShiftTest.php` (new or extended) covers:
      - A same-day shift returns the correct hour count.
      - An overnight shift (end time before start time) wraps past
        midnight and returns the correct hour count.

      Run the full PHP suite to confirm the retrofit changes no existing
      behavior.

- [x] 2. Backend: `plannedHoursReport()` data and route
      Add a `PublishedWeek::pairsForWorkcenter(int $workcenterId): array`
      helper (or reuse `pairs()` filtered by workcenter if that already
      fits) to check which weeks a workcenter has published in a date
      range.

      Extend `ReportController`:
      - Read `planned_hours_from` and `planned_hours_to` query params
        (`Carbon` dates), defaulting to the current week (Monday to
        Sunday) when absent.
      - `plannedHoursReport(Carbon $from, Carbon $to)`: for each active
        workcenter, for each week the range touches, skip days outside
        a published `(week_start, workcenter_id)` pair. Sum each
        remaining day's `ShiftAssignment` hours via
        `Shift::durationHours()`, across all employees. Drop
        zero-hour rows. Map to `{ workcenter, date, hours }`.
      - Sort via a `planned_hours_sort` / `planned_hours_direction` pair
        (Workcenter, Date, Hours), validated against a whitelist,
        falling back to Date then Workcenter.
      - Paginate at 15 rows with page name `planned_hours_page`.
      - Pass `plannedHoursReport` and
        `filters.planned_hours_from`/`.planned_hours_to`/`.planned_hours_sort`/
        `.planned_hours_direction` props.

      `tests/Feature/ReportsTest.php` covers:
      - A published assignment inside the range appears with the right
        workcenter, date, and hours.
      - A draft (unpublished) assignment in the same range is excluded.
      - An assignment for an unconfirmed employee is still included.
      - A workcenter/day with no assignments produces no row.
      - Default range (no query params) covers the current week.
      - Sorting by each column, both directions, and pagination at 15
        rows with a working second page.

- [ ] 3. Frontend: "Planned hours" tab
      Add a `planned-hours` entry to the `tabs` array in
      `resources/js/pages/Reports/Index.vue`, as the last tab. Add
      `plannedHoursFrom`/`plannedHoursTo` date refs (via `DateInput`)
      and sort refs, seeded from `props.filters`, included in the
      existing `reload()` watcher.

      Tab body: two `DateInput`s ("From" / "To"), then a sortable,
      paginated table (Workcenter, Date, Hours columns) from the
      `plannedHoursReport` prop, reusing the Workcenter report's sort-
      button and pagination-footer patterns.

      Add `reports.tab.planned_hours` and `reports.planned_hours.*`
      language keys to `resources/lang/en.json`.

      `tests/js/Reports.test.js` covers:
      - The tab renders rows from the `plannedHoursReport` prop.
      - Changing either date input navigates with the new
        `planned_hours_from`/`planned_hours_to` params.
      - Clicking a column header sorts, and clicking again flips
        direction.
      - The pagination footer shows only when there is more than one
        page.

- [ ] 4. CSV export
      Add a `GET /reports/planned-hours/export` route and
      `ReportController::exportPlannedHours(Request $request)` method,
      reusing the same query as `plannedHoursReport()` without
      pagination, streamed as `text/csv` via `fputcsv` (header row:
      Workcenter, Date, Hours).

      Add an "Export CSV" link next to the date inputs in
      `Reports/Index.vue`, pointing at the export route with the
      current `planned_hours_from`/`planned_hours_to` params, styled to
      match `ButtonPrimary` (a plain `<a>` with the same classes, since
      no button-as-link component exists yet).

      `tests/Feature/ReportsTest.php` covers:
      - The export route returns a `text/csv` response with the header
        row and one row per matching workcenter/date/hours combination,
        for a given date range.
      - A draft assignment in range is excluded from the export, same
        as the on-screen report.

- [ ] 5. Full-suite regression check
      Run the PHP and JS test suites in full. Fix any regression this
      feature introduced. No new behavior in this step.
