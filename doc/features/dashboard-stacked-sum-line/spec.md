# Dashboard — Single Sum Line in Stacked View — Spec

## Problem

The Stacked dashboard view draws two lines per chart: confirmed
availability (brand blue, bottom) and the confirmed+unconfirmed total
(gray, top boundary). The gray top line already equals the sum, but it
reuses the same gray as the Unconfirmed-only view, so a manager cannot
tell the two apart at a glance.

## Solution

In Stacked mode, the chart shows three independent lines, each at its
own true height: confirmed (green), unconfirmed (gray), and the
confirmed+unconfirmed sum (brand blue). None of the three is drawn as a
stack boundary — each plots its own raw series. The selector buttons
are ordered Unconfirmed, Confirmed, Stacked.

- `FteLineChart` gains a third line pair, `secondaryAvailable` /
  `secondaryAvailableStroke`, alongside the existing main
  (`available`/`availableStroke`) and base
  (`baseAvailable`/`baseAvailableStroke`) pair. All three render
  independently; none is computed from the others inside the chart.
- `Dashboard/Index.vue`, for the `both` filter: `available` is the
  confirmed+unconfirmed sum (brand blue), `baseAvailable` is
  `available_confirmed` (green), `secondaryAvailable` is
  `available_unconfirmed` (gray). Confirmed-only and Unconfirmed-only
  modes pass only the main line, as before, in their own color.
- `employeeFilterOptions` is ordered Unconfirmed, Confirmed, Stacked.
  Each button's marker matches its line color: gray, green, blue.
- The coverage donut is unchanged: it already sums confirmed and
  unconfirmed hours regardless of which lines are drawn.

## Key decisions

- **Reuse `badge_success_text`, no new color role.** The styling rules
  gate new component vars and roles behind a user discussion. An
  existing badge-family color (green, already distinct from brand blue
  and text-secondary gray) covers this without adding one. Unconfirmed
  keeps `--color-text-secondary`; Confirmed takes
  `--color-badge-success-text`; Stacked's sum line takes
  `--color-brand-bg`. Three visually distinct colors, zero new tokens.
- **Button order is Unconfirmed, Confirmed, Stacked.** Matches the
  requested reading order; the active-state outline and query-string
  behavior are unchanged, only the array order and per-option colors
  moved.
- **Three independent lines, not a stack.** Each of confirmed,
  unconfirmed, and the sum plots its own raw values. Keeping the old
  stacking geometry (unconfirmed drawn on top of confirmed) would make
  the unconfirmed line and the sum line coincide, since unconfirmed's
  stacked height already equals the sum — indistinguishable on the
  chart.
- **Selector gets a matching marker.** The selector already acts as the
  chart legend for Confirmed and Unconfirmed. Leaving Stacked without a
  marker would break that pattern now that Stacked has its own
  distinct color.
- **Donut untouched.** It already reads `available_hours` /
  `required_hours`, which are confirmed+unconfirmed totals computed in
  the controller. The line-drawing change doesn't affect that data.

## Non-goals

- No backend changes — `DashboardController` already sends the summed
  `available` series for the `both` filter.
- No change to Confirmed-only or Unconfirmed-only rendering.
- No change to the coverage donut.
- No new theme-builder-configurable color role.
