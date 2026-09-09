# ShiftPlanner Concept

## Purpose

ShiftPlanner is a browser-based shift scheduling tool for shift-based teams. It helps managers keep employee information current, define departmental coverage needs, and generate fair schedules automatically.

ShiftPlanner runs on Prodrive's intranet only. It is not exposed to the public internet.

The first useful capability is employee administration: managers can add people to a list and maintain their name, email address, department, and shift preferences.

## Users and Access

### Managers

Managers administer the organization:

- Create Business Lines and standard day schedules.
- Maintain the competence and Business Line lists, the shifts, the
  availability questions, and the planning period, on the Settings page.
- Watch available FTE over the period on the dashboard.
- Add and edit employees.
- Assign each employee to a Business Line.
- Set shift coverage requirements.
- Generate, review, edit, and publish schedules.
- Send an employee the link to their personal page from the mailbox.

The mailbox composes typed messages from a reusable per-type template,
previews the branded email, and stores each as a draft. One type ships:
the personal-page link. Delivery goes through Microsoft Graph from one
shared mailbox (app-permission client credentials); until that is
configured the app keeps the `log` mailer and messages simply stay in the
outbox. Drafts, outbox, and sent are shared across all admins.

### Employees

Each employee receives a private personal-link token. It gives access only to that employee's information:

- Edit shift preferences.
- View their own published assignments.

Employees cannot view other employees, edit departmental schedules, or publish plans.

## Core Data

### Business Lines and Standard Schedules

A Business Line is the org unit an employee belongs to. It has an
abbreviation, a description, a target FTE, and a manual order. Managers
maintain the list on the Settings page. Each employee is assigned to at
most one Business Line, on the employee Details tab. Business Lines take
the slot the earlier plan called "Departments"; there is no separate
Department record.

A per-Business-Line standard day schedule — which shifts a line runs and
the required headcount for each — comes in a later phase. The exact
calendar model, including weekly recurrence and date-specific exceptions,
is deferred until planning cadence is known.

### Shifts

A shift is the planning baseline unit. It has a name, a start time, and
an end time; the end must be after the start, so no shift crosses
midnight. Managers maintain the list on the Settings page. Shifts show in
start-time order everywhere.

Shifts are global, not per Business Line. Required headcount and the link
from a shift to a workcenter come in a later phase. The recurring
availability grid has one row per shift.

The Shifts settings tab also holds one global Markdown note — shift
information such as an allowances table. It renders at the top of every
employee's availability page, on the manager editor and the personal
page. Only an admin edits it.

### Dashboard

The signed-in landing page. It reads a global planning period (a start
date, an end date) and the weekly hours that count as one FTE, both set
on the Settings page. For each weekday in the period it charts available
FTE: an employee counts `weekly_hours / fte_hours`, and nothing inside a
holiday. Weekend dates are dropped from the charts. One card totals every
employee against the summed Business Line targets; one card per Business
Line totals its members against that line's target. Each card also
carries a donut showing how much of its required hours are covered —
`available_hours` against `required_hours`, both real person-hours over
the period at `fte_hours / 5` per working day.

### Competences

A configurable list of skills, maintained on the Settings page. Each
employee holds a subset, checked by a manager or the employee. A later
phase lets a work centre require a competence, so only employees who hold
it can be planned there.

### Availability Questions

A configurable list of yes/no questions, maintained on the Settings page
— for cover the weekday grid does not describe, such as weekend
call-outs or an extra ISO week. Each employee answers each question with
a checkbox on the Availability tab; a checked box is the only thing
stored, so an unanswered question counts as no. A later phase decides how
the planner reads the answers.

### Employees

An employee record contains:

- Name
- Email address
- Weekly hours: 20 to 48, in steps of 4
- Holidays: whole-day date ranges the employee is away. Hard blocks for
  the planner. A manager or the employee maintains them.
- Recurring availability grid: one row per defined shift by five weekdays
  (Monday–Friday). Each cell is available, not preferred (soft), or
  unavailable (hard). A manager or the employee maintains it.
- Availability question answers: a yes/no answer to each question on the
  configurable question list. Unanswered means no. A manager or the
  employee sets them on the Availability tab.
- Competences: the skills the employee holds, checked from the
  configurable competence list. A manager or the employee maintains them.
  Planning use comes later.
- Business Line: the org unit the employee belongs to, chosen from the
  configurable Business Line list. Optional. A manager sets it; the
  personal page does not show it.

A global switch on the Settings General tab, `allow_employee_changes`
(default on), governs every "a manager or the employee maintains it"
field above. When off, the personal page stays visible but read-only and
the employee-side write routes return `403`; the manager editor is
unaffected.

The old morning/evening/either preference was a placeholder and is
removed. The recurring grid replaces the preference. It first shipped
with three fixed dayparts; phase 3 shift definitions replaced those, so
each grid row is now a named shift.

## Planning Workflow

1. A manager defines departmental shifts and required coverage.
2. Employees maintain their shift preferences through personal links, while managers can maintain employee records.
3. A manager requests an optimized plan.
4. The system produces an editable schedule draft.
5. The manager reviews and may manually adjust the draft.
6. The manager publishes the plan.
7. Employees can see their own assignments only after publication.

## Optimization Rules

The planner produces the best feasible draft using this priority order:

1. Meet required shift coverage.
2. Never violate hard availability constraints once they are introduced.
3. Distribute workload fairly among eligible employees.
4. Honor employee wishes and preferences as fairly as possible.

Fairness applies both to assigned workload and to fulfilled wishes. The system must show a clear overview of unfulfilled wishes, including a reason when it can determine one, such as insufficient coverage alternatives or a higher-priority constraint.

The generated plan is advice, not an automatic publication. Managers remain responsible for review and publication.

## Initial Product Shape

The manager experience starts with an employee list. From there, a manager can add an employee, edit employee details, assign a department, and send the employee their personal-page link from the mailbox.

As the product expands, manager navigation should include employees, departments, schedules, planning drafts, and published schedules. Employee links should open a focused personal view with preferences and assigned shifts.

## Technology

Decided in a design session on 2026-09-08. `roadmap.md` holds the build order.

- Application framework: Laravel 12 with PHP.
- Frontend: Inertia with Vue.
- Database: SQLite for local development; PostgreSQL is the deployment
  target, wired in at roadmap phase 2. Migrations and code target
  PostgreSQL semantics so the switch is a config change.
- Deployment: Linux containers on the Prodrive intranet.
- Background work: Laravel queues (`database` driver) for schedule generation.
- Starting point: a snapshot of the internal `TeamApps/template` scaffold (token theming, `Input/*` components, i18n, `AuthServiceContract` seam). The snapshot then diverges; there is no automated re-sync.

Schedule generation runs outside HTTP requests through a Laravel queue job.

The schedule optimizer is a separate service from the start, not a deferred addition. A private Python worker uses Google OR-Tools (CP-SAT). Laravel stays the system of record and calls the worker through one narrow interface: the queue job builds a JSON problem document, the worker returns a draft plus unfulfilled-wish reasons. Laravel-side planning sits behind a `PlanGenerator` interface so no throwaway PHP planner is written. The worker is not built in the first increment, which has no scheduling, but the architecture accounts for it now.

## Authentication

- Accounts have a role, `admin` or `manager`. An admin maintains
  everything, including accounts on `/users`. A manager maintains
  employees for now. At least one active admin must exist. The seed user
  is the first admin.
- Sign-in is one screen: email with an optional password, or a six-digit
  code emailed on request (10-minute, single-use; the response never says
  whether the email is an account). A new account has no password and
  signs in with a code until it sets one on the `/account` page.
- This is the interim scheme. Managers still authenticate through the
  `AuthServiceContract` seam for the password path, so real use can add
  `EntraAuthService`: direct Microsoft Entra ID (OIDC) against Prodrive's
  tenant. The code path is local-only and outside the seam.
- A manager can add itself as an employee from `/account`, linking its
  account to one employee record so the same person is also schedulable.
- Employees never hold accounts. Each employee record is reached through a
  per-employee token link, resolved by a dedicated employee guard. The
  prototype uses opaque non-secure preview tokens with synthetic data
  only. Real use replaces them with hashed, cryptographically random,
  expiring, revocable tokens and negative security tests.

## Deferred Decisions

- Availability model — holidays and the recurring weekday/shift grid are done (`features/employee-availability/`, `features/shift-definitions/`). Date-specific shift exceptions and fairness weights are still open.
- Calendar recurrence and exceptions for standard day schedules.
- Employee assignment confirmation, swap, or self-scheduling workflows.
- Exact token-link security, expiry, revocation, and recovery behavior.
- Department qualifications or multi-department staffing.
- Microsoft Graph tenant details: the Azure app registration, the shared
  mailbox address, and admin consent for `Mail.Send`
  (`features/mailbox/`). The transport and config keys exist; only the
  values are outstanding.
- Fairness definitions, planning cadence, and the exact JSON contract for the OR-Tools worker.
- The Entra ID integration package and claims mapping.

## Out of Scope for the First Increment

- Employee self-assignment to open shifts.
- Automatic schedule publication.
- Multi-department employee assignments.
- Detailed availability calendars.
- Message types beyond the personal-page link; bulk send.