# ShiftPlanner Roadmap

Build order and status. Each phase leaves the repo in a working, booting
state. `concept.md` holds the reasoning. Each feature has its own folder
under `features/<name>/` with a `spec.md` (the design) and, once work
starts, a `plan.md` (the steps and progress).

## Status

| Phase | Feature | State | Detail |
| --- | --- | --- | --- |
| 0 | Scaffold — TeamApps template snapshot, SQLite dev DB, compose stub | Done | — |
| 1 | Employee admin prototype — manager employee list/editor, personal-page preview link | Done | `features/employee-admin/spec.md`, `features/employee-admin/plan.md` |
| 2 | Auth hardening — manager Entra ID (OIDC), employee token hardening | Planned | grill first, then `features/auth-hardening/spec.md` |
| 3 | Business Lines and standard day schedules — config list, per-employee assignment, FTE dashboard; then schedules and coverage | In progress | Business Lines shipped (`features/business-lines/`). Shift definitions shipped (`features/shift-definitions/`). Shift coverage (required headcount, the workcenter link) still needs a design session. |
| 3.5 | Competences — config list on a Settings page, per-employee checkmarks | Done | `features/competences/spec.md`, `features/competences/plan.md`. Planning use is out of scope. |
| 3.6 | Product groups | Removed | Shipped, then removed from the product. Tables dropped by `2026_09_09_000007`; the config tab, per-employee checklist, routes, and language keys are gone. |
| 3.7 | Account management (interim auth) — admin/manager roles, password or email code, admin `/users`, account page | Done | `features/account-management/`. Entra ID, the PostgreSQL switch, and employee token hardening stay in phase 2. |
| 3.8 | Mailbox and employee change lock — typed messages with a reusable template, one type (personal-page link), Microsoft Graph transport prepared but inert; a global switch that makes the personal page read-only | Mostly done | Both features shipped (`features/mailbox/`, `features/employee-change-lock/`). Only the Graph tenant values (Azure app registration, shared mailbox, admin consent) are pending, on a machine with tenant access. Supersedes the phase-2 `mailto:` line with a shared Graph mailbox. |
| 4 | Availability and wishes — recurring availability, date-specific exceptions, fairness model | In progress | Holidays and the recurring availability grid shipped (`features/employee-availability/`); the grid is weekday-only and carries manager-defined yes/no questions (`features/availability-questions/`). Fairness model still needs a design session. |
| 5 | Scheduling engine — Python OR-Tools `/solve` service, JSON contract, `GeneratePlan` job, draft review and edit | Planned | depends on phases 3 and 4 |
| 6 | Publish and employee schedule view — publish a plan, employees see own assignments only | Planned | — |

## Phase 0 — Scaffold

Snapshot `/Users/jb/Development/TeamApps/template` into this repo, keep
this repo's `doc/`. Set `APP_NAME=ShiftPlanner`. Local dev runs on SQLite
(`DB_CONNECTION=sqlite`) — no container runtime needed to develop.
`docker-compose.yml` carries a `postgres` service plus `app` / `scheduler`
stubs, unused until phase 2 wires PostgreSQL in.

Migrations and code target PostgreSQL semantics (real FKs, enum
constraints) so the phase-2 switch is a config change, not a rewrite.

Done: repo boots, `php artisan test` (70) and `npm run test` (100) pass —
the template suite runs on in-memory SQLite — and `php artisan migrate`
runs clean (8 tables). Verified once against the `postgres` container
before the switch to a SQLite dev DB.

No automated re-sync with the template after the snapshot. Carry the
template's known-issues list forward by hand if it matters.

## Phase 1 — Employee admin prototype

A controlled prototype, not ready for employee use. A reviewer creates and
maintains employees, assigns each to one department, and opens a realistic
personal-page preview link to evaluate the employee experience and change
a shift preference. No authentication, synthetic data only, restricted
deployment. See `features/employee-admin/spec.md`.

App shell: `AppLayout` sidebar has two items — Employees and Theme Builder.
Theme Builder now renders inside `AppLayout` and, like the app pages, sits
behind `auth`; local dev relies on the gated `AUTH_AUTO_LOGIN` seed user
until phase 2 builds real manager auth. `/` redirects to `/employees`.

Theme: the Theme Builder output was baked in as the app default —
`ThemeTokens::COLOR_DEFAULTS` / `BRAND_FAMILY_DEFAULT` carry the
ShiftPlanner theme (accents on the `sky` brand ramp, light and dark),
`app.css` mirrors it, and `app.blade.php` always emits the token `<style>`
block (no stored `theme-tokens.json` needed). Re-save in the builder and
re-bake (`ThemeTokens.php` + `app.css` + `themeBuilderProps.js`) to change
the shipped default. The theme unit tests now `Storage::fake()` so a suite
run no longer deletes a developer's saved `theme-tokens.json`.

## Phase 2 — Auth hardening

Required before any real employee use.

- Database: switch to PostgreSQL. Bring up the `postgres` compose service,
  flip the `DB_*` vars, run migrations, add a CI job that runs the suite
  against PostgreSQL. Confirm the enum and FK constraints behave.
- Managers: bind `EntraAuthService` to the template's `AuthServiceContract`
  seam. Direct Microsoft Entra ID (OIDC) against Prodrive's tenant. Role
  and active-state on the `User` model.
- Employees: replace preview tokens with hashed, cryptographically random,
  expiring, revocable tokens. A dedicated employee guard and middleware.
  Regeneration and audit fields. Negative security tests for unauthorized
  access.
- Invitation delivery is handled in phase 3.8 (`features/mailbox/`): a
  shared-mailbox Microsoft Graph transport sends the personal link. The
  original `mailto:` plan is dropped.

## Phase 3 — Business Lines and standard day schedules

Business Lines are the org unit, taking the slot the plan first called
"Departments". See `features/business-lines/`.

Shipped: a Settings tab to add, edit, reorder, and delete Business Lines
(abbreviation, description, target FTE); an optional Business Line select
on the employee Details tab; a Settings tab for the planning period
(start date, end date, hours per FTE); and a dashboard, now the landing
page, that charts available FTE per weekday — `weekly_hours / fte_hours`,
nothing inside a holiday, weekend dates dropped — for every employee and
for each Business Line against its target. Each card also carries a
coverage donut: available vs required person-hours over the period
(`features/dashboard-coverage/`).

Also shipped (`features/shift-definitions/`): a Settings tab to add, edit,
and delete shifts (name, start time, end time; ordered by start time).
The recurring availability grid now has one row per defined shift instead
of the three fixed dayparts.

Also shipped (`features/shift-info-note/`): one global Markdown note on
the Shifts tab, rendered at the top of every employee's availability page
— typically an allowances table per shift.

Still to design: required headcount per shift and the shift-to-workcenter
link. Calendar recurrence and date-specific exceptions stay in phase 4.

## Phase 3.5 — Competences

Built ahead of phases 2 and 3. See `features/competences/`.

A new Settings page (sidebar item, `/settings`) holds a tabbed card, one
tab per configuration category. The first tab is Competences: a manager
adds, renames, reorders (up and down, manual `position`), and deletes
competences. Delete asks for confirmation and names the holder count,
then cascades the employee links.

A competence has a name only. `competence_employee` is the pivot. Both
the manager (employee editor) and the employee (personal page) toggle
which competences an employee holds, on a new Competences tab, matching
the holiday and availability pattern.

Planning use — a work centre that requires a competence — is out of
scope. Only the data and the two UIs ship here.

## Phase 3.6 — Product groups (removed)

Built after competences, then removed from the product. The
`product_groups` and `employee_product_group` tables, the config tab, the
per-employee checklist, and all routes and language keys are gone. The
`2026_09_09_000007` migration drops the tables; the original create
migrations stay in history. The employee editor and personal page tab is
`Competences` again (its Profile-era rename is reverted). The shared
`OrderedNameList` and `TagChecklist` components stay — competences use
them.

## Phase 3.7 — Account management (interim auth)

Built ahead of phase 2. See `features/account-management/`. Interim: the
`AuthServiceContract` seam still describes the password path so an Entra
`EntraAuthService` can replace it later.

`users.role` is `admin` or `manager`; the seed user is `admin`. An
`admin` middleware gates `/settings`, `/theme-builder`, `/mailbox`, and
`/users`. A manager reaches `/employees` only for now.

Login is one screen: email, an optional password, and "Email me a code".
The code path issues a six-digit code (hashed, 10-minute TTL, single use,
previous code voided), mailed synchronously, silent for an unknown or
inactive email; five wrong entries burn it. `users.password` is nullable.

An admin manages accounts on `/users` (create with no password, edit,
deactivate; the last active admin is protected). Every signed-in user has
an `/account` page to set a password. A manager can add itself as an
employee there (`users.employee_id`), which then shows a "My details"
shortcut. Employees still reach only their personal page, by token link.

The PostgreSQL switch, Entra ID / OIDC, and employee token hardening stay
in phase 2.

## Phase 3.8 — Mailbox and employee change lock

Two independent features, one phase. See `features/mailbox/` and
`features/employee-change-lock/`.

Mailbox: the phase-0 template left a generic mailbox baseline. This turns
it into a typed message tool. Every message has a type; one type ships,
`personal_page_link`. Each type has one stored, editable template
(subject + Markdown body, `:name` / `:link` placeholders) kept in a
`message_templates` table and edited on the Compose tab. Compose picks a
type, edits the template, selects employees from a searchable list,
previews the branded email, and creates one draft per employee with the
link resolved. Draft / Outbox / Sent are shared across all admins. The
employees list and editor carry a **Send link** / **Resend link** action
that opens Compose preselected. Microsoft Graph delivery is prepared but
inert: a custom `graph` Laravel mail transport, app-permission client
credentials against a shared mailbox (`POST /users/{mailbox}/sendMail`),
config keys and blank `GRAPH_*` env. Local dev keeps `MAIL_MAILER=log`;
the Graph path is exercised on a machine with tenant access. This
supersedes the phase-2 `mailto:` invitation plan.

Employee change lock: a global `planning_settings.allow_employee_changes`
switch, default on, on the renamed **General** settings tab (was Period).
When off, the five personal write routes return `403` and the personal
page renders read-only with a banner; `personal.show` and every manager
route are untouched.

## Phase 4 — Availability and wishes

Dedicated design session before any code. Recurring availability,
date-specific exceptions, and the fairness definitions (workload fairness,
wish fairness) that become objective terms in the optimizer. Output: the
data model and the shape of the `/solve` JSON contract.

Done so far — `features/employee-availability/`:

- Per-employee holidays. Whole-day date ranges, hard blocks, no approval.
  A manager or the employee adds and removes them on a new Availability
  tab.
- Recurring availability grid. One row per defined shift (phase 3), five
  weekdays (Monday–Friday). Each cell is `available`, `not_preferred`
  (soft), or `unavailable` (hard). One table, `weekday` + `shift_id` +
  `level`. It replaces the old shift preference. The three fixed dayparts
  it first shipped with were superseded by the shift definitions; the
  weekend columns were dropped by `features/availability-questions/`.
- Availability questions (`features/availability-questions/`). A manager
  keeps a list of yes/no questions on a Settings tab — "can we call you
  in for a weekend?", "are you reachable in week 53?". The employee or
  the manager answers each with a checkbox on the Availability tab. A
  checked box is a stored pivot row; nothing else. No planner use yet.
- The global `allow_employee_changes` switch (phase 3.8,
  `features/employee-change-lock/`) closes all employee-side edits to
  this data while keeping the personal page viewable — a manager uses it
  to freeze availability during a planning round.

Still open: the fairness definitions and objective-term weights, and the
`/solve` service that reads this data.

## Phase 5 — Scheduling engine

- A separate containerized Python service running OR-Tools CP-SAT. One
  endpoint, `POST /solve`. No database access. Shared secret on the
  internal network.
- Laravel stays the system of record. A `GeneratePlan` queue job builds a
  JSON problem document, calls `/solve`, writes the returned draft and its
  unfulfilled-wish reasons to PostgreSQL (`jsonb`).
- Laravel-side planning sits behind a `PlanGenerator` interface. No PHP
  heuristic planner is written.
- Manager reviews and manually adjusts the draft. The plan is advice, not
  an automatic publication.

## Phase 6 — Publish and employee schedule view

A manager publishes a reviewed plan. Employees see only their own
assignments, and only after publication. No manager navigation, no other
employees, no draft data on the employee page.

## Deferred and out of scope for the first increments

- Employee self-assignment to open shifts.
- Automatic schedule publication.
- Multi-department employee assignments.
- Server-managed email delivery.
- Organizations and multi-tenancy.
