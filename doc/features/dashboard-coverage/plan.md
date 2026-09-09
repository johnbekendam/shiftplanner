# Dashboard — Weekdays Only + Coverage Donut — Plan

Status: done — 4/4

Spec: `spec.md`. Roadmap phase 3, after `features/business-lines/`. Drop
weekend columns from the dashboard charts and add a per-card coverage
donut (available vs required hours).

- [x] 1. **Backend: weekday-only period and coverage hours.**
  `DashboardController@index` filters the `CarbonPeriod` to weekdays
  before building `$days` and the FTE series (`->filter(fn ($d) =>
  ! $d->isWeekend())->values()`). Each payload block gains
  `available_hours` = `array_sum(series) * fte_hours / 5` and
  `required_hours` = `target * $days->count() * fte_hours / 5`; the
  overall block uses the summed business-line targets. `availableFte()`
  keeps its `isWeekend()` guard. Rewrite `test_a_weekend_day_contributes_zero`
  to assert `days: []`, `overall.available: []`, and
  `overall.required_hours: 0` for a Saturday-only period. Add feature
  tests: weekend dates are absent from `days` for a Mon–Sun period;
  `available_hours` and `required_hours` on the overall and a line block
  for a known period (e.g. one weekday, one full-timer, `target_fte` 5,
  `fte_hours` 40 → available 8, required 40); `required_hours` is 0 when
  `target_fte` is 0. Full PHP suite green.

- [x] 2. **Frontend: `CoverageDonut.vue`.** New
  `resources/js/components/CoverageDonut.vue` — bespoke SVG in the style
  of `FteLineChart.vue`. Props `available` (Number), `required` (Number).
  Renders a track circle in `var(--color-border)` and an arc in
  `var(--color-brand-bg)` with `stroke-dasharray` set from
  `min(available / required, 1)`; center `<text>` shows
  `Math.round(available / required * 100) + '%'`, or "—" when `required`
  is 0 (no arc then). Caption below via `dashboard.hours_ratio`
  (`:available` / `:required`, "h" suffix) in
  `var(--color-text-secondary)`. `role="img"` + `<title>` from
  `dashboard.coverage`. `en.json`: `dashboard.hours_ratio`,
  `dashboard.coverage`. Vitest `CoverageDonut.test.js`: 60/100 →
  arc dash fraction ~0.6 and center "60%"; 150/100 → arc capped (full)
  and center "150%"; required 0 → center "—", no arc,
  caption "0 / 0 h". `npm run test` and `npm run build` green.

- [x] 3. **Wire the donut into the dashboard.** `Dashboard/Index.vue`
  `blocks` computed carries `available_hours` / `required_hours` from
  the overall and line payloads. Card body becomes a flex row
  (`flex flex-wrap items-center gap-4` or similar): `FteLineChart`
  wrapped in a `flex-1 min-w-0` container, `CoverageDonut` in a fixed
  `w-28`-ish container. Update `DashboardIndex.test.js`: the mounted
  props gain `available_hours` / `required_hours`; assert one
  `CoverageDonut` per block and that block 1 (PMP) receives the expected
  numbers. `npm run test` and `npm run build` green.

- [x] 4. **Docs and full checks.** `doc/roadmap.md` phase 3 and
  `doc/concept.md` Dashboard section note the weekday-only charts and the
  per-card coverage donut. Set this `plan.md` header to `4/4` and check
  the boxes. Run Pint, `php artisan test`, `npm run test`,
  `npm run build`, and `php artisan migrate` on the dev database — all
  green. No migration is added by this feature; the `migrate` run is just
  the standard check.

## Not done / deferred

- Everything under the spec's "Non-goals": threshold colouring, changes
  to `FteLineChart`, the month-tick edge, new colour roles or UI
  components, period-model changes, a per-day hours breakdown, and any
  planner use.
- Manual browser pass — AGENTS.md leaves UI verification to the user.
