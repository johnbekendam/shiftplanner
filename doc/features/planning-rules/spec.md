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

Most rules carry a **mode** (`hard` or `soft`). A soft rule also carries
a **severity** score from 1 (minor) to 10 (critical). The severity is a
unitless relative weight. Phase 5 maps severity to objective-function
coefficients. This feature only stores it.

Three global, singleton rules:

1. **Max hours per week** — capped at the employee's own `weekly_hours`
  field, with no overage. The system evaluates the cap as an average
  over a 2-week planning cycle. The cycle starts on the Monday of the
  week that contains `PlanningSettings.period_start`. The cap has no
  extra config value.
2. **Max shifts per day** — one global integer value (e.g. `1`),
   applying to every employee.
3. **Not-preferred-shift rule** — applies when an assignment lands
   on a weekday+shift cell the employee marked `not_preferred` on the
   recurring availability grid (`employee-availability`). Reuses that
   existing data; adds no new table. When the rule is soft, the
   assignment costs the rule's severity. When the rule is hard, the
   planner treats a `not_preferred` cell like an `unavailable` cell and
   never assigns it.
4. **Equal workload** — a presence-only singleton rule. It has no mode,
   severity, or config. In phase 5, it minimizes the highest absolute
   assigned-hour total after the solver maximizes shift coverage.

Three scoped, multi-instance rule types:

5. **Competence required for a workcenter.** A manager picks a
  workcenter and a competence; assigning that workcenter is expected to
  require the competence regardless of shift.
6. **Business-line preference for a workcenter.** A manager picks a
  workcenter and one business line; filling that workcenter is expected
  to prefer employees from that line.
7. **Alternating shift pair.** A manager picks two distinct shifts. The
   rule is always hard and applies across workcenters. An employee never
   holds both shifts in the same Monday–Sunday week
   (`alternating-shift-weekly-exclusion`). A shift can belong to only
   one pair. The unordered pair is fixed after
   creation. To change either shift, delete the rule and create a new one.

Adding another rule type later is application code — a new entry in
the type list, its validation, and its slice of the settings UI — not
a migration. See Key decisions.

### Future solver behavior

The solver uses this objective order:

1. Apply hard eligibility and assignment constraints.
2. Maximize filled shift capacity.
3. Apply the equal-workload rule when it exists.
4. Optimize the soft preferences.

The equal-workload rule compares absolute assigned hours. It does not
compare assigned hours as a percentage of `weekly_hours`. The
`weekly_hours` value is the maximum time that an employee offers for
factory work. It is not a target or a minimum.

The fairness pool contains confirmed employees with `weekly_hours > 0`
who can fill at least one required spot in the 2-week cycle. Eligibility
includes holidays, recurring availability, workcenter restrictions, and
hard competence rules. All assignments in the final draft count toward
the assigned-hour total, including retained manual and fixed assignments.

Each generation run covers one complete 2-week cycle. Cycles are
non-overlapping and start from the week that contains `period_start`.
For example, a start date in ISO week 2 produces cycles for weeks 2-3,
4-5, and so on. A later change to `period_start` changes future cycle
boundaries. It does not change existing assignments.

An alternating shift pair is a hard eligibility rule. The planner never
gives an employee one pair shift in a week in which the employee holds
the other. Manual planning applies the same check.

### Data

One table, `planning_rules`, holds every rule of every type:

- `type` — one of the seven type strings above.
- `mode` — nullable, otherwise `hard` or `soft`.
- `severity` — nullable, 1-10, required for a soft rule.
- `config` — a nullable JSON column holding whatever the type needs:

  | Type | `config` |
  | --- | --- |
  | `max_hours_per_week` | `{}` — reads the employee's own `weekly_hours` field, no config |
  | `max_shifts_per_day` | `{"value": 2}` |
  | `not_preferred_shift` | `{}` — reads the existing availability grid |
  | `equal_workload` | `{}` |
  | `competence_required` | `{"workcenter_id": 1, "competence_id": 3}` |
  | `business_line_preference` | `{"workcenter_id": 1, "business_line_id": 1}` |
  | `alternating_shift_pair` | `{"first_shift_id": 1, "second_shift_id": 2}` |

`max_hours_per_week`, `max_shifts_per_day`, `not_preferred_shift`, and
`equal_workload` are **singleton types**. The application rejects a
second row of the same type. The other types are **scoped types**.
Many scoped rows are allowed, but the application rejects duplicate
scopes. For alternating pairs, it rejects any pair that uses an already
paired shift, including a reversed duplicate.

A row's `type`, and for a scoped type what it targets (the
workcenter/competence combination, or which workcenter a
business-line preference is for), is fixed once created — the
application ignores client-supplied identity fields on update. Only
`mode`, `severity`, and a type's own mutable data (`max_shifts_per_day`'s
`value`, `business_line_preference`'s `business_line_id`) change in
place. An alternating pair permits no change. Anything
else means deleting the row and adding a new one.

The `mode` and `severity` columns are null for `equal_workload`. The
`mode` is `hard` for `alternating_shift_pair`, and its severity is
null. Other rule types keep the standard hard/soft behavior.

### Page — `/planning-rules`

A new sidebar item, **Planning rules**, admin-only, next to Schedule
and Planning in `AppLayout.vue`. Route behind the existing `admin`
middleware group — its own page, not a Settings tab, since it is a
frequent, standalone task rather than one-off configuration (the same
reasoning `workcenter-shift-assignments/spec.md` gave for
`/schedule`'s own page).

One table of rules, regardless of type, each showing its type, its
target (for a scoped type), its mutable field, and applicable controls.
The equal-workload row has no controls. An alternating-pair row shows
both shift names, `Hard`, and a hint. It has no controls except delete. An add section below picks a type
first — a singleton type already present is not offered — then
reveals only the fields that type needs, mirroring the row layout
above. Same explicit-save model as the rest of the app
(`settings-explicit-save/spec.md`): local edit-until-Save state, one
Save/Cancel pair, nothing written until Save.

### Controller

`PlanningRuleController`, generic across all seven types:

- `store(Request)` — validates `type`, `mode`, `severity`, and
  whichever type-specific fields `required_if:type,...` pulls in
  (`value`; `workcenter_id`/`competence_id`;
  `workcenter_id`/`business_line_id`). Rejects a duplicate singleton
  type or a duplicate scope, builds the `config` JSON for the given
  type, creates the row. Alternating-pair validation requires two
  different existing shifts and rejects overlap with another pair.
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
- **Hard/soft applies to the original five rule types.** Equal workload
  is presence-only because it has a fixed objective tier. Alternating
  pairs are always hard: two pair shifts in one week is never allowed
  (`alternating-shift-weekly-exclusion`).
- **Severity is a unitless 1-10 score**, not a raw solver weight.
  Phase 5 decides how to turn it into an objective-function coefficient;
  this feature does not guess that shape.
- **Max hours/week has no separate config value.** It reads the
  employee's own `weekly_hours` field (already used elsewhere,
  `employee-hours`), so a manager edits one number instead of two that
  could drift apart.
- **One setting anchors each 2-week planning cycle.** The cycle starts
  on the Monday of the week that contains `period_start`. Generation,
  fairness, and max-hours averaging use the same boundaries.
- **Equal workload means equal absolute hours.** Factory work is an
  unwanted shared duty. The solver minimizes the highest workload after
  it fills as many spots as possible. The employee's `weekly_hours`
  remains an offered maximum, not a proportional fairness weight.
- **Alternation uses explicit shift pairs.** Names and clock times do not
  identify shift meaning. Explicit pairs are stable after shift renames,
  and unpaired day shifts remain outside the rule.
- **One `planning_rules` table for every type, not a table per type.**
  Reversed from this spec's first draft, which modeled each type as
  its own table (or its own columns on `planning_settings`). A
  manager adds "a rule," picks its type, and the type determines what
  else the form asks for — the UI and the data model should say the
  same thing. A single table also means the Settings tab reads and
  writes one list instead of a form plus several separate lists.
- **Type-specific data lives in one JSON `config` column**, not a
  shared set of nullable typed columns. Adding another rule type is
  then pure application code — a new type string, its validation, and
  its slice of the settings UI — never a migration. The tradeoff: the
  database cannot enforce a foreign key or a column type inside
  `config`, so referential integrity (a competence or workcenter that
  still exists) is the controller's job via `exists:` validation
  rules, not the schema's.
- **Most types are capped at one row; the three scoped types allow
  many.** `max_hours_per_week`, `max_shifts_per_day`, and
  `not_preferred_shift` have no scope to tell two instances apart, so
  a second one is meaningless. Nothing in the schema can express "one
  row per type, but only for some types" against an opaque JSON
  `config`, so this guard — and the scoped types' duplicate-scope
  guard — lives entirely in the controller, not a database
  constraint.
- **Shift deletion removes its alternating-pair rule.** Shift IDs live in
  JSON, so the database cannot cascade this reference. The application
  deletes the pair rule and shift in one transaction.
- **A rule's identity is fixed once created; its data can still
  change.** Matches `workcenter-shift-assignments`' rule for its own
  pivot: the workcenter target cannot change in place, but spot counts
  can. Here, `type` and a scoped rule's target never change in
  place; `mode`, `severity`, and a type's own mutable field
  (`value`, `business_line_id`) do.
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
- Wish fairness. Workload fairness is defined here, but phase 5 implements
  and enforces it in the solver.
- A rolling 2-week window, or a configurable overage percentage above
  `weekly_hours`.
- A minimum or target number of assigned hours. `weekly_hours` is only
  the maximum time that an employee offers.
- Per-business-line or per-employee overrides of max hours/week or max
  shifts/day — both stay single global values (max hours/week already
  varies per employee through `weekly_hours`; max shifts/day does not
  vary at all in v1).
- Min-rest-between-shifts as a distinct rule — superseded by max
  shifts/day for v1.
- Max consecutive working days — not requested for v1.
