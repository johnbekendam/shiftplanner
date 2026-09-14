# Dashboard Employee Status Selector — Spec

## Problem

The dashboard currently shows only confirmed employees. A manager cannot
compare confirmed capacity with employees who are not confirmed yet, so the
dashboard hides useful planning risk.

## Solution

Add a three-option selector at the top of the dashboard:

- Confirmed
- Unconfirmed
- Stacked

The selected option is stored in the dashboard URL query string so refreshes,
bookmarks, and shared links keep the same view. Stacked remains the default
when no query value is present.

The backend sends separate confirmed and unconfirmed availability series for
the overall dashboard card and each business-line card. The frontend derives
the displayed chart and coverage numbers from the selected mode:

- Confirmed: show confirmed availability only.
- Unconfirmed: show unconfirmed availability only.
- Stacked: show two stacked lines with confirmed on the bottom and unconfirmed
  stacked above it. Coverage totals use confirmed plus unconfirmed.

Confirmed uses the dashboard's brand line color. Unconfirmed always uses the
secondary gray text color. The Confirmed and Unconfirmed selector buttons each
show a small line marker in the matching chart color, so the selector also acts
as the chart legend. The active selector option uses a focus-ring treatment;
the background color does not change between active and inactive options.

## Key Decisions

- **Use a URL-backed selector.** The chosen view is shareable and survives a
  refresh. Invalid or missing query values fall back to Stacked.
- **Keep Stacked as the default.** The dashboard opens with the complete
  confirmed plus unconfirmed capacity view unless the manager chooses a narrower
  status.
- **Always show all three options.** The control uses visible labels instead of
  a dropdown so the available modes are immediately clear.
- **Stacked means combined totals.** The stacked line and coverage donut both use
  confirmed plus unconfirmed capacity, with confirmed as the bottom line and
  unconfirmed as the top stack.
- **Confirmed owns the lower line color.** In Stacked mode, the lower confirmed
  line remains brand-colored and the upper unconfirmed stack boundary is gray.
  In Unconfirmed mode, the single line is gray.
- **Selector doubles as the legend.** The visible Confirmed and Unconfirmed
  choices include line markers in their chart colors, avoiding a separate legend
  below every chart.
- **Active state uses an outer focus treatment.** The selector keeps one neutral
  button background across all options and marks the active option with a
  brand-colored outline and offset, so the line-color markers remain the visual
  legend.
- **Compute status series in the controller.** The existing FTE and coverage
  rules stay on the backend. Vue chooses the active presentation but does not
  recalculate employee availability from raw employee records.

## Non-goals / Scope Boundaries

- No change to how employees become confirmed or unconfirmed.
- No new employee status beyond the existing confirmed boolean.
- No changes to scheduling eligibility or planner assignment rules.
- No new charting library.
- No manual browser verification in this workflow; the user verifies UI
  behavior.