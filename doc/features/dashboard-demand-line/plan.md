# Dashboard — Demand Line — Plan

Status: done — 4/4

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
