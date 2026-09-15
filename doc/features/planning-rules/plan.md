# Planning Rules — Plan

Status: in progress — 1/4

Spec: `spec.md`. Four slices: global rule fields on `planning_settings`,
competence-requirement rows, business-line-preference rows, then docs
and full checks. Each slice ships its own migration, model/controller
change, and Settings tab section.

- [x] 1. **Backend + UI: global rules on `planning_settings`.**
  Migration adds `max_hours_per_week_mode` (string, default `hard`),
  `max_hours_per_week_severity` (nullable unsigned tinyint),
  `max_shifts_per_day` (unsigned integer, default `1`),
  `max_shifts_per_day_mode` (default `hard`),
  `max_shifts_per_day_severity` (nullable), `not_preferred_shift_mode`
  (default `soft`), `not_preferred_shift_severity` (nullable) to
  `planning_settings`. `PlanningSettings`: added the seven fields to
  `$fillable` and casts, a new `rulesPayload()` alongside `toPayload()`
  (kept separate so `period` and `planningRules` stay independent
  Inertia props), extended `current()`'s defaults. New
  `PlanningRuleController::update(Request)` validates the three modes
  (`in:hard,soft`), the three severities (`required_if`/
  `prohibited_unless` paired with each mode field, `between:1,10`), and
  `max_shifts_per_day` (`integer|min:1`). Updates the singleton row,
  redirects back with a flash. Route: `PUT /settings/planning-rules`,
  admin-only, alongside the other `/settings/*` routes.
  `SettingsController::index()` passes `planningRules`. New Settings
  tab (`settings.tab.planning_rules`) added to `Settings/Index.vue`
  next to Competences, driven by a new `PlanningRulesForm.vue`
  (mirrors `PeriodSettingsForm.vue`'s exposed `isDirty`/`processing`/
  `submit`/`cancel` surface): a hard/soft `SelectInput` per rule, a
  conditional severity `NumberInput` (1-10) shown only when soft, and
  `max_shifts_per_day`'s own `NumberInput`. Same explicit-save
  `TabSaveBar` pattern as `Period`. `en.json`: `planning_rules.*` and
  `settings.tab.planning_rules` keys. Feature test
  `PlanningRuleGlobalTest` (9 tests: guest/manager blocked; index
  payload defaults; update persists all seven fields; soft without a
  severity rejected; hard with a severity rejected; severity out of
  1-10 range rejected; `max_shifts_per_day` below 1 rejected; an
  invalid mode rejected). Vitest `PlanningRulesForm.test.js` (5 tests:
  seeds from props; defaults hard/hard/soft; toggling to soft reveals
  the severity field; submit nulls severity for hard rules; isDirty/
  cancel). Full PHP suite green (533 passed — one pre-existing,
  unrelated failure in `EmployeeAdminTest` confirmed present on `main`
  before this change). Full JS suite green (502 passed). `npm run
  build` green. Pint clean on all touched/new files.

- [ ] 2. **Backend + UI: competence requirements.** Migration creates
  `workcenter_shift_competence_requirements` (`id`, `workcenter_id` FK,
  `shift_id` FK, `competence_id` FK, `mode`, `severity` nullable,
  timestamps; unique on the three FKs). Model
  `WorkcenterShiftCompetenceRequirement` with the three `belongsTo`
  relations. `PlanningRuleController` (or a new
  `CompetenceRequirementController`, matching whichever the previous
  step chose): `store` validates the three FKs (`exists`, pair not
  already present — `ValidationException`, same guard style as
  `WorkcenterShiftAssignmentController`), `mode`, `severity` (same
  soft/hard rule as step 1). `update($requirement)` — mode/severity
  only, the three FKs stay fixed once a row exists. `destroy($requirement)`.
  Routes: `POST /settings/planning-rules/competence-requirements`,
  `PUT .../{competenceRequirement}`, `DELETE .../{competenceRequirement}`.
  Settings tab section: an add/remove list — workcenter `SelectInput`,
  shift `SelectInput`, competence `SelectInput`, hard/soft toggle,
  conditional severity field — same explicit-save diff shape as
  `WorkcenterShifts.vue`'s row list. `en.json` keys for the section.
  Feature test `CompetenceRequirementTest` (guest/manager blocked;
  store creates a row; store rejects a duplicate triple, a missing FK,
  a bad mode/severity combination; update changes mode/severity only;
  destroy removes the row). Vitest for the section (add row, remove
  row, save diff, soft reveals severity). Full suites and build green.

- [ ] 3. **Backend + UI: business-line preferences.** Migration creates
  `workcenter_business_line_preferences` (`id`, `workcenter_id` FK,
  `business_line_id` FK, `mode`, `severity` nullable, timestamps;
  unique on the two FKs). Model `WorkcenterBusinessLinePreference` with
  `belongsTo` relations. Controller action(s) treat one workcenter's
  preference set as a unit: `store`/`update`
  (`replaceForWorkcenter($workcenterId, Request)`) validates
  `workcenter_id`, `business_line_ids` (array, each `exists`, at least
  one), `mode`, `severity` (same rule as step 1), deletes existing rows
  for that workcenter and inserts the new set in one transaction.
  `destroy($workcenterId)` removes the whole set. Routes:
  `PUT /settings/planning-rules/business-line-preferences/{workcenter}`,
  `DELETE .../{workcenter}`. Settings tab section: one row per
  workcenter with a preference set — workcenter `SelectInput` (only
  workcenters without an existing set, in the add row), a business-line
  multi-select, hard/soft toggle, conditional severity field. Save
  diffs by workcenter (whole-set PUT per changed/added workcenter,
  DELETE per removed one) — not per business line. `en.json` keys.
  Feature test `BusinessLinePreferenceTest` (guest/manager blocked;
  replace creates rows for a new workcenter; replace swaps a workcenter's
  set atomically; rejects an empty business-line list, a missing FK, a
  bad mode/severity combination; destroy removes a workcenter's set).
  Vitest for the section (add a workcenter's preference set, edit its
  business lines, remove it, save diff). Full suites and build green.

- [ ] 4. **Docs and full checks.** `doc/roadmap.md`: phase 3's status
  row and Phase 3 section note that rule-based planning's data model
  and settings UI shipped (`features/planning-rules/`), still nothing
  enforces the rules and `/solve` remains phase 5. `doc/concept.md`
  updated if it lists phase-3 scope. `AppLayout.vue`/nav unaffected —
  this lives on the existing `/settings` route. Pint clean. Full PHP
  suite green. Full JS suite green. `npm run build` green.
  `php artisan migrate` clean on a fresh database.

## Not done / deferred

- Everything under the spec's non-goals: enforcement on `/scheduling`,
  the `/solve` service and `GeneratePlan` job, fairness, a rolling
  2-week window, a configurable overage percentage, per-business-line
  or per-employee overrides of the two global caps, min-rest-between-
  shifts, max-consecutive-working-days.
- Manual browser pass — AGENTS.md leaves UI verification to the user.
