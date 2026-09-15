# Planning Rules — Plan

Status: in progress — 2/3

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
  `DELETE /settings/planning-rules/{planningRule}` (superseded by step
  2's move to `/planning-rules`). `SettingsController::index()` passed
  `planningRules`, and a Settings tab (`settings.tab.planning_rules`)
  hosted `PlanningRuleList.vue` — both since replaced. `PlanningRuleList.vue`
  is a `BusinessLineList`-style controlled child (seeds local rows from
  `items`, emits `update:items`, no network calls of its own): one card
  per rule (type, target summary, its mutable field, mode/severity), an
  add section that reveals only the fields the chosen type needs and
  hides singleton types already present, business-line selection via
  the existing `TagChecklist`. `en.json`: `planning_rules.*` keys for
  all five type labels, field labels, placeholders, and error messages.
  Feature test `PlanningRuleTest` (16 tests at this point: guest/manager
  blocked; index payload; create each singleton type; create
  competence_required and business_line_preference with their config;
  reject a duplicate singleton, a duplicate competence triple, a
  duplicate business-line workcenter, soft-without-severity,
  hard-with-severity; update changes mode/severity; update ignores a
  client-supplied identity field; update changes a business-line
  preference's lines; destroy removes a row). Vitest
  `PlanningRuleList.test.js` (6 tests: renders rows and empty state;
  hides an already-present singleton type from the add form; adds a
  `max_shifts_per_day` and a `competence_required` rule locally and
  emits `update:items`; reveals severity only when soft; removes a row
  locally). Full PHP suite green (539 passed — one pre-existing,
  unrelated `EmployeeAdminTest` failure confirmed present on `main`
  before this feature). Full JS suite green (503 passed). `npm run
  build` green. Pint clean on all touched/new files.

- [x] 2. **Move Planning Rules off Settings onto its own
  `/planning-rules` page.** New sidebar item, **Planning rules**
  (`nav.planning_rules`, icon `adjustments-horizontal`), admin-only,
  next to Schedule and Planning in `AppLayout.vue`. Routes moved from
  `/settings/planning-rules` to `/planning-rules` (`GET`, `POST`,
  `PUT /{planningRule}`, `DELETE /{planningRule}`), route names
  `planning-rules.*`. `PlanningRuleController::index()` renders the new
  `PlanningRules.vue` page directly (`planningRules`,
  `workcenters`/`shifts`/`competences`/`businessLines` lookup lists) —
  `SettingsController` no longer touches planning rules at all.
  `PlanningRules.vue`: same page shape as `WorkcenterShifts.vue` (one
  `Card`, `PlanningRuleList` plus a page-level `TabSaveBar`), hosting
  the diff/save/cancel logic that `Settings/Index.vue` used to own.
  `Settings/Index.vue`: the `planning_rules` tab, its props, and all
  its diff logic removed; `settings.tab.planning_rules` key dropped
  from `en.json`, replaced by `nav.planning_rules` and
  `planning_rules.title`. `PlanningRuleList.vue` itself is unchanged —
  it was already a controlled child, so moving its host page did not
  touch it. Feature test additions: index guest/manager-blocked and
  payload tests now target `/planning-rules` (18 tests total). New
  Vitest `PlanningRules.test.js` (6 tests: renders rows; Save/Cancel
  disabled with nothing changed; add-then-save POST; delete-then-save
  DELETE; Cancel reverts). `AppLayoutNav.test.js` updated for the new
  nav item. Full PHP suite green (541 passed — same one pre-existing
  failure). Full JS suite green (509 passed). `npm run build` green.
  Pint clean.

- [ ] 3. **Docs and full checks.** Once the data model is settled and
  the user has replaced the in-place migration with a fresh,
  correctly-named one: `doc/roadmap.md` — phase 3's status row and
  section note that rule-based planning's data model and its own
  `/planning-rules` page shipped (`features/planning-rules/`), still
  nothing enforces the rules and `/solve` remains phase 5.
  `doc/concept.md` updated if it lists phase-3 scope. Pint clean. Full
  PHP suite green. Full JS suite green. `npm run build` green.
  `php artisan migrate` clean on a fresh database.

## Not done / deferred

- Everything under the spec's non-goals: enforcement on `/planning`,
  the `/solve` service and `GeneratePlan` job, fairness, a rolling
  2-week window, a configurable overage percentage, per-business-line
  or per-employee overrides of the two global caps, min-rest-between-
  shifts, max-consecutive-working-days.
- Manual browser pass — AGENTS.md leaves UI verification to the user.
