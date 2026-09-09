# Employee Change Lock — Spec

Roadmap phase 3.8, alongside `features/mailbox/`. One global switch that
turns the employee personal page read-only.

## Problem

Every editable area on the personal page has a manager controller and a
mirror `Personal*Controller` that only checks the token. An employee can
change weekly hours, the availability grid, question answers, holidays,
and competences at any time. A manager needs to freeze that — during
planning, or before employees are meant to touch anything — while still
letting employees see their own data and the shift note.

## Solution

### Storage

`planning_settings` gains a boolean `allow_employee_changes`, default
`true` (today's behaviour). `PlanningSettings`: `$fillable` and `casts()`
gain the field.

### Settings — the renamed General tab

The Period tab is renamed **General** — it already holds app-wide
singleton settings (FTE hours, period start, period end) and now also
this switch.

- `Settings/Index.vue`: tab `value` becomes `general`, label
  `__('settings.tab.general')`, panel `data-testid="panel-general"`.
- `PeriodSettingsForm.vue` gains a `CheckboxInput` for
  `allow_employee_changes`, below the period dates, with a short hint
  ("When off, personal pages are read-only.").
- The `PUT /settings/period` route and `PeriodController@update` keep
  their path and name (no user-facing URL) and gain
  `allow_employee_changes` → `boolean` in the validated set, written to
  `PlanningSettings::current()`.
- `SettingsController@index` adds `allowEmployeeChanges` to the `period`
  payload (or a sibling prop).

### Enforcement — server

A single guard, `App\Support\EmployeeChanges::locked(): bool`
(`! PlanningSettings::current()->allow_employee_changes`), or a
route-middleware `employee.changes` on the write routes.

The five personal write controllers abort `403` when locked, before
touching data:

- `PersonalPageController@update`
- `PersonalRecurringAvailabilityController@update`
- `PersonalQuestionController@update`
- `PersonalHolidayController@store`, `@destroy`
- `PersonalCompetenceController@update`, `@destroy`

`PersonalPageController@show` is **not** guarded — the page still renders.
Every `/employees/{employee}/…` manager route is untouched.

Cleanest form: a `employee.changes` middleware alias applied to the
`/personal/{token}/…` write routes (not `personal.show`), registered in
`bootstrap/app.php` next to `admin`.

### Enforcement — personal page UI

`PersonalPageController@show` payload gains `editable` (the negation of
locked). `Personal/Show.vue`:

- Passes `:disabled="!editable"` (new prop) into `AvailabilityGrid`,
  `TagChecklist`, `QuestionChecklist`, `HolidayList`, and disables the
  weekly-hours form's `SelectInput` and submit.
- Shows a short banner above the tabs when `!editable`
  (`personal.locked_notice`).

Those four components today have no `disabled` prop (only `HolidayList`
has an in-flight `disabled`). Each gains one that greys the controls and
blocks the emit / request. This is the bulk of the front-end work.

### i18n

`settings.tab.general` (replaces `settings.tab.period` as the tab label;
the `period.*` field keys stay). `general.allow_employee_changes`,
`general.allow_employee_changes_hint`. `personal.locked_notice`.

## Key decisions

- **One flag, all five areas.** The manager wants "employees can / cannot
  change their stuff", not a matrix. A split (competences always open,
  say) needs a rationale the product does not have yet.
- **Read-only, not blocked.** An employee keeps access to their own data
  and the shift note when changes are closed; only writing stops. A
  closed link would remove read access for no gain.
- **Default on.** Matches today. A planner who wants changes closed until
  invites go out flips one switch.
- **On the General tab, not a new tab.** One boolean does not earn a tab.
  The Period tab already carries global singleton settings; renaming it
  keeps future global flags in one place.
- **Middleware on the write routes.** The show route is deliberately
  excluded, so the guard cannot accidentally hide the page. Manager
  routes are in a different group and never see it.
- **Server first, UI second.** The `403` is the real control; disabled
  inputs are courtesy. Both ship together.

## Non-goals

- Per-field or per-area locks.
- A per-employee or per-Business-Line override.
- Locking the manager editor.
- A schedule (auto-close on a date) — the manager toggles it by hand.
- Any change to token security or the `personal.show` route.
- Audit or history of when the flag changed.
