# Business Lines — Spec

Roadmap phase 3. Business Lines replace the planned "Departments"
concept. An employee belongs to one Business Line. Standard day schedules
hang off a Business Line in a later phase.

## Problem

The data model has no org-unit concept. A manager cannot group employees,
set a staffing target for a group, or see how much capacity a group has
over a date range. Holidays already cut an employee's real availability,
but nothing adds that up.

This feature adds the Business Line record, a per-employee assignment, a
small set of planning settings, and a dashboard that plots available FTE
per day against each Business Line's target.

## Solution

### Business Line records

A new `business_lines` table:

| column | notes |
| --- | --- |
| `id` | |
| `abbreviation` | required, case-insensitive unique, max 10 |
| `description` | required, max 255 |
| `target_fte` | decimal(5,1), `>= 0` |
| `position` | integer, sets the manual order |
| timestamps | |

A `BusinessLine` model with a position-ordered default scope, an
`employees()` `hasMany`, and a `toPayload()` returning
`{ id, abbreviation, description, target_fte, position }`.

### Employee assignment

`employees` gains a nullable `business_line_id` foreign key,
`ON DELETE SET NULL`. `Employee::businessLine()` is a `belongsTo`.

The assignment is optional. An employee with no Business Line still
appears in the dashboard's overall chart.

### Planning settings

A single-row `planning_settings` table:

| column | notes |
| --- | --- |
| `id` | always 1 |
| `fte_hours` | integer, `> 0`, default 40 |
| `period_start` | date, nullable |
| `period_end` | date, nullable |
| timestamps | |

`fte_hours` is the weekly hours that equal one FTE. `period_start` and
`period_end` bound the dashboard's day axis. A `PlanningSettings` model
exposes a `current()` helper that returns the row, creating it with
defaults on first read.

### Settings page — two new tabs

`/settings` stays admin-only. The tabbed card gains:

**Business lines** — after Product groups. Inline multi-field rows in the
style of the other tabs, but three fields wide:

- Each row has an abbreviation field, a description field, and a
  target-FTE number field. A field that loses focus, or `Enter`, sends
  `PUT /settings/business-lines/{businessLine}` with the whole row and
  reloads. The server re-checks the abbreviation.
- An up and a down button, `PUT /settings/business-lines/{businessLine}/move`
  with `direction` in `up|down`. Neighbour swap, no-op past an end.
- A delete button behind a confirm that names the assigned-employee count
  ("3 employees are in this Business Line. Delete it?"). On confirm,
  `DELETE /settings/business-lines/{businessLine}`. The row goes; each
  assigned employee's `business_line_id` becomes null.
- An add row sends `POST /settings/business-lines`. The new row takes
  `position` = current maximum + 1.

**Period** — after Business lines. One form, one Save button:

- An FTE-hours number field (`fte_hours`).
- A period-start date field and a period-end date field.
- `PUT /settings/period` saves all three. `period_end` must not be before
  `period_start`. Either date may be left blank; the dashboard shows an
  empty state until both are set.

`BusinessLineController` mirrors `ProductGroupController` (`store`,
`update`, `destroy`, `move`). `PeriodController@update` saves the
singleton. `SettingsController@index` adds `businessLines` as
`{ id, abbreviation, description, target_fte, position, employee_count }`
in position order, and `period` as `{ fte_hours, period_start, period_end }`.

### Employee Details tab

`Employees/Form.vue` and `EmployeeFields.vue` gain a Business Line
`SelectInput`, after Weekly hours. Options are every Business Line by
abbreviation, plus a blank "None". The value is part of the Details form
and saves with its Save button. `weekly_hours` validation is unchanged;
`business_line_id` is `nullable|exists`.

`EmployeeController@create` and `@edit` payloads gain `businessLines`
(`{ id, abbreviation }`, position order). The `edit` payload's `employee`
already carries `business_line_id` once it is `$fillable`.

The personal page does not show or set the Business Line. It is a
manager-only field.

### Dashboard

A new `/dashboard` Inertia page behind plain `auth`, so every signed-in
user (admin or manager) reaches it. It becomes the landing page:
`Route::redirect('/', '/dashboard')`. A "Dashboard" sidebar item leads
the nav for all users.

`DashboardController@index` reads `PlanningSettings::current()`. If either
date is blank it returns `period: null` and the page shows a short
"set a period on the Settings page" message. Otherwise it computes, for
every day from `period_start` to `period_end` inclusive:

- **Per employee, per day** available FTE:
  - `0` if the day is a Saturday or Sunday.
  - `0` if the day falls inside any of the employee's holiday ranges
    (inclusive).
  - `0` if `weekly_hours` is `0`.
  - otherwise `weekly_hours / fte_hours`.
- **Overall series** — the sum over every employee, each day.
- **One series per Business Line** — the sum over that line's employees,
  each day.

The payload carries `days` (the date list), an `overall` block
(`{ available: number[], target: number }` where `target` is the sum of
all `target_fte`), and `lines` (one block per Business Line:
`{ abbreviation, description, available: number[], target: target_fte }`).

The page renders one chart per block: the overall chart first, then one
per Business Line in position order. Each chart is an `FteLineChart.vue`:

- Inline SVG. A left FTE axis, a bottom date axis with month ticks,
  horizontal gridlines.
- One `--color-brand` polyline for `available`.
- One dashed `--color-text-secondary` horizontal line at `target`.
- A title from the block (`Overall`, or `abbreviation — description`).

The component takes one series and one target so the overall and per-line
charts share it. Merging the charts into one multi-series view is a later
change.

## Key decisions

- **Business Lines are the Departments slot.** The roadmap's phase 3
  "Departments" concept ships as Business Lines. There is no separate
  Department record. Schedules attach to a Business Line later.
- **Assignment is optional and saves with the form.** The select is a
  plain Details field, not a write-on-toggle control, because it is a
  single value that belongs with name and hours, not a checklist. A
  nullable column keeps existing employees valid with no data migration.
- **Delete sets null, after a count-named confirm.** Same tone as a
  competence delete. A Business Line can leave the list without blocking
  on its members; the members lose the link and nothing else.
- **Inline multi-field rows, not the shared `OrderedNameList`.** That
  component is single-field. A Business Line has three fields, so the tab
  gets its own row component with the same blur-to-save and up/down feel.
- **Planning settings are one row.** `fte_hours` and the period are
  global, not per Business Line. A helper creates the row with defaults
  so no seeder is needed.
- **The bucket is always one day.** Demand, in a later phase, is per day.
  A day axis now means the dashboard projects onto demand with no
  re-bucketing. Weekends and holidays zero a day; nothing else reduces
  it.
- **Weekends are non-working.** Available FTE is a weekday figure. The
  recurring availability grid does not enter this calculation.
- **The overall chart counts everyone.** Employees with no Business Line
  still consume capacity, so the overall available curve includes them
  and can sit above the sum of the per-line curves. The overall target
  is the sum of the per-line targets.
- **One hand-rolled SVG chart component, one series each.** No charting
  dependency enters the project. A single brand colour with a dashed
  target line is enough for a one-series chart. Series-colour tokens are
  not added.

## Non-goals

- Demand. The day axis anticipates it; no demand record, curve, or
  comparison ships here.
- The recurring availability grid in the FTE calculation.
- A merged multi-series chart, a legend, or chart-series colour tokens.
- Per-Business-Line schedules, shifts, or coverage requirements.
- Planning use of the Business Line or the target. The `/solve` contract
  is untouched.
- Setting the Business Line from the personal page.
- A per-Business-Line period or FTE-hours value.
- Public-holiday calendars. Only per-employee holiday ranges zero a day.
