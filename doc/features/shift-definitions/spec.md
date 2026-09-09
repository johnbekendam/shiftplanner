# Shift Definitions — Spec

Roadmap phase 3. This feature adds the "standard day schedule" shift
record that phases 3 and 4 anticipated. It also replaces the recurring
availability grid's three fixed dayparts with the defined shifts.

## Problem

The data model has no shift record. A manager cannot state that the team
works, for example, an Early, a Late, and a Night shift, each with a
clock range.

The recurring availability grid uses three hardcoded dayparts (Morning,
Afternoon, Evening). The `employee-availability` spec left these as a
placeholder and said phase 3 would map them to named shifts. There is no
shift to map to yet, and an employee sees dayparts that do not match how
the team names its real shifts.

## Solution

### Shift record

A new `shifts` table:

| column | notes |
| --- | --- |
| `id` | |
| `name` | required, case-insensitive unique, max 50 |
| `start_time` | `time` |
| `end_time` | `time`, strictly after `start_time` |
| timestamps | |

A `Shift` model with a default scope that orders by `start_time` then
`name`, a `recurringAvailabilities()` `hasMany`, and a `toPayload()`
returning `{ id, name, start_time, end_time }` with the times as `H:i`
strings.

There is no manual order and no `position` column. Start time is the
order everywhere.

### Settings page — new "Shifts" tab

`/settings` stays admin-only. The tabbed card gains a **Shifts** tab
after **Business lines** and before **Period**. It uses inline
multi-field rows in the style of the Business lines tab:

- Each row has a name `TextInput` and two `TimeInput` fields
  (`start_time`, `end_time`). A field that loses focus, or `Enter`,
  sends `PUT /settings/shifts/{shift}` with the whole row and reloads.
  The server re-checks the rules.
- A delete button behind a plain confirm ("Delete this shift? Any
  availability set for it will be lost."). On confirm,
  `DELETE /settings/shifts/{shift}`. The row goes; the database cascade
  drops every availability cell that referenced it.
- An add row sends `POST /settings/shifts` with the three fields.
- An empty state when no shift exists.

There are no up/down buttons. The list always shows in start-time order.

`ShiftController` has `store`, `update`, and `destroy`. Validation is
shared: `name` required, string, max 50, case-insensitive unique
(ignoring self on update); `start_time` and `end_time` required,
`date_format:H:i`; `end_time` `after:start_time`.
`SettingsController@index` adds `shifts` as
`{ id, name, start_time, end_time }` in start-time order.

Routes behind `admin`: `POST /settings/shifts`,
`PUT /settings/shifts/{shift}`, `DELETE /settings/shifts/{shift}`.

### Recurring availability grid — daypart becomes shift

`recurring_availabilities.daypart` becomes `shift_id`, a foreign key to
`shifts` with `cascadeOnDelete`. The unique key becomes
`(employee_id, weekday, shift_id)`. The table keeps its name. `level`
(`not_preferred`, `unavailable`) is unchanged; `available` is still the
absence of a row.

`RecurringAvailability::DAYPARTS` is removed. `RecurringAvailability`
gains a `shift()` `belongsTo` and its `toPayload()` returns
`{ weekday, shift_id, level }`.

The per-cell routes change the `daypart` segment to a numeric `shift`
segment, model-bound:

- Manager: `PUT /employees/{employee}/availability/{weekday}/{shift}`
- Employee: `PUT /personal/{token}/availability/{weekday}/{shift}`

`weekday` stays `1`–`7`. `shift` matches `[0-9]+`.
`SetsRecurringAvailability::setCell` takes a `Shift` instead of a
`daypart` string. A `level` of `available` deletes the row; the other
levels upsert on `(weekday, shift_id)`.

The `edit` and `show` payloads gain a `shifts` array
(`{ id, name, start_time, end_time }`, start-time order) and change the
`availability` array items to `{ weekday, shift_id, level }`.

### Availability grid UI (`AvailabilityGrid.vue`)

- Rows are the defined shifts in start-time order. Columns stay the
  seven weekdays, Monday to Sunday.
- The row header shows the shift name, with `start – end` below it in
  secondary text (for example `06:00 – 14:00`).
- The cell button, its three-state cycle, the badge colours, the icons,
  and the legend are unchanged. The cell key and the write URL use the
  shift id.
- When no shift is defined, the grid area shows "No shifts are defined
  yet." On the manager editor the message adds "Add them on the
  Settings page." The Holidays section below still renders.

The grid shows on both the manager editor and the personal page, the
same as before.

## Key decisions

- **The `shifts` table is the phase-3 standard-day-schedule shift.** It
  carries a name and a clock range only. Required headcount and the
  workcenter link come in a later phase.
- **Start time is the only order.** A shift's place in the list and in
  the grid follows its clock start. No `position` column and no reorder
  UI. Ties break on name.
- **End must be after start. No overnight shift.** The planning model is
  a single day. A wrap-around range has nothing to consume it and would
  force every reader to handle midnight. A later phase can lift this.
- **Daypart is replaced, not kept alongside.** The three-daypart model
  was always a placeholder for named shifts. `daypart` becomes
  `shift_id`. The synthetic recurring-availability rows are dropped by
  the migration; there is no back-compat path.
- **Delete cascades with a plain confirm.** A shift delete removes the
  availability cells that referenced it through the database cascade.
  The confirm states that, without a count. Counting affected employees
  adds a query for little value in a prototype.
- **Inline rows, no shared component.** `OrderedNameList` is
  single-field. A shift has three fields, so the tab gets its own row
  component with the Business lines tab's blur-to-save feel, minus the
  up/down buttons.
- **Empty state, holidays kept.** A manager who has not defined shifts
  still sees the Holidays section and a message that explains the empty
  grid, rather than a silently missing section.

## Non-goals

- Assigning shifts to workcenters, and the required-headcount figure.
- Calendar recurrence or date-specific exceptions for shifts.
- Overnight (wrap-around) shifts.
- A per-Business-Line shift set. Shifts are global here.
- Changes to the `/solve` contract or any planner code.
- A shift-to-daypart compatibility layer or a data migration of
  existing availability rows.
- Manual shift ordering.
