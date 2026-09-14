# Dashboard Employee Status Selector — Spec

## Problem

The dashboard currently shows only confirmed employees. A manager cannot
compare confirmed capacity with employees who are not confirmed yet, so the
dashboard hides useful planning risk.

## Solution

Add a three-option selector at the top of the dashboard:

- Confirmed
- Unconfirmed
- Both

The selected option is stored in the dashboard URL query string so refreshes,
bookmarks, and shared links keep the same view. Confirmed remains the default
when no query value is present.

The backend sends separate confirmed and unconfirmed availability series for
the overall dashboard card and each business-line card. The frontend derives
the displayed chart and coverage numbers from the selected mode:

- Confirmed: show confirmed availability only.
- Unconfirmed: show unconfirmed availability only.
- Both: show two stacked lines with confirmed on the bottom and unconfirmed
  stacked above it. Coverage totals use confirmed plus unconfirmed.

## Key Decisions

- **Use a URL-backed selector.** The chosen view is shareable and survives a
  refresh. Invalid or missing query values fall back to confirmed.
- **Keep confirmed as the default.** This preserves the current dashboard view
  unless the manager chooses a broader or different status.
- **Always show all three options.** The control uses visible labels instead of
  a dropdown so the available modes are immediately clear.
- **Both means combined totals.** The stacked line and coverage donut both use
  confirmed plus unconfirmed capacity, with confirmed as the bottom line and
  unconfirmed as the top stack.
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