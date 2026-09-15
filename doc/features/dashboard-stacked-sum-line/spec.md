# Dashboard — Single Sum Line in Stacked View — Spec

## Problem

The Stacked dashboard view draws two lines per chart: confirmed
availability (brand blue, bottom) and the confirmed+unconfirmed total
(gray, top boundary). The gray top line already equals the sum, but it
reuses the same gray as the Unconfirmed-only view, so a manager cannot
tell the two apart at a glance. Two lines also read as more detail than
Stacked mode intends to show.

## Solution

In Stacked mode, the chart shows one line only: confirmed +
unconfirmed, in a new dedicated color. The confirmed line and the gray
total-boundary line no longer render in this mode.

- `FteLineChart` already receives `available` as the combined series
  when `both` is active (`DashboardController` sums confirmed and
  unconfirmed). `Dashboard/Index.vue` stops passing `baseAvailable` for
  the `both` filter, so the base line never renders.
- `availableStroke` for the `both` filter becomes
  `var(--color-badge-success-text)` (green), replacing the current gray.
  Confirmed-only and Unconfirmed-only modes are unchanged.
- The Stacked button in the employee-status selector gains a line
  marker in the same green, matching the existing pattern where
  Confirmed and Unconfirmed buttons carry a marker in their line color.
- The coverage donut is unchanged: it already sums confirmed and
  unconfirmed hours regardless of which lines are drawn.

## Key decisions

- **Reuse `badge_success_text`, no new color role.** The styling rules
  gate new component vars and roles behind a user discussion. An
  existing badge-family color (green, already distinct from brand blue
  and text-secondary gray) covers this without adding one. Confirmed
  keeps `--color-brand-bg`; Unconfirmed keeps `--color-text-secondary`;
  Stacked's sum line takes `--color-badge-success-text`. Three visually
  distinct colors, zero new tokens.
- **Drop the base line in Stacked mode, don't add a fourth series.** The
  combined total is already the only figure Stacked mode is meant to
  show per the new requirement. Removing `baseAvailable` for `both` is
  simpler than teaching `FteLineChart` a new "solo stacked" prop.
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
