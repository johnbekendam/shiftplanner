# Employee Workcenter Assignments — Spec

Roadmap phase 3 continuation. `doc/features/workcenters/spec.md`
deferred "competence-to-workcenter gating." `doc/roadmap.md`'s phase 3
entry still marks "rule-based automatic planning" as needing a design
session. This feature is a smaller, related step. It lets a manager mark
which workcenter or workcenters an employee may or should work. It
enforces that mark in manual planning today. It does not add automatic
planning.

A grilling session with the user settled this design before this
document was written.

## Problem

Nothing records which workcenter or workcenters an employee is fit for
or prefers. The manual planner (`/planning`) lets any confirmed,
available employee onto any workcenter's shift slot. A manager has no
way to say "this person only works Assembly A" or "prefer this person on
Paint Booth."

## Solution

### Employee workcenter record

A new `employee_workcenter` pivot table:

| column | notes |
| --- | --- |
| `employee_id` | foreign key, cascade on employee delete |
| `workcenter_id` | foreign key, cascade on workcenter delete |
| `mode` | string, `hard` or `soft` |

The primary key is the pair (`employee_id`, `workcenter_id`), one row
per pair, like `competence_employee`. `Employee::workcenters()` is a
`belongsToMany` with `withPivot('mode')`. `Workcenter` gets no matching
`employees()` relation, since nothing reads this pivot from that
direction today.

A missing row carries the same "not set" default the recurring
availability grid and competences already use elsewhere in this app. No
row means no restriction. This feature needs no backfill migration.

### What `hard` and `soft` mean

- **No row for an employee, at any workcenter:** unrestricted. The
  employee stays assignable everywhere, exactly like today. Nothing
  changes until a manager sets a row for this employee.
- **One or more `hard` rows:** an allowlist. The employee becomes
  assignable only at their hard-marked workcenter or workcenters. An
  employee can hold more than one hard row.
- **A `soft` row:** advisory only. It never narrows eligibility, even
  when it is the employee's only row. It only flags the workcenter as
  not preferred to the manager at assignment time.

| Employee's rows | Assignable at Assembly A? | Assignable at Warehouse? |
| --- | --- | --- |
| none | yes | yes |
| hard: Assembly A | yes | no |
| soft: Assembly A | yes, no flag | yes, flagged not preferred |
| hard: Assembly A, hard: Paint Booth | yes | no |

### Editing — Employee page, Workcenters tab

Manager-only. Unlike competences and availability, the employee's
personal page gets no matching tab. The manager sets this fit-for-duty
assignment. It is not something the employee reports about themselves.
Please confirm this choice — see Key decisions.

A new **Workcenters** tab sits on `Employees/Form.vue`, after
**Competences**. It lists one row per workcenter: every non-archived
workcenter, plus any archived workcenter the employee already holds a
row for, suffixed "(archived)" so a manager can still see and clear a
stale link. Each row has a checkbox. Checking it reveals a
Requirement/Preference `SelectInput` next to it — this page's own wording
for `hard`/`soft`, distinct from `planning_rules.mode.hard`/`.mode.soft`'s
"Hard"/"Soft" wording. Unchecking a row clears it.

The tab follows the explicit-save model every other tab on this page
already uses (`doc/features/explicit-save-consolidation/`). It holds
local edits only, and writes nothing until the page's Save button runs.
Save compares pending state against last-saved state, then sends one
request per changed row through `Promise.allSettled`, matching how the
Competences tab's `registry.register(...)` block works today:

- `PUT /employees/{employee}/workcenters/{workcenter}`, body
  `{ mode: "hard" | "soft" }`. This attaches the row, or updates its
  `mode` if the row already exists (`syncWithoutDetaching`).
- `DELETE /employees/{employee}/workcenters/{workcenter}` detaches it.

A new `EmployeeWorkcenterController` (`update`, `destroy`) mirrors
`EmployeeCompetenceController`. It uses a new `TogglesWorkcenter` trait
that mirrors `TogglesCompetence`, and validates `mode` as `hard` or
`soft`.

`EmployeeController@edit` (and `@index`, for a future shift-coverage-style
summary, not required by this feature) gains two payload fields:

- `workcenters`: every eligible workcenter as
  `{ id, name, archived }`, in position order.
- `employeeWorkcenterAssignments`: this employee's rows, as
  `{ workcenter_id, mode }`.

### Enforcement — mirrors recurring availability exactly

Two new methods join `SchedulingEligibility`, next to `isUnavailable`
and `isNotPreferred`:

- `isWorkcenterIneligible(Employee, Workcenter): bool` returns true only
  when the employee holds at least one `hard` row and this workcenter is
  not one of them.
- `isWorkcenterNotPreferred(Employee, Workcenter): bool` returns true
  when the employee holds a `soft` row for this workcenter.

`EligibleEmployeeController::index` loads the target `Workcenter` and
adds `isWorkcenterIneligible` to its existing `reject()` chain, next to
the holiday, unavailable, and overlap checks. It also adds
`workcenter_not_preferred` next to `not_preferred` in the JSON it
returns.

`ShiftAssignmentController::store` adds one hard-block check, the same
shape as its existing `isUnavailable` check, and reports a new
`scheduling.error.workcenter_ineligible` message. The store method never
checks the soft case server-side, matching how it treats `not_preferred`
today.

`ShiftWeekTable.vue`'s assign popover gains a second warning-triangle
icon next to the existing `not_preferred` one, driven by
`employee.workcenter_not_preferred`.

When a manager adds or changes a hard row, the app never touches,
revalidates, or flags the employee's existing `ShiftAssignment` rows.
The new row only gates future assignment attempts. Recurring
availability works the same way today.

### Language keys

New keys: `workcenters.employee_tab` ("Workcenters," distinct from the
Settings tab's own `workcenters.*` keys already in use),
`workcenters.checklist_empty`, `workcenters.archived_suffix`,
`workcenters.mode.hard` ("Requirement"), `workcenters.mode.soft`
("Preference") — this page's own wording, deliberately distinct from
`planning_rules.mode.hard`/`.soft`'s "Hard"/"Soft" — and
`scheduling.error.workcenter_ineligible`.

## Key decisions

- **Employee page, not a workcenter-centric page.** The user chose
  editing this from the employee's side, following the Competences
  pattern already proven on this page, over a new per-workcenter roster
  page.
- **Unrestricted by default.** No row means no restriction, the same
  default recurring availability now uses after its own backfill
  migration. This feature needs no such migration, since "no row" is
  the correct default from day one rather than a changed one.
- **Hard is an allowlist, soft is advisory only.** A soft-only employee
  is never blocked from any workcenter, even with zero hard rows. This
  keeps the two levels doing one job each. Hard restricts. Soft only
  hints.
- **Enforcement mirrors recurring availability's existing hard/soft
  split exactly:** picker-list filtering plus a server-side hard block,
  with no server-side soft check. This avoids a new pattern for a
  problem the app already solves one way.
- **`mode` reuses the existing `hard`/`soft` vocabulary** that
  `PlanningRule` already stores, rather than new terms such as `level`
  or `strength` for the same concept — this is the stored/API value only.
  The displayed label on this page is "Requirement"/"Preference," not
  "Hard"/"Soft"; `planning_rules.mode.*` keeps its own "Hard"/"Soft"
  wording unchanged.
- **Manager-only, with no personal-page surface.** The manager judges
  this assignment, unlike availability and competences, which the
  employee also reports. Please confirm this choice. Grilling did not
  ask it directly.
- **Existing assignments stay untouched.** A new hard restriction never
  retroactively flags or removes assignments already on the schedule.

## Non-goals

- The automatic planner. It does not exist yet (`doc/roadmap.md` phase
  5, blocked on the phase 4 fairness design). This data takes the same
  shape as `recurring_availabilities.level` so a future solver can read
  it, but no solver code reads it here. Once phase 5 starts, its plan
  should note `employee_workcenter.mode` as an input.
- Employee self-service editing of their own workcenter assignments.
- Revalidating or flagging existing `ShiftAssignment` rows after a hard
  row is added or changed.
- Any change to `PlanningRule` (`/planning-rules`). Its category-level
  rule types (`competence_required`, `business_line_preference`, and so
  on) form a separate, unrelated mechanism.
- Competence-to-workcenter gating, still deferred per
  `doc/features/workcenters/spec.md`.
- A description field, a ranking among multiple soft workcenters, or any
  change to how the picker sorts or orders the eligible-employee list
  beyond the existing not-preferred-style icon.
