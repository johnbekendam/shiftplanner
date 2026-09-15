# Planning Rules — Plan

Status: in progress — 1/2

Spec: `spec.md`. One generic `planning_rules` table (`type` + `mode` +
`severity` + JSON `config`) replaces the original per-type-table design
after a mid-build redesign — see the note below before touching the
migration.

**Migration note:** the schema is still being iterated on. Per
instruction, edit the existing migration
(`2026_09_15_150000_add_global_planning_rules_to_planning_settings.php`)
in place rather than adding new migration files, until this feature's
data model is settled. A fresh, correctly-named migration replaces it
once the design is final.

- [x] 1. **Backend + UI: `planning_rules` table and full CRUD for all
  five types.** Migration (rewritten in place) creates `planning_rules`
  (`id`, `type` string, `mode` string default `hard`, `severity`
  nullable unsigned tinyint, `config` nullable json, timestamps) —
  replaces the earlier `planning_settings` column approach entirely;
  `PlanningSettings` reverted to its pre-feature state. New
  `PlanningRule` model (`SINGLETON_TYPES`, `SCOPED_TYPES`, `TYPES`
  constants; `config` cast to array; `toPayload()`). Rewritten
  `PlanningRuleController`: `store` validates `type`/`mode`/`severity`
  plus whichever fields `required_if:type,...` pulls in for the five
  types, rejects a duplicate singleton type or duplicate scope
  (competence triple, or a second business-line preference for the
  same workcenter), builds `config` per type, creates the row.
  `update` merges the existing row's identity fields over any
  client-supplied ones before validating, so `type` and a scoped
  rule's target cannot change from the client — only `mode`,
  `severity`, and a type's own mutable field (`value`,
  `business_line_ids`). `destroy` deletes. Routes:
  `POST /settings/planning-rules`,
  `PUT /settings/planning-rules/{planningRule}`,
  `DELETE /settings/planning-rules/{planningRule}`.
  `SettingsController::index()` passes `planningRules` as
  `PlanningRule::all()->map->toPayload()`, reusing the existing
  `workcenters`/`shifts`/`competences`/`businessLines` props as lookup
  lists. New Settings tab (`settings.tab.planning_rules`) driven by
  `PlanningRuleList.vue` — a `BusinessLineList`-style controlled child
  (seeds local rows from `items`, emits `update:items`, no network
  calls of its own): one card per rule (type, target summary, its
  mutable field, mode/severity), an add section that reveals only the
  fields the chosen type needs and hides singleton types already
  present, business-line selection via the existing `TagChecklist`.
  `Settings/Index.vue` owns the diff/save/cancel logic and
  `TabSaveBar`, matching the Business Lines tab's shape. `en.json`:
  `planning_rules.*` keys for all five type labels, field labels,
  placeholders, and error messages. Feature test `PlanningRuleTest`
  (16 tests: guest/manager blocked; index payload; create each
  singleton type; create competence_required and
  business_line_preference with their config; reject a duplicate
  singleton, a duplicate competence triple, a duplicate business-line
  workcenter, soft-without-severity, hard-with-severity; update
  changes mode/severity; update ignores a client-supplied identity
  field; update changes a business-line preference's lines; destroy
  removes a row). Vitest `PlanningRuleList.test.js` (6 tests: renders
  rows and empty state; hides an already-present singleton type from
  the add form; adds a `max_shifts_per_day` and a
  `competence_required` rule locally and emits `update:items`; reveals
  severity only when soft; removes a row locally). Full PHP suite
  green (539 passed — one pre-existing, unrelated `EmployeeAdminTest`
  failure confirmed present on `main` before this feature). Full JS
  suite green (503 passed). `npm run build` green. Pint clean on all
  touched/new files.

- [ ] 2. **Docs and full checks.** Once the data model is settled and
  the user has replaced the in-place migration with a fresh,
  correctly-named one: `doc/roadmap.md` — phase 3's status row and
  section note that rule-based planning's data model and settings UI
  shipped (`features/planning-rules/`), still nothing enforces the
  rules and `/solve` remains phase 5. `doc/concept.md` updated if it
  lists phase-3 scope. Pint clean. Full PHP suite green. Full JS suite
  green. `npm run build` green. `php artisan migrate` clean on a fresh
  database.

## Not done / deferred

- Everything under the spec's non-goals: enforcement on `/planning`,
  the `/solve` service and `GeneratePlan` job, fairness, a rolling
  2-week window, a configurable overage percentage, per-business-line
  or per-employee overrides of the two global caps, min-rest-between-
  shifts, max-consecutive-working-days.
- Manual browser pass — AGENTS.md leaves UI verification to the user.
