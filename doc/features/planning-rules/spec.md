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

Three global, singleton rules:

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

Two scoped, multi-instance rule types:

4. **Competence required for a workcenter+shift pairing.** A manager
   picks a workcenter, a shift, and a competence; assigning that
   pairing is expected to require the competence.
5. **Business-line preference for a workcenter.** A manager picks a
   workcenter and one or more business lines; filling that workcenter
   is expected to prefer employees from one of the listed lines.

Adding a sixth rule type later is application code — a new entry in
the type list, its validation, and its slice of the settings UI — not
a migration. See Key decisions.

### Data

One table, `planning_rules`, holds every rule of every type:

- `type` — one of the five type strings above.
- `mode` — `hard` or `soft`.
- `severity` — nullable, 1-10, required exactly when `mode` is `soft`.
- `config` — a nullable JSON column holding whatever the type needs:

  | Type | `config` |
  | --- | --- |
  | `max_hours_per_week` | `{}` — reads the employee's own `weekly_hours` field, no config |
  | `max_shifts_per_day` | `{"value": 2}` |
  | `not_preferred_shift` | `{}` — reads the existing availability grid |
  | `competence_required` | `{"workcenter_id": 1, "shift_id": 2, "competence_id": 3}` |
  | `business_line_preference` | `{"workcenter_id": 1, "business_line_ids": [1, 2]}` |

`max_hours_per_week`, `max_shifts_per_day`, and `not_preferred_shift`
are **singleton types** — the application rejects a second row of the
same type. `competence_required` and `business_line_preference` are
**scoped types** — many rows allowed, but a duplicate scope is
rejected (the same workcenter+shift+competence triple, or a second
preference rule for the same workcenter).

A row's `type`, and for a scoped type what it targets (the
workcenter/shift/competence combination, or which workcenter a
business-line preference is for), is fixed once created — the
application ignores client-supplied identity fields on update. Only
`mode`, `severity`, and a type's own mutable data (`max_shifts_per_day`'s
`value`, `business_line_preference`'s `business_line_ids`) change in
place; anything else means deleting the row and adding a new one.

### Page — `/planning-rules`

A new sidebar item, **Planning rules**, admin-only, next to Schedule
and Planning in `AppLayout.vue`. Route behind the existing `admin`
middleware group — its own page, not a Settings tab, since it is a
frequent, standalone task rather than one-off configuration (the same
reasoning `workcenter-shift-assignments/spec.md` gave for
`/schedule`'s own page).

One list of rule cards, regardless of type, each showing its type, its
target (for a scoped type), its mutable field, and a hard/soft toggle
with a conditional severity field. An add section below picks a type
first — a singleton type already present is not offered — then
reveals only the fields that type needs, mirroring the row layout
above. Same explicit-save model as the rest of the app
(`settings-explicit-save/spec.md`): local edit-until-Save state, one
Save/Cancel pair, nothing written until Save.

### Controller

`PlanningRuleController`, generic across all five types:

- `store(Request)` — validates `type`, `mode`, `severity`, and
  whichever type-specific fields `required_if:type,...` pulls in
  (`value`; `workcenter_id`/`shift_id`/`competence_id`;
  `workcenter_id`/`business_line_ids`). Rejects a duplicate singleton
  type or a duplicate scope, builds the `config` JSON for the given
  type, creates the row.
- `update(Request, PlanningRule)` — re-validates the same shape, but
  overwrites any client-supplied identity field with what is already
  stored before validating, so identity cannot change from the
  client.
- `destroy(PlanningRule)`.

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
- **One `planning_rules` table for every type, not a table per type.**
  Reversed from this spec's first draft, which modeled each type as
  its own table (or its own columns on `planning_settings`). A
  manager adds "a rule," picks its type, and the type determines what
  else the form asks for — the UI and the data model should say the
  same thing. A single table also means the Settings tab reads and
  writes one list instead of a form plus several separate lists.
- **Type-specific data lives in one JSON `config` column**, not a
  shared set of nullable typed columns. Adding a sixth rule type is
  then pure application code — a new type string, its validation, and
  its slice of the settings UI — never a migration. The tradeoff: the
  database cannot enforce a foreign key or a column type inside
  `config`, so referential integrity (a competence or workcenter that
  still exists) is the controller's job via `exists:` validation
  rules, not the schema's.
- **Most types are capped at one row; the two scoped types allow
  many.** `max_hours_per_week`, `max_shifts_per_day`, and
  `not_preferred_shift` have no scope to tell two instances apart, so
  a second one is meaningless. Nothing in the schema can express "one
  row per type, but only for some types" against an opaque JSON
  `config`, so this guard — and the scoped types' duplicate-scope
  guard — lives entirely in the controller, not a database
  constraint.
- **A rule's identity is fixed once created; its data can still
  change.** Matches `workcenter-shift-assignments`' rule for its own
  pivot: the workcenter/shift pair cannot change in place, but spot
  counts can. Here, `type` and a scoped rule's target never change in
  place; `mode`, `severity`, and a type's own mutable field
  (`value`, `business_line_ids`) do.
- **A dedicated `/planning-rules` page, not a Settings tab.** Reversed
  from this spec's first draft. Managing rules is a frequent task, not
  one-off configuration, and every rule (global or scoped) belongs in
  one list rather than split across a Settings tab and
  `/schedule`; `/schedule` stays about capacity, not rules.

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
