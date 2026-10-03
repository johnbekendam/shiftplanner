# Dashboard — Demand Line — Plan

Status: in progress — 6/7

- [x] `DashboardController` sends a weekly `demand` series on the overall
      block: slots x shift hours of the active workcenters, date
      overrides included, divided by `fte_hours`.
- [x] Remove Unconfirmed and Total, and rename Confirmed to Available:
      the payload keys become `available` and `available_hours`, and the
      legend shows Available and Planned.
- [x] Add the Demand toggle and step line to the dashboard. The line
      shows on the Overall card only. The default set is Demand,
      Available and Planned.
- [x] Add the What's new entry for managers and admins.
- [x] Demand is off by default. The default set is Available and
      Planned.
- [x] Donuts: the Available donut shows only with the Available line.
      A Planned donut below it shows `planned_hours` / `available_hours`,
      only with the Planned line.
- [ ] Remove the What's new entry.
