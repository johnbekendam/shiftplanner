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
| 3 | Departments and standard day schedules — management UI, shift coverage requirements | Planned | grill first |
| 4 | Availability and wishes — recurring availability, date-specific exceptions, fairness model | In progress | Holidays and the recurring availability grid shipped (`features/employee-availability/`). Fairness model still needs a design session. |
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
- `mailto:` invitation generation from the manager's employee editor.

## Phase 3 — Departments and standard day schedules

Department management UI. Each department owns its schedule definition. A
standard day schedule holds one or more shifts, each with a time range and
a required employee count. Calendar recurrence and date-specific
exceptions are deferred to phase 4.

## Phase 4 — Availability and wishes

Dedicated design session before any code. Recurring availability,
date-specific exceptions, and the fairness definitions (workload fairness,
wish fairness) that become objective terms in the optimizer. Output: the
data model and the shape of the `/solve` JSON contract.

Done so far — `features/employee-availability/`:

- Per-employee holidays. Whole-day date ranges, hard blocks, no approval.
  A manager or the employee adds and removes them on a new Availability
  tab.
- Recurring availability grid. Three dayparts by seven weekdays. Each
  cell is `available`, `not_preferred` (soft), or `unavailable` (hard).
  One table, `weekday` + `daypart` + `level`. It replaces the old shift
  preference. Dayparts map to named shifts in phase 3.

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
