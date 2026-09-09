# Employee Change Lock — Plan

Status: done — 3/3

Spec: `spec.md`. Roadmap phase 3.8, alongside `features/mailbox/`. One
global `planning_settings.allow_employee_changes` flag that makes the
personal page read-only.

- [x] 1. **Flag + General tab (backend + settings UI).** Migration
  `..._000013` adds `allow_employee_changes` boolean default `true` to
  `planning_settings`; `PlanningSettings` `$fillable` + `casts()` +
  `toPayload()` + the `current()` seed default (so a freshly created row
  reports `true` without a re-read). `PeriodController@update` validates
  `allow_employee_changes` as `required|boolean`. `Settings/Index.vue`
  tab `period` → `general` (`settings.tab.general`, `panel-general`).
  `PeriodSettingsForm.vue` gains a `CheckboxInput` + hint bound to the
  new field. `en.json`: `settings.tab.period` → `settings.tab.general`;
  added `general.allow_employee_changes` / `_hint`.
  `tests/Feature/EmployeeChangeLockTest.php` (default on; guest/manager
  blocked; admin toggles off/on; must be boolean; index carries it);
  `PeriodSettingsTest` two cases updated to send the now-required field.
  `PeriodSettingsForm.test.js` / `SettingsIndex.test.js` updated. PHP
  270, JS 254, Pint clean.

- [x] 2. **Enforce on the personal write routes.**
  `App\Http\Middleware\EnsureEmployeeChangesAllowed` (`abort_unless`
  `allow_employee_changes`, 403), aliased `employee.changes` in
  `bootstrap/app.php`. `routes/web.php`: `personal.show` stays ungrouped;
  the seven write routes moved into a `Route::middleware('employee.changes')`
  group. `EmployeeChangeLockTest`: all seven writes 403 when off; a write
  redirects when on; `personal.show` stays 200 when off; the manager
  `PUT /employees/{id}` is unaffected. PHP 274 green, Pint clean.

- [x] 3. **Read-only personal page UI + full checks.**
  `PersonalPageController@show` payload gains `editable`
  (`allow_employee_changes`). `Personal/Show.vue`: a
  `personal.locked_notice` banner above the tabs when locked; threads
  `:disabled="!editable"` into `AvailabilityGrid`, `QuestionChecklist`,
  `TagChecklist`, `HolidayList` and a new `disabled` prop on
  `EmployeeFields` (hours + business-line selects); the Details save
  button is hidden when locked. Each of the four list components gained a
  `disabled` prop: grid cells get `:disabled` + guard, checklists pass
  `:disabled` to `CheckboxInput` + guard, `HolidayList` hides the add row
  and delete buttons + guards. `en.json` `personal.locked_notice`.
  Vitest: a `disabled`/lock case added to `AvailabilityGrid`,
  `TagChecklist`, `QuestionChecklist`, `HolidayList`, `PersonalShow`.
  Feature: `editable` in the show payload both ways. `doc/roadmap.md`
  (3.8 row → "Mostly done", phase 4 note), `doc/concept.md` Employees
  section. Migration applied on the dev DB. Pint clean, PHP 275, JS 260,
  build green.

## Not done / deferred

- Everything under the spec's Non-goals: per-field locks, per-employee
  override, locking the manager editor, a scheduled auto-close, audit
  history.
- Manual browser pass — AGENTS.md leaves UI verification to the user.
