# Business Lines — Plan

Status: in progress — 6/8

Spec: `spec.md`. Roadmap phase 3. Business Lines replace Departments.
The Settings CRUD mirrors `ProductGroupController`.

- [x] 1. **Backend: Business Line records and Settings CRUD.** Migrations:
  `business_lines` (`abbreviation` string unique, `description` string,
  `target_fte` decimal(5,1), `position` unsigned int, timestamps) and an
  `employees.business_line_id` nullable FK with `nullOnDelete`.
  `BusinessLine` model (`$fillable` the four fields, `target_fte` cast,
  order-by-position global scope, `employees()` hasMany, `toPayload()`).
  `Employee::businessLine()` belongsTo. `BusinessLineFactory`.
  `BusinessLineController` with `store` (abbreviation required, string,
  max 10, case-insensitive unique; description required, max 255;
  `target_fte` numeric, `>= 0`; `position` = max + 1), `update` (same
  rules, unique ignoring self), `destroy` (delete; the FK nulls each
  member), `move` (`direction` in `up|down`, neighbour swap, no-op past an
  end). `SettingsController@index` adds `businessLines` as
  `{ id, abbreviation, description, target_fte, position, employee_count }`
  in position order. Routes behind `admin`: `POST /settings/business-lines`,
  `PUT /settings/business-lines/{businessLine}/move`,
  `PUT /settings/business-lines/{businessLine}`,
  `DELETE /settings/business-lines/{businessLine}`. Feature test
  `BusinessLineConfigTest` mirroring `ProductGroupConfigTest` (guest and
  manager blocked; index payload and `employee_count`; create appends;
  blank, too-long, and duplicate-case abbreviation rejected; blank
  description rejected; negative `target_fte` rejected; update; delete
  nulls members, not deletes them; move up and down; move past an end is a
  no-op; bad direction rejected). Full PHP suite green.

- [x] 2. **Backend + frontend: the employee Business Line select.**
  `Employee::$fillable` gains `business_line_id`. `EmployeeController`
  `store` and `update` validate `business_line_id` as
  `nullable|integer|exists:business_lines,id`. `create` and `edit`
  payloads gain `businessLines` (`{ id, abbreviation }`, position order).
  `EmployeeFields.vue` gains a `SelectInput` after Weekly hours, options a
  blank "None" plus every Business Line by abbreviation, bound to
  `form.business_line_id`; it takes a `businessLines` prop.
  `Employees/Form.vue` passes the prop and seeds
  `form.business_line_id` from `employee?.business_line_id`. `en.json`:
  `employees.field.business_line` and `employees.field.business_line_none`.
  Feature test: `store` and `update` persist the id; an unknown id is
  rejected; `null` clears it; `create`/`edit` carry `businessLines`.
  Vitest `EmployeesForm.test.js`: the select renders its options and
  submits the chosen id; an existing employee's line is preselected.
  `npm run test` and `npm run build` green.

- [x] 3. **Frontend: the Business lines Settings tab.** New
  `resources/js/components/BusinessLineList.vue`: a row per Business Line
  with an abbreviation `TextInput`, a description `TextInput`, and a
  `NumberInput` for `target_fte`, each writing `PUT .../business-lines/{id}`
  with the whole row on blur or `Enter`; an up and a down button
  (`PUT .../move`); a delete button behind a confirm that names
  `employee_count`; an add row (`POST .../business-lines`) with the three
  fields; an empty state. `Settings/Index.vue` gains a `business_lines`
  tab after `product_groups`, rendering `BusinessLineList` with a
  `businessLines` prop. `en.json`: `settings.tab.business_lines` and the
  `business_lines.*` key set (abbreviation, description, target_fte, add,
  add labels, move_up, move_down, delete, delete_confirm with `:count`,
  list_empty, error.abbreviation_taken, flash.added/updated/deleted).
  Vitest: `BusinessLineList.test.js` (row edit writes the row; add writes;
  delete confirms with the count; move calls the endpoint) and
  `SettingsIndex.test.js` (the new tab mounts the list on its endpoint).
  `npm run test` and `npm run build` green.

- [x] 4. **Backend: planning settings.** Migration `planning_settings`
  (`fte_hours` unsigned small int default 40, `period_start` date
  nullable, `period_end` date nullable, timestamps). `PlanningSettings`
  model with `$fillable` the three fields, date casts, and a static
  `current()` that returns the single row, `firstOrCreate` on id 1 with
  defaults. `PeriodController@update` validates `fte_hours`
  (`integer`, `min:1`), `period_start` and `period_end`
  (`nullable|date`, end `after_or_equal:period_start`), saves the
  singleton, redirects back with a flash. Route behind `admin`:
  `PUT /settings/period`. `SettingsController@index` adds `period` as
  `{ fte_hours, period_start, period_end }`. Feature test
  `PeriodSettingsTest` (guest and manager blocked; index carries the
  defaults; update persists; `fte_hours` below 1 rejected; `period_end`
  before `period_start` rejected; blank dates accepted). Full PHP suite
  green.

- [x] 5. **Frontend: the Period Settings tab.** `Settings/Index.vue` gains
  a `period` tab after `business_lines`, one form: a `NumberInput` for
  `fte_hours`, a `DateInput` for `period_start`, a `DateInput` for
  `period_end`, and one `ButtonPrimary` that sends `PUT /settings/period`.
  It takes a `period` prop and seeds the form from it. `en.json`:
  `settings.tab.period` and `period.*` keys (fte_hours, fte_hours_hint,
  period_start, period_end, save, flash.saved, error.range). Vitest
  `SettingsIndex.test.js`: the tab renders the three fields seeded from
  the prop and submits them. `npm run test` and `npm run build` green.

- [x] 6. **Backend + frontend: the dashboard page and data.**
  `DashboardController@index` reads `PlanningSettings::current()`. With
  either date blank it returns `period: null`. Otherwise it builds `days`
  (every date from `period_start` to `period_end` inclusive) and, per day,
  each employee's available FTE: `0` on Saturday or Sunday; `0` if the day
  is inside any holiday range (inclusive); `0` if `weekly_hours` is `0`;
  else `weekly_hours / fte_hours`. Payload: `days`, `overall`
  (`{ available: number[], target }` — `target` = sum of all
  `target_fte`, `available` summed over every employee), and `lines` (per
  Business Line in position order:
  `{ abbreviation, description, available: number[], target }`). Route
  `GET /dashboard` behind `auth` (not `admin`), name `dashboard.index`.
  Change `Route::redirect('/', '/employees')` to `/dashboard`.
  `AppLayout.vue` nav gains a `Dashboard` item (`chart-bar` icon, mapped
  in `Icon.vue`) at the head of the list, for all users. `Dashboard/Index.vue`
  renders the "set a period on the Settings page" message when `period` is
  null, otherwise a plain list of the block titles and their numbers (no
  chart yet). `en.json`: `nav.dashboard`, `dashboard.title`,
  `dashboard.no_period`, `dashboard.overall`. Feature test
  `DashboardTest`: guest blocked; manager allowed; no period → `period`
  null; weekend day is `0`; a holiday day is `0`; `weekly_hours` `0` is
  `0`; a plain weekday is `weekly_hours / fte_hours`; `overall` counts an
  employee with no Business Line; `overall.target` is the sum of targets;
  one `lines` block per Business Line with its own target. Vitest
  `DashboardIndex.test.js`: the no-period message; the block titles with
  data. `/` redirect test updated. `npm run test` and `npm run build`
  green.

- [ ] 7. **Frontend: the FTE line chart.**
  `resources/js/components/FteLineChart.vue`: props `title`, `days`
  (date strings), `available` (numbers), `target` (number). Inline SVG
  with a `viewBox`, a left FTE axis with a few ticks, a bottom axis with a
  tick at each month boundary, horizontal gridlines, one polyline in
  `--color-brand` for `available`, and one dashed horizontal line in
  `--color-text-secondary` at `target`. A `<title>` and an
  `aria-label` for the chart. `Dashboard/Index.vue` replaces the plain
  number list with one `FteLineChart` per block: the overall block first,
  then each `lines` block in order, each in its own `Card`. Vitest
  `FteLineChart.test.js` (renders a polyline with the right point count; a
  target line at the right height; the title) and update
  `DashboardIndex.test.js` (one chart per block, overall first). `npm run
  test` and `npm run build` green.

- [ ] 8. **Docs and full checks.** `doc/roadmap.md` — rewrite the phase 3
  row and section as Business Lines (replaces Departments; standard day
  schedules still later), note the dashboard and planning settings.
  `doc/concept.md` — a Business Lines entry under Core Data, replace the
  "Departments return in phase 3" notes, add Business Line to the employee
  record list, note the dashboard under the manager capabilities. Set this
  `plan.md` header to `8/8`. Run Pint, `php artisan test`, `npm run test`,
  `npm run build`, and `php artisan migrate` on the dev database — all
  green.

## Not done / deferred

- Everything under the spec's "Non-goals", demand and planning use
  included.
- Manual browser pass — AGENTS.md leaves UI verification to the user.
