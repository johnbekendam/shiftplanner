Status: complete — 11/11

- [x] 1. Add the `EmployeeAvailability` resolver for weekly defaults and
  holidays. Build it from loaded relations and from `PlanProblem`
  arrays. Switch `SchedulingEligibility`, `PlanEligibility`, `PlanScorer`
  and `PlanningVerifier` to it. Unit tests for the resolver. The existing
  suite stays green.

- [x] 2. Add the start date. Migration for `employees.available_from`.
  The resolver returns `not_started` before it. `not_started` becomes a
  block reason, a verification code and a planner block. The admin and
  personal update endpoints accept the field. Both page payloads
  include it. Feature tests for each.

- [x] 3. Add date overrides to the back end. Migration and
  `AvailabilityOverride` model. The resolver applies whole-day blocks
  and shift overrides. Add `PUT .../availability/dates/{date}` on the
  admin and personal routes. It replaces the rows of the date, audits
  the change and respects the change lock. Both page payloads include
  all overrides. The application archive includes the new table. An
  older archive without it restores it empty. Feature tests.

- [x] 4. Add shifts per weekday. `Employee::shiftWeekdays()` returns the
  ISO weekdays each effective shift runs on. The weekday routes accept
  1–7. The weekday and date write paths reject a shift that does not
  run that day. Each shift in both page payloads has a `weekdays` list.
  Tests.

- [x] 5. Record `available_from` and the date overrides in the backup
  import audit snapshot, so an import that changes them is audited. The
  legacy employee-configuration format is import-only and has no such
  data, so it stays as it is. Tests.

- [x] 6. Show Monday to Sunday in `AvailabilityGrid`. Show a cell only
  where the shift runs. Hide a weekday column without running shifts.
  Vitest.

- [x] 7. Add a `dayBorders` prop (solid or dashed per day), a
  `borderLegenda` prop and a `highlightSelection` prop to `Calendar.vue`.
  Vitest.

- [x] 8. Add `utils/availabilityCalendar.js`. It gives the day status,
  fill and border from the start date, holidays, defaults, overrides and
  shifts per weekday. It mirrors the resolver order. Vitest.

- [x] 9. Add `DayAvailabilityDialog.vue`. It edits the shift levels, the
  whole-day block and the reset of one date. Vitest.

- [x] 10. Add `AvailabilityCalendar.vue`: the calendar, legend and day
  dialog. `Calendar.vue` emits `day-click` for a day button only.
  Integrate it in the personal page with the start date field, the
  two-column layout and a `dates` save registry entry. The personal
  card uses a new `wide` `CenteredLayout` width on the Availability tab.
  The columns use a container query, so they stack in a narrow card.
  Vitest.

- [x] 11. Integrate the same layout, start date and `dates` save entry in
  the admin employee page. The card widens on the Availability tab.
  Vitest.
