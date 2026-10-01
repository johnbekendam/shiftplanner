# Demand — Plan

Status: done — 6/6

- [x] 1. Rename Schedule to Demand. Move `WorkcenterShiftAssignmentController` to `DemandController`, the routes to `/demand`, the page to `Demand.vue`, the i18n keys to `demand.*` and the nav label to "Demand". Redirect `/schedule` to `/demand`. Rename the tests.
- [x] 2. Scope the page to one workcenter. `GET /demand?workcenter=<id>` sends the workcenter list, the selected workcenter, its shifts with weekday defaults, its date overrides and the assigned count per shift and date. Fall back to the first active workcenter. The page shows the workcenter select and the "Default demand" card. The add row lists only shifts that the workcenter does not have yet.
- [x] 3. Move the date override routes to `PUT|DELETE /demand/{workcenter}/{shift}/{date}`. Remove the slot menu and the reset button from the planning day header.
- [x] 4. Add the demand calendar. Fill each day from slots and assigned counts (success, warning, muted), with pending edits. Add the override border and the legend. A click selects a day, a second click deselects it.
- [x] 5. Add the date card. One row per shift with the assigned count and a slot input (minimum: assigned count). Show the override border on an input. Add "Reset to default" in the footer. Keep date edits pending. A value equal to the weekday default removes the override. Save writes the changed dates with the other edits.
- [x] 6. Persist the selection. Store the workcenter and the day in `sessionStorage` per user, and restore them on a visit without `?workcenter`. A workcenter change with unsaved edits opens the Stay / Discard dialog.
