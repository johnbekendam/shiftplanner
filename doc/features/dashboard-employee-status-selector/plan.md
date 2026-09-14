# Dashboard Employee Status Selector — Plan

Status: done — 5/5

Spec: `spec.md`. Add a URL-backed dashboard selector for confirmed,
unconfirmed, and stacked employee groups. Stacked mode renders confirmed capacity as
the lower stacked line and unconfirmed capacity above it.

- [x] 1. **Reuse check and test infrastructure.** Search for existing dashboard
  status/filter helpers and verify PHP/Vitest test runners are configured.
- [x] 2. **Failing tests.** Add backend tests for confirmed, unconfirmed, both,
  invalid-query fallback, and status-separated line payloads. Add frontend tests
  for the selector labels, URL navigation, and stacked series passed to the
  chart in both mode.
- [x] 3. **Backend implementation.** Update `DashboardController@index` to
  accept the employee-status query value, compute confirmed and unconfirmed
  series for each block, and expose enough status-specific coverage data for
  the frontend to select the right totals.
- [x] 4. **Frontend implementation.** Add the always-visible three-option
  selector, drive it from the URL query string, and pass either a single series
  or stacked series to `FteLineChart` and `CoverageDonut`.
- [x] 5. **Full verification and docs status.** Run the full PHP and JS test
  suites plus the build, fix regressions caused by this feature, and update this
  plan status.