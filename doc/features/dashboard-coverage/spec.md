# Dashboard — Weekdays Only + Coverage Donut — Spec

Roadmap phase 3. A follow-up to `features/business-lines/`. Two changes
to the FTE dashboard: drop weekend columns from the charts, and give each
card a donut showing how much of its required hours are covered.

## Problem

The dashboard charts available FTE for every day in the planning period.
Weekend days always sit at zero, so every card's line dips to the floor
twice a week — noise that carries no information, since nobody is
scheduled on a weekend.

The cards also answer only "how does availability move day to day". They
do not answer "over the whole period, do I have enough hours for this
business line". A manager has to read the area between the line and the
target by eye.

## Solution

### Weekdays only

`DashboardController` filters the `CarbonPeriod` to weekdays (Mon–Fri)
before it builds `days` and the FTE series. Weekend dates never reach the
payload, so the x-axis holds working days only.

- `availableFte()` keeps its `isWeekend()` guard as a harmless defensive
  check; it can no longer fire from the dashboard path.
- A period with no weekday in it (for example a single Saturday) yields
  `days: []`, empty series, and `required_hours` / `available_hours` of
  `0`. Both charts render empty and the donut shows "—". This is not
  treated as an unset period; the "Set a period" prompt still shows only
  when the period itself is missing or invalid.

### Coverage hours

One FTE is `fte_hours` per week. In the weekday-only model that is
`fte_hours / 5` per working day. For each card:

- `available_hours` = `sum(daily available-FTE series) * fte_hours / 5`
- `required_hours` = `target * weekdayCount * fte_hours / 5`, where
  `target` is the card's `target_fte` (the overall card uses the summed
  business-line targets) and `weekdayCount` is `count(days)`.

Both are computed in `DashboardController` and added to each payload
block as `available_hours` and `required_hours` (floats). The coverage
math lives next to the FTE logic so it is covered by the PHP feature
tests; the front end only formats.

### Coverage donut

A new `resources/js/components/CoverageDonut.vue` — a bespoke SVG
component in the same style as `FteLineChart.vue` (no charting library).
Props: `available` (Number), `required` (Number).

- Sits to the right of the line chart inside each card body. The body is
  a flex row: the line chart flex-fills, the donut is a fixed width
  (~7rem) and wraps beneath the chart on the narrowest cards.
- Ring: an arc in `var(--color-brand-bg)` over a full-circle track in
  `var(--color-border)`. Fill fraction is `min(available / required, 1)`,
  so the ring caps at a full circle; it never over-winds.
- Center label: `Math.round(available / required * 100)` followed by
  `%`. The true value shows even above 100 % (for example "140%"). The
  label box is sized for three digits plus the percent sign.
- When `required` is `0`: the center shows "—" and the ring is the empty
  track only.
- A caption under the donut shows the raw figures, formatted through
  `dashboard.hours_ratio` (for example "480 / 800 h"), in
  `var(--color-text-secondary)`. When `required` is `0` it reads
  "0 / 0 h".

`Dashboard/Index.vue` passes `block.available_hours` and
`block.required_hours` into `CoverageDonut` alongside the existing
`FteLineChart`.

### i18n

`en.json` gains:

- `dashboard.hours_ratio` — the caption, with `:available` and
  `:required` placeholders and an "h" unit suffix.
- `dashboard.coverage` — the donut's `aria-label` / `<title>` text.

No user-facing string is hardcoded in the component.

## Key decisions

- **Filter weekends in the controller, not the chart.** The chart stays a
  dumb renderer of whatever `days` and series it is handed. Dropping the
  dates at the source keeps `days`, the series, and any future
  export in step, and leaves `FteLineChart` untouched.
- **A weekday-less period is empty, not unset.** The period is validly
  configured; it just contains no working days. Showing empty charts and
  a "—" donut is consistent with the zero-target rule and needs no
  special-case branch. The existing `test_a_weekend_day_contributes_zero`
  is rewritten to assert the empty payload.
- **Coverage math in PHP.** `available_hours` and `required_hours` are
  domain figures with the `fte_hours / 5` rule baked in. Computing them
  in the controller keeps that rule in one place with the FTE logic and
  puts them under the feature-test suite. The Vue side only rounds and
  formats.
- **Real person-hours over the period.** `available / required` compares
  the actual coverage-hours the chart area already represents, totalled.
  The ratio is independent of `fte_hours`. Rejected: FTE-days (the ask
  said "hours") and a weekly-equivalent snapshot (does not grow with the
  period, so it hides a short or long period).
- **One brand colour, ring caps at full.** No red/amber/green thresholds
  — that would need new colour roles, which the design rules gate behind
  a discussion, and the center number already carries the "over/under"
  reading. The ring geometry stays honest by clamping the fill; the label
  stays honest by showing the true percentage.
- **"—" for a zero target.** Coverage is undefined with nothing required.
  A "0%" would misread as "no coverage". Hiding the donut would make
  cards structurally inconsistent.
- **Raw hours as a caption.** The percentage answers "how close"; the
  manager still needs the absolute hours to act. A caption is lighter
  than a second ring segment and always visible, unlike a tooltip.
- **`CoverageDonut` is a new component without a design discussion.** The
  design rules gate new Input, Button, Card, and Layout components. A
  bespoke SVG chart is none of those; `FteLineChart` set the precedent
  for a hand-rolled, token-styled chart on this page.

## Non-goals

- Threshold or gradient colouring of the donut.
- Any change to `FteLineChart.vue` beyond it receiving weekday-only data.
- Fixing the line chart's month-label tick when the 1st of a month falls
  on a weekend (a pre-existing cosmetic edge).
- New colour roles or component `--color-*` vars.
- New Input / Button / Card / Layout components.
- Changes to the planning period model, `fte_hours`, or the Settings
  page.
- A per-day or per-week hours breakdown; the donut is a period total.
- Any planner or `/solve` use of the coverage figures.
