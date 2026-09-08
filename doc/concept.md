# ShiftPlanner Concept

## Purpose

ShiftPlanner is a browser-based shift scheduling tool for shift-based teams. It helps managers keep employee information current, define departmental coverage needs, and generate fair schedules automatically.

ShiftPlanner runs on Prodrive's intranet only. It is not exposed to the public internet.

The first useful capability is employee administration: managers can add people to a list and maintain their name, email address, department, and shift preferences.

## Users and Access

### Managers

Managers administer the organization:

- Create departments and standard day schedules.
- Maintain the competence list on the Settings page.
- Add and edit employees.
- Assign every employee to exactly one department.
- Set shift coverage requirements.
- Generate, review, edit, and publish schedules.
- Send employee invitations with a prepared `mailto:` message containing a personal link.

The application does not require an outbound email server initially. The manager's local email client sends the prepared invitation. Server-side email delivery can replace this later.

### Employees

Each employee receives a private personal-link token. It gives access only to that employee's information:

- Edit shift preferences.
- View their own published assignments.

Employees cannot view other employees, edit departmental schedules, or publish plans.

## Core Data

### Departments and Standard Schedules

Each department owns its schedule definition. A standard day schedule contains one or more shifts. Every shift specifies its time range and required employee count.

The exact calendar model, including weekly recurrence and date-specific exceptions, is intentionally deferred until planning cadence is known.

### Competences

A configurable list of skills, maintained on the Settings page. Each
employee holds a subset, checked by a manager or the employee. A later
phase lets a work centre require a competence, so only employees who hold
it can be planned there.

### Product Groups

A configurable list of product families, maintained on the Settings page.
Each employee has a set of preferred product groups, set by a manager or
the employee. A later phase uses the preference as a planning wish.

### Employees

An employee record contains:

- Name
- Email address
- Weekly hours: 20 to 48, in steps of 4
- Holidays: whole-day date ranges the employee is away. Hard blocks for
  the planner. A manager or the employee maintains them.
- Recurring availability grid: three dayparts by seven weekdays. Each
  cell is available, not preferred (soft), or unavailable (hard). A
  manager or the employee maintains it.
- Competences: the skills the employee holds, checked from the
  configurable competence list. A manager or the employee maintains them.
  Planning use comes later.
- Preferred product groups: the product families the employee would
  rather work on, checked from the configurable product group list. A
  manager or the employee maintains them. Planning use comes later.

Department and the old morning/evening/either preference were
placeholders and are removed. Departments return as a configurable set in
phase 3. The recurring grid replaces the preference. Its dayparts map to
named shifts in phase 3.

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

The manager experience starts with an employee list. From there, a manager can add an employee, edit employee details, assign a department, and create an invitation link.

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

- Managers authenticate through the template's `AuthServiceContract` seam. The prototype uses local session login. Real use adds `EntraAuthService`: direct Microsoft Entra ID (OIDC) against Prodrive's tenant.
- Employees never hold accounts. Each employee record is reached through a per-employee token link, resolved by a dedicated employee guard. The prototype uses opaque non-secure preview tokens with synthetic data only. Real use replaces them with hashed, cryptographically random, expiring, revocable tokens and negative security tests.

## Deferred Decisions

- Availability model — holidays and the recurring weekday/daypart grid are done (`features/employee-availability/`). Date-specific shift exceptions and fairness weights are still open.
- Calendar recurrence and exceptions for standard day schedules.
- Employee assignment confirmation, swap, or self-scheduling workflows.
- Exact token-link security, expiry, revocation, and recovery behavior.
- Department qualifications or multi-department staffing.
- Server-side email delivery.
- Fairness definitions, planning cadence, and the exact JSON contract for the OR-Tools worker.
- The Entra ID integration package and claims mapping.

## Out of Scope for the First Increment

- Employee self-assignment to open shifts.
- Automatic schedule publication.
- Multi-department employee assignments.
- Detailed availability calendars.
- Server-managed email delivery.