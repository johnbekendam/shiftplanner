# Dashboard — Demand Line — Spec

A grilling session with the user settled this design before this document
was written. It replaces the line set of
`features/dashboard-planned-hours-line/`.

## Problem

The dashboard has four line toggles: Unconfirmed, Confirmed, Total and
Planned. Unconfirmed is not necessary anymore, and Total is then the same
as Confirmed. The chart does not show the demand, so a manager cannot
compare the work to do with the capacity and the planning.

## Solution

### Line toggles

The legend has three checkboxes, in this order:

| Checkbox | Line | Color token |
| --- | --- | --- |
| Demand | slots of all workcenters per week, as FTE | `--color-brand-bg` (blue) |
| Available | availability of the confirmed employees | `--color-badge-success-text` (green) |
| Planned | planned hours per week, as FTE | `--color-badge-warning-text` (amber) |

- Available and Planned are on by default. Demand is off.
- The query string holds the set, for example `?lines=demand,planned`.
  The default set gives no query string. An empty `lines` value turns
  all lines off. An unknown value is ignored. This includes the old
  values `confirmed`, `unconfirmed` and `total`.
- The dashed target line stays on every card.

### Available line

Available is the old Confirmed line with a new name. It counts the
confirmed employees only. The calculation does not change.

The payload keys become `available` (the series) and `available_hours`
(the donut value). The keys `available_confirmed`,
`available_unconfirmed`, `available_total`, `available_hours_confirmed`
and `available_hours_unconfirmed` are removed.

### Demand line

`DashboardController` adds a `demand` series to the overall block only.
The series has one value per weekday in `days`.

- The source is each shift of each active workcenter. An archived
  workcenter does not count.
- The slots of a date are the date override, or the weekday default if
  there is no override.
- The demand hours of a date are the slots multiplied by
  `Shift::durationHours()`.
- Each weekday gets the total of its full week (Monday to Sunday),
  divided by `fte_hours`. Weekend demand counts. Days of that week
  outside the period also count.
- The line is a step line, as the Planned line.

A business-line block has no `demand` series. Its chart shows Available
and Planned only.

### Donuts

Each card has two donuts, one below the other, at the right of the chart.

| Donut | Value | Shows when |
| --- | --- | --- |
| Available | `available_hours` / `required_hours` | the Available line is on |
| Planned | `planned_hours` / `available_hours` | the Planned line is on |

- The Available donut is the old coverage donut. Its caption is
  "Available", not "Confirmed".
- `planned_hours` is new in the payload, on the overall block and on
  each business-line block. It is the sum of the `planned` series
  multiplied by `fte_hours / 5`, the same rule as `available_hours`.
- The Planned donut shows "—" when `available_hours` is 0.
- `CoverageDonut` gets a `label` prop for the caption.

## Key decisions

- **Demand on the Overall card only.** Demand belongs to a workcenter,
  and a workcenter has no business line. A split per business line has
  no source.
- **Weekly step, the same math as Planned.** The two lines use the same
  unit and the same week totals. Planned touches Demand when all slots
  of the week are filled.
- **Active workcenters only.** The Demand page and the planning page
  also hide archived workcenters.
- **Available keeps the confirmed-only rule.** The user asked for a new
  name, not a new calculation.
- **Unconfirmed data is removed, not hidden.** No screen reads it
  anymore.
- **Demand takes the blue of Total.** The token is free and is distinct
  from green and amber. No new role or component color var is
  necessary.
- **Demand is off by default.** The user asked for this after a look at
  the result. Demand is one click away.
- **A donut follows its line.** A donut with no matching line on the
  chart has no context.
- **Planned relative to Available.** The Planned donut shows how much of
  the capacity is in use.
- **No What's new entry.** The user decided this.

## Non-goals

- No relation between workcenters and business lines.
- No change to the donut math for Available. It keeps confirmed hours
  divided by required hours.
- No donut for Demand.
- No change to the dashed target line.
- No change to the Planned line.
- No per-day demand line.
- No change to how an employee becomes confirmed.
