# Employee Availability — Spec

Part of roadmap phase 4. Phase 4 is large. This increment builds the
holiday half now and writes down the recurring half for a later build.

## Problem

The planner needs to know when an employee cannot work. Two cases:

1. **Holidays** — the employee is away for a run of whole days.
2. **Recurring blocks** — the employee cannot work a part of a weekday
   every week, because of an outside obligation. For example, no Wednesday
   evenings.

Neither exists in the data model. The old `morning/evening/either` shift
preference was removed in the employee-hours feature and has no
replacement yet.

Named shifts do not exist until phase 3. Case 2 needs them. Case 1 does
not.

## Solution

### Part 1 — Holidays (build now)

A holiday is a whole-day date range that an employee is away. Every
holiday is a hard block: the planner never assigns the employee on any
date in the range.

New table `employee_holidays`:

| column | notes |
| --- | --- |
| `id` | |
| `employee_id` | foreign key, cascade on employee delete |
| `start_date` | `date`, inclusive |
| `end_date` | `date`, inclusive, must be `>= start_date` |
| `note` | nullable free text |
| timestamps | |

Rules:

- Whole days only.
- Overlapping ranges are allowed. The planner treats the union.
- Past and future dates are both allowed.
- No status and no approval step. A saved row blocks the planner at once.

Each holiday is a REST sub-resource. Add and delete only. To change a
holiday, delete it and add a new one.

- Manager: `POST /employees/{employee}/holidays`,
  `DELETE /employees/{employee}/holidays/{holiday}`
- Employee: `POST /personal/{token}/holidays`,
  `DELETE /personal/{token}/holidays/{holiday}`

### UI

The manager editor (`Employees/Form.vue`) and the personal page
(`Personal/Show.vue`) both change from a single `Card` to a `Card` with a
tab bar in its header.

- **Details** tab — the current `EmployeeFields` block (name, email,
  weekly hours). On the personal page, name and email stay read-only.
- **Availability** tab — a table of holiday rows: start date, end date,
  note, and a delete action. An add-row form sits below the table.

A new shared `Tabs` component drives the tab bar. It uses the existing
`--color-tab-*` tokens. No reusable tab component exists today.

### Part 2 — Recurring availability grid (building — see `plan-recurring.md`)

A weekly grid on the **Availability** tab. Rows are the three dayparts
(`morning`, `afternoon`, `evening`). Columns are the seven weekdays,
Monday to Sunday. Each of the 21 cells holds one state:

| state | meaning | planner |
| --- | --- | --- |
| `available` | the default. Nothing stored. | no constraint |
| `not_preferred` | the employee would rather not work this slot | soft penalty in the objective |
| `unavailable` | the employee cannot work this slot | hard constraint |

New table `recurring_availabilities`:

| column | notes |
| --- | --- |
| `id` | |
| `employee_id` | foreign key, cascade on employee delete |
| `weekday` | ISO day number, 1 (Monday) to 7 (Sunday) |
| `daypart` | `morning`, `afternoon`, or `evening` |
| `level` | `not_preferred` or `unavailable` |
| timestamps | |

- Unique on (`employee_id`, `weekday`, `daypart`). `available` is the
  absence of a row.
- This model replaces the removed `morning/evening/either` preference.
- `daypart` is a placeholder set with no clock times. Phase 3 tags each
  shift with a daypart. The planner then expands one row onto every shift
  that matches the weekday and daypart.

### Grid behaviour

- A click on a cell cycles its state: `available` → `not_preferred` →
  `unavailable` → `available`. The cell is a button, so Enter and Space
  cycle it too.
- Each change writes at once, like a holiday row. No Save button. The
  request keeps the page and the open tab, and raises no success banner.
- The three states are colour-coded through existing badge tokens
  (`--color-badge-standard-*` for available, `--color-badge-warning-*`
  for not preferred, `--color-badge-error-*` for unavailable). A legend
  sits under the grid.
- The grid shows on both the manager editor and the personal page, the
  same as the holiday table. On the create page the tab still asks the
  user to save the employee first.

### Endpoints

One per-cell route on each surface. `PUT` with a `level` of `available`,
`not_preferred`, or `unavailable`. `available` deletes any row for that
cell. The others upsert.

- Manager: `PUT /employees/{employee}/availability/{weekday}/{daypart}`
- Employee: `PUT /personal/{token}/availability/{weekday}/{daypart}`

`weekday` is `1`–`7`. `daypart` is `morning`, `afternoon`, or `evening`.
The `edit` and `show` payloads carry an `availability` array of
`{ weekday, daypart, level }` for the non-available cells.

### `/solve` contract sketch

- Holidays become per-date hard unavailability for the employee.
- Recurring `unavailable` cells become hard constraints.
- Recurring `not_preferred` cells become penalty terms in the objective.
- Daypart-to-shift resolution uses the phase-3 shift daypart tag.

Fairness weights and shift-level single-day exceptions stay deferred to
the phase-4 fairness session.

## Documentation updates

- `doc/roadmap.md` — phase 4 row notes that holidays and the recurring
  availability grid shipped.
- `doc/concept.md` — the employee record carries holidays and a recurring
  availability grid.

## Key decisions

- **Holidays first, then the grid.** Holidays had no dependency, so they
  shipped first (`plan.md`). The recurring grid follows in
  `plan-recurring.md`. It runs on abstract dayparts, so it does not wait
  for phase 3.
- **Per-cell writes, click to cycle.** Each cell writes on the click that
  changes it, the same as a holiday row. A 3-by-7 grid of dropdowns or
  segmented controls is too dense. `available` is the absence of a row.
- **Whole days only.** Plain `date` columns. The planner has no shift
  times yet, so sub-day precision has nothing to act on. A later increment
  can add half-days without breaking rows.
- **No approval workflow.** The prototype trusts both the manager and the
  employee. A saved holiday binds the planner. A leave-request flow is a
  later concern.
- **Add and delete, no edit.** A holiday row is small. Delete and re-add
  is enough and removes inline-edit state from the UI.
- **Per-row endpoints.** Each holiday is an independent sub-resource. No
  bulk sync logic on the employee save.
- **Tabbed card on both pages.** The personal page mirrors the manager
  editor, matching the earlier employee-hours decision.
- **One recurring model with a level field.** A single table serves both
  the hard block and the returning soft preference. The `/solve` contract
  carries one list with a level, not two shapes.
- **Daypart, not named shift or clock time.** Daypart is how people
  describe an obligation and it needs no phase-3 records. Employees would
  have to guess clock times for shifts they cannot see.

## Non-goals

- Clock times or shift names on a daypart. Phase 3 adds those.
- Manager approval or a leave-request workflow.
- Half-day or datetime holidays.
- Prorating the weekly-hours target for a holiday week. That is a phase-5
  `/solve` concern.
- Fairness weighting for `not_preferred` cells.
- Shift-level single-day exceptions.
- Calendar recurrence for standard day schedules. That is phase 3 and 4.
- The `/solve` service itself. This feature only stores the data.
