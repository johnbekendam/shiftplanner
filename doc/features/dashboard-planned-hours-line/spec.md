# Dashboard — Planned Hours Line and Line Toggles — Spec

## Problem

The dashboard shows available capacity (Unconfirmed, Confirmed, Stacked)
against a target. It does not show how many hours are already planned. A
manager cannot compare planned hours with capacity per week.

The selector also allows only one view at a time. The name "Stacked" no
longer fits when a planned line joins the chart.

## Solution

### Line toggles

The single-choice selector becomes a legend of four checkboxes, in this order:

| Button | Line | Color token |
| --- | --- | --- |
| Unconfirmed | unconfirmed availability | `--color-text-secondary` (gray) |
| Confirmed | confirmed availability | `--color-badge-success-text` (green) |
| Total | confirmed + unconfirmed availability | `--color-brand-bg` (blue) |
| Planned | planned hours per week, as FTE | `--color-badge-warning-text` (amber) |

- Each checkbox switches its own line on or off. Any combination is
  valid. With all lines off, the chart shows the target line only.
- Each checkbox is a `CheckboxInput` with its label. A line in the
  line color underlines the checkbox and label, so the row is the chart
  legend. The row uses small text with a clear gap between items.
- The default set is Confirmed + Planned.
- The query string holds the set, for example `?lines=confirmed,planned`.
  The default set gives no query string. With no `lines` parameter, the
  default set applies. An empty `lines` value turns all lines off. An
  unknown value is ignored. The old `?employees=` parameter is removed.
- The "unconfirmed employees are not included" notice is removed. The
  legend already shows which lines are on.

### Planned line

`DashboardController` adds a `planned` series to the overall block and to
each business-line block. The series has one value per weekday in
`days`.

- The source is every `ShiftAssignment`, published and draft, for every
  employee, confirmed or not.
- Hours per assignment come from `Shift::durationHours()`.
- Each weekday gets the total of its full ISO week (Monday to Sunday),
  divided by `fte_hours`. Weekend shifts count. Days of that week outside
  the period also count.
- The overall block counts all assignments. A business-line block counts
  the assignments of employees in that business line. An employee with no
  business line counts on the overall block only.

The controller always sends all series (confirmed, unconfirmed, total,
planned). Vue selects the lines to draw. `FteLineChart` takes a list of
lines instead of fixed line props, and scales its y-axis to the visible
lines and the target.

## Key decisions

- **Weekly FTE step line on the existing chart.** The chart y-axis is FTE
  per weekday. Weekly hours divided by `fte_hours` give the same unit, so
  the planned line compares directly with capacity and target. A flat
  step per week shows "per week" as asked.
- **Published and draft assignments count.** The line shows planning
  progress while a week is still in draft. It does not match the Planned
  hours report, which counts published assignments only.
- **Full ISO week totals.** A period can start or end mid-week. Totals of
  only the in-period days would make the edge weeks drop in error.
- **Planned counts all employees.** Toggles replace the status filter, so
  each line shows its own group. The planned line is not split by status.
- **Business line comes from the employee.** Workcenters have no business
  line. This matches how availability is split per card.
- **Toggles instead of a single choice.** The user wants to show and hide
  each line on its own.
- **Checkboxes, not buttons.** Four `ButtonSecondary` toggles were too
  large, and the outlines of two active neighbors almost touched. A
  checkbox legend is compact, shows on and off clearly, and reuses
  `CheckboxInput` with no custom styling.
- **"Total" replaces "Stacked".** The lines are not stacked. "Total"
  sits next to Unconfirmed and Confirmed, so it reads as their sum.
- **Amber from an existing token.** `--color-badge-warning-text` is
  distinct from gray, green, and blue. No new role or component var is
  necessary.
- **Series selection moves to Vue.** The controller sends every series.
  The query string only controls which lines Vue draws, so the backend
  has no filter logic.

## Non-goals

- No change to the coverage donut. It keeps confirmed hours divided by
  required hours.
- No change to availability, FTE, or target calculations.
- No new theme role or component color var.
- No change to the Planned hours report.
- No per-day planned line.
