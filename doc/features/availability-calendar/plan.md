Status: complete — 26/26

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

- [x] 12. Move the questions from the Availability tab to a Questions
  tab after it, on both pages. Show the tab only when questions exist.
  Vitest.

- [x] 13. Put the calendar in the left column. Show the default of one
  weekday in the right column only after a click on that weekday in the
  calendar header. A second click hides it. `Calendar.vue` emits
  `weekday-click` and takes a `selectedWeekday` prop. `AvailabilityGrid`
  takes a `weekday` prop. Vitest.

- [x] 14. Replace the day dialog with a date schedule in the right
  column, in the default grid style. Move the level cell and its menu to
  `AvailabilityLevelCell.vue`, shared by `AvailabilityGrid` and the new
  `DateAvailabilityGrid`. `Calendar.vue` takes a `ringDay` prop for the
  selected date. Remove `DayAvailabilityDialog`. Vitest.

- [x] 15. A weekday letter selects the whole weekday header: a border
  around the row, and the full default week in the right column.
  `Calendar.vue` takes a `weekdayHeaderSelected` prop in place of
  `selectedWeekday`. `AvailabilityGrid` drops its `weekday` prop.
  Vitest.

- [x] 16. Hover borders: the whole weekday header row in header-selection
  mode, and one day (not its week row) when `highlightSelection` is
  false. A click anywhere on the header row selects it. Vitest.

- [x] 17. Put the right column in a card. The card header shows
  "Default week" or the date. Add a separator below the block checkbox.
  Remove the "Calendar" heading, so both cards align at the top. Vitest.

- [x] 18. Move the schedule note into the availability card, below a
  separator. Vitest.

- [x] 19. Merge the Questions tab into the Competences tab, on both
  pages. Competences and Questions are separate sections with a
  separator between them. `?tab=questions` opens the Competences tab.
  Vitest.

- [x] 20. Put the availability card below the calendar, in one column.
  Both pages return to their normal width, and `CenteredLayout` drops
  the `wide` width. Vitest.

- [x] 21. Size the availability calendar to its content (`w-fit`), so it
  does not stretch across the tab. The `Calendar.vue` legend gets
  `w-0 min-w-full`, so it wraps to the day grid's width instead of
  setting the calendar's width. Vitest.

- [x] 22. Move the hint, the legend and the schedule note into an info
  card to the right of the calendar. The card takes the remaining width.
  The availability card shows only with a selection. The legend moves to
  `CalendarLegend.vue`, and the class maps to `calendarClasses.js`, both
  shared with `Calendar.vue`. Vitest.

- [x] 23. Move the hint out of the info card to plain text above the
  calendar. Vitest.

- [x] 24. Show the hint in the card below the calendar while nothing is
  selected. A selection replaces it with the default week or the date.
  Vitest.

- [x] 25. On a holiday the date card shows only the holiday notice, not
  the block checkbox, the shift table or the reset button. Vitest.

- [x] 26. A holiday day gets the custom badge fill instead of a dashed
  border. A changed day gets a border in its own badge border color, not
  the selection color. A changed cell in the date table gets a thicker
  border in its own badge color. `calendarClasses.js` replaces the
  border style map with a border color map. Vitest.
