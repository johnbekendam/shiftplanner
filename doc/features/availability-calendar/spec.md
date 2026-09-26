# Availability Calendar — Spec

A grilling session with the user settled this design before this document
was written.

## Problem

The Availability tab on the personal page and on the employee edit page
is one long column: weekly hours, the weekly grid, questions, holidays.
The grid only holds a recurring Monday–Friday pattern. An employee cannot
say "I start on 1 November", and cannot change their availability for
one specific date. Holidays are the only date-specific data.

The rule "is employee E available for shift S on date D" also exists
three times: `SchedulingEligibility`, `PlanEligibility` and
`PlanningVerifier`, plus a not-preferred lookup in `PlanScorer`. Each
new rule would need four copies.

## Solution

### Start date

A nullable `employees.available_from` date. The employee and the admin
can both edit it. Before this date the employee is blocked for every
shift. An empty value means no restriction.

### Date overrides

A new `availability_overrides` table: `employee_id`, `date`, `shift_id`
(nullable), `level`.

- A row with a shift sets the level of that shift on that date. It
  replaces the weekly default for that date only.
- A row without a shift blocks the whole day. It also blocks shifts that
  start to run on that day later.

Any date can have overrides, past dates included.

### Resolution order

One resolver, `EmployeeAvailability`, gives the status of an employee
for a shift on a date. The first match wins:

1. The date is before `available_from`: `not_started`.
2. The date is in a holiday: `holiday`.
3. The date has a whole-day block: `unavailable`.
4. The date has an override for the shift: its level.
5. The weekly default for the ISO weekday and shift. A missing row is
   `unavailable`.

`SchedulingEligibility`, `PlanEligibility`, `PlanScorer` and
`PlanningVerifier` all use this resolver. The planner builds it from the
plain arrays in `PlanProblem`. The other callers build it from loaded
relations. `not_started` is a new block reason and a new verification
code.

### Shifts per weekday

A shift runs on an ISO weekday (1–7) for an employee when one of the
employee's workcenters has capacity (spots > 0) for it on that weekday.
An employee without a workcenter uses the capacity of any workcenter.
Only effective shifts count (`Employee::effectiveShifts()`). Date
overrides of workcenter capacity are not used.

The default grid supports Monday to Sunday. A cell shows only where the
shift runs. A weekday column where no shift runs is hidden. The weekday
routes accept 1–7. The write paths reject a cell or override for a shift
that does not run on that weekday.

### Calendar

`Calendar.vue` shows the month. The day fill shows the result of the
resolver for the shifts that run that day:

- One or more shifts `available`: success.
- Else one or more shifts `not_preferred`: warning.
- Else: error. A day without running shifts is error.

Two borders sit on top of the fill. Both use the existing
`--color-tab-active-border` token:

- Solid: the day has one or more overrides.
- Dashed: the day is in a holiday.

Days before the start date are disabled. The availability calendar does
not show the selected-day border, so the borders only carry these two
meanings. The legend explains the three fills and the two borders.

A click on an enabled day opens a dialog. For each shift that runs that
day, the dialog offers: default (shows the default level), available,
not preferred, unavailable. It also has a "Block whole day" toggle and a
"Reset to default" action. On a holiday the dialog shows a notice. The
overrides stay stored, but have no effect.

### Layout

Both pages use the same Availability tab layout:

1. Top row: start date, weekly hours, the hours warning.
2. Two columns on wide screens. Left: the calendar with its legend.
   Right: the default of one weekday, and the schedule note.
3. Holidays, full width.

The default section shows only after a click on a weekday letter in the
calendar header. It then shows the shifts that run on that weekday, each
with its default level. A click on another weekday shows that weekday.
A second click on the same weekday hides the section. Without a selected
weekday, a hint tells the user to click a weekday letter.

On narrow screens every part stacks in this order. The questions have
their own Questions tab, after the Availability tab. The tab shows only
when one or more questions exist.

### Saving

The explicit page Save writes the start date, the default grid and the
date overrides together. Day dialog edits stay in a local pending set,
keyed by date. The calendar shows pending edits. The page loads all
overrides of the employee at once, so pending edits survive month
navigation. One `PUT .../availability/dates/{date}` per changed date
replaces all rows of that date. The employee change lock applies to all
of these writes.

## Key decisions

- **Per-shift overrides plus a whole-day block.** Per-shift rows mirror
  the weekly model. The whole-day block is its own row so it also works
  on a day without running shifts, for example a weekend.
- **Holidays stay separate.** A holiday is leave with a note, and reports
  count it. An override is a change of availability. The calendar shows
  holidays, but the holiday list still manages them.
- **The start date is a hard block that both roles edit.** The employee
  knows when they can start.
- **Outcome colors, with borders for the reason.** The fill answers "can
  I work this day". The border answers "why does this day differ".
- **Any date is editable.** Earlier planning is already done, so a
  "future only" rule adds complexity without value.
- **Weekend-ready through capacity.** Weekend shifts appear by
  themselves when a workcenter gets weekend capacity.
- **One resolver before the new rules.** Four copies of the same rule
  would drift apart.
- **The default week opens from the calendar.** The calendar is the
  main view. The default of one weekday shows only when the user picks
  that weekday, so the tab stays short.
- **Load all overrides at once, not per month.** An employee has few
  rows. This removes a month endpoint and keeps pending edits simple.
- **Existing assignments are not removed.** A new start date or override
  can conflict with a planned shift. Planning verification shows it.

## Non-goals

- An end date for employment.
- Public holidays.
- Date-range selection in the calendar.
- Changes to the hours warning. It still uses only the weekly defaults.
- Changes to the reports that read the weekly grid (shift coverage,
  missing availability).
- Changes to the questions themselves and to the schedule note. Only
  their place changes.
