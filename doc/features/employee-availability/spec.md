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

### Part 2 — Recurring blocks (design only)

No code this increment. Build after phase 3 delivers named shifts.

New table `recurring_availabilities`:

| column | notes |
| --- | --- |
| `id` | |
| `employee_id` | foreign key, cascade on employee delete |
| `weekday` | ISO day number, 1 (Monday) to 7 (Sunday) |
| `daypart` | `morning`, `afternoon`, or `evening` |
| `level` | `blocked` (hard) or `avoid` (soft) |
| timestamps | |

- One model, two levels. `blocked` is a hard constraint the planner never
  violates. `avoid` is a soft penalty in the objective: the planner
  avoids it but may override it for coverage or fairness.
- This model replaces the removed `morning/evening/either` preference.
- `daypart` is a placeholder set. Phase 3 tags each shift with a daypart.
  The planner then expands one row onto every shift that matches the
  weekday and daypart.
- A later increment puts the recurring UI on the same **Availability**
  tab.

### `/solve` contract sketch

- Holidays become per-date hard unavailability for the employee.
- Recurring `blocked` rows become hard constraints.
- Recurring `avoid` rows become penalty terms in the objective.
- Daypart-to-shift resolution uses the phase-3 shift daypart tag.

Fairness weights and shift-level single-day exceptions stay deferred to
the phase-4 fairness session.

## Documentation updates

- `doc/roadmap.md` — phase 4 row notes that holidays shipped and the
  recurring model is specified here.
- `doc/concept.md` — the employee record now carries holidays. The
  detailed availability model is partly resolved.

## Key decisions

- **Holidays now, recurring later.** Holidays have no phase-3 dependency.
  The recurring model needs named shifts, so it is designed now and built
  later.
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

- Any recurring-availability code.
- Manager approval or a leave-request workflow.
- Half-day or datetime holidays.
- Prorating the weekly-hours target for a holiday week. That is a phase-5
  `/solve` concern.
- Fairness weighting for `avoid` rows.
- Shift-level single-day exceptions.
- Calendar recurrence for standard day schedules. That is phase 3 and 4.
