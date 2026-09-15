# Planning Rules — Spec

Roadmap phase 3's last open item: "Rule-based automatic planning still
needs a design session" (`doc/roadmap.md:15`). This feature closes that
gap with data and a settings UI only. It does not enforce anything on
`/planning` and does not build the OR-Tools `/solve` service — that
stays phase 5, which also needs phase 4's fairness design first.

## Problem

A manager has no way to record the constraints a schedule should follow
— a weekly hours cap, a rest rule, which competence a workcenter needs,
which business line it prefers to draw from. Competences exist but have
no planning use (`doc/features/competences/spec.md`). None of this is
captured anywhere, so phase 5's `/solve` service would have nothing to
read once it exists.

## Solution

### Rule types (v1)

Every rule carries a **mode** (`hard` or `soft`) and, when soft, a
**severity** score from 1 (minor) to 10 (critical) — a unitless
relative weight. Phase 5 maps severity to its own objective-function
coefficients; this feature only stores it.

Three global, singleton rules, extending the existing `planning_settings`
table (`PlanningSettings` model, already a current-settings singleton):

1. **Max hours per week** — capped at the employee's own `weekly_hours`
   field, no overage allowed. Evaluated as an average over a 2-week
   period, weeks paired to fixed calendar boundaries (ISO week 1-2,
   3-4, ...), not a rolling window. No extra config value: the cap
   comes from each employee's existing field.
2. **Max shifts per day** — one global integer value (e.g. `1`),
   applying to every employee.
3. **Not-preferred-shift penalty** — applies when an assignment lands
   on a weekday+shift cell the employee marked `not_preferred` on the
   recurring availability grid (`employee-availability`). Reuses that
   existing data; adds no new table.

Two scoped, multi-instance rule types, each its own table:

4. **Competence required for a workcenter+shift pairing.** A manager
   picks a workcenter, a shift, and a competence; assigning that
   pairing is expected to require the competence.
5. **Business-line preference for a workcenter.** A manager picks a
   workcenter and one or more business lines; filling that workcenter
   is expected to prefer employees from one of the listed lines.

### Data

- `planning_settings` — add columns:
  - `max_hours_per_week_mode` (`hard`/`soft`, default `hard`),
    `max_hours_per_week_severity` (nullable, 1-10, required when soft).
  - `max_shifts_per_day` (unsigned integer, default `1`),
    `max_shifts_per_day_mode`, `max_shifts_per_day_severity` (same
    shape as above).
  - `not_preferred_shift_mode` (default `soft`),
    `not_preferred_shift_severity`.
- `workcenter_shift_competence_requirements` — one row per
  (workcenter, shift, competence): `workcenter_id`, `shift_id`,
  `competence_id`, `mode`, `severity`. Unique on the three FKs.
- `workcenter_business_line_preferences` — one row per (workcenter,
  business line): `workcenter_id`, `business_line_id`, `mode`,
  `severity`. Unique on the two FKs. `mode`/`severity` are set once per
  workcenter in the UI and written identically to every row under that
  workcenter — see Key decisions.

### Settings tab — Planning Rules

A new tab on `/settings`, admin-only, alongside Competences, Business
Lines, Shifts, Workcenters. Same explicit-save model as the rest of
Settings (`settings-explicit-save/spec.md`): local edit state, one
Save/Cancel pair, nothing written until Save.

Layout:

- **Global rules** — three rows, each a hard/soft toggle; switching to
  soft reveals a severity input (1-10). Max shifts/day also has its
  integer value field. Max hours/week and not-preferred-shift need no
  extra value — they read from existing employee/availability data.
- **Competence requirements** — an `OrderedNameList`-style add/remove
  table: workcenter `SelectInput`, shift `SelectInput`, competence
  `SelectInput`, hard/soft toggle, severity input when soft. No
  reordering — this is a set, not a sequence.
- **Business-line preferences** — one row per workcenter that has a
  preference rule: workcenter `SelectInput`, a multi-select of business
  lines, hard/soft toggle, severity input when soft.

### Controller

A new `PlanningRuleController` (or folded into the existing
`SettingsController`/`PeriodController` pattern, whichever the plan
step finds cleaner):

- Update the three global fields on `planning_settings` in one request,
  same shape as `PeriodController::update`.
- CRUD for `workcenter_shift_competence_requirements`: store, update
  (mode/severity only — the three FKs are fixed once a row exists, same
  rule as `workcenter-shift-assignments`), destroy.
- CRUD for `workcenter_business_line_preferences`: store/update writes
  the whole set of business-line rows for one workcenter in a single
  request (delete-and-reinsert under that workcenter_id), destroy
  removes a workcenter's whole preference set.

## Key decisions

- **Data and UI only, no enforcement.** `/planning`'s existing
  server-side checks (capacity, holidays, availability, overlap) are
  untouched. A manager can still assign an employee that breaks a rule
  defined here — these rules exist so phase 5 has something to read,
  not to gate today's manual planning.
- **Hard/soft is a per-rule-instance setting, not fixed per rule type.**
  Every rule, global or scoped, carries the same `mode` +
  `severity`-when-soft shape. One consistent mechanism instead of five
  bespoke ones.
- **Severity is a unitless 1-10 score**, not a raw solver weight.
  Phase 5 decides how to turn it into an objective-function coefficient;
  this feature does not guess that shape.
- **Max hours/week has no separate config value.** It reads the
  employee's own `weekly_hours` field (already used elsewhere,
  `employee-hours`), so a manager edits one number instead of two that
  could drift apart.
- **2-week averaging uses fixed calendar pairs, not a rolling window.**
  Simpler to compute and to explain to a manager than a sliding
  2-week span.
- **Business-line preference duplicates mode/severity across a
  workcenter's rows** rather than adding a second table to hold them
  once. The set is small (a handful of business lines per workcenter)
  and always written together from one form, so the duplication never
  drifts.
- **Fixed set of typed rule tables, not one generic rule engine.**
  Matches how `workcenter_shift_capacities` and similar features are
  already modeled — a real column per concept, not a JSON blob a form
  has to interpret. Adding a sixth rule type later is a migration, the
  same cost as any other new concept in this codebase.
- **One Settings tab for everything**, not splitting the workcenter-
  scoped rules onto `/schedule`. Keeps every planning
  constraint discoverable in one place; `/schedule` stays
  about capacity, not rules.

## Non-goals

- Enforcing any rule on `/planning` (blocking or warning on an
  assignment that breaks one).
- The OR-Tools `/solve` service, the `GeneratePlan` job, or any JSON
  contract — phase 5, and it also needs phase 4's fairness design
  first.
- Fairness (workload fairness, wish fairness) — a separate, still-open
  phase 4 design session.
- A rolling 2-week window, or a configurable overage percentage above
  `weekly_hours`.
- Per-business-line or per-employee overrides of max hours/week or max
  shifts/day — both stay single global values (max hours/week already
  varies per employee through `weekly_hours`; max shifts/day does not
  vary at all in v1).
- Min-rest-between-shifts as a distinct rule — superseded by max
  shifts/day for v1.
- Max consecutive working days — not requested for v1.
