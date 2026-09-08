# Employee Admin Prototype — Spec

Phase 1 in `doc/roadmap.md`.

## Problem

ShiftPlanner needs a first reviewable increment on top of the TeamApps
template snapshot. A reviewer must be able to create and maintain
employees, assign each employee to one department, and open a realistic
personal-page link that shows the employee experience for changing a
shift preference.

This is a controlled prototype, not an application ready for employee use.
Authentication, authorization, secure token management, and email
invitations are phase 2 and are out of scope here.

## Solution

Extend the template. Reuse its layout, navigation, table, form, dialog,
select, button, and feedback components. Do not add a second UI library or
page shell.

### Manager workflow

1. The reviewer opens the Employees page from the app navigation.
2. The reviewer sees a searchable list of employees: name, email,
   department, shift preference, link status.
3. The reviewer creates or edits an employee.
4. The reviewer opens or copies a personal-page preview link.

### Employee workflow

1. A reviewer opens an employee's personal-page preview link without
   creating an account.
2. The app shows a focused personal page with the employee's name and
   current preference.
3. The reviewer selects `morning`, `evening`, or `either`, then saves.
4. The app confirms the update. No manager navigation, no other
   employees, no departments, no schedule data.

## Data model

### `departments`

- `id`
- `name`
- timestamps

Seed a small set through a seeder. A full department-management UI is
phase 3. The employee form requires one department.

### `employees`

- `id`
- `department_id` — foreign key, required
- `name`
- `email`
- `shift_preference` — `morning`, `evening`, or `either`
- timestamps

Add a unique constraint on `email` for the single initial organization.
Tenant-aware uniqueness is deferred until organization support exists.

### `employee_personal_links`

- `id`
- `employee_id`
- `token` — opaque, plain-text, preview only
- timestamps

The token is a navigation aid for a realistic URL, not an authentication
mechanism. Phase 2 replaces it with hashed, random, expiring, revocable
tokens.

## Routes

```text
GET    /employees
GET    /employees/create
POST   /employees
GET    /employees/{employee}/edit
PUT    /employees/{employee}
GET    /employees/{employee}/personal-page
GET    /personal/{token}
PUT    /personal/{token}/preference
```

No `mailto:` invitation route in this increment. The reviewer copies or
opens the preview URL directly.

## Backend structure

- Eloquent models: `Department`, `Employee`, `EmployeePersonalLink`.
- Thin controllers with inline `$request->validate()`, matching the
  template's established pattern. The template ships no FormRequest classes,
  so this increment adds none.
- An `EmployeePersonalLinkService` for generating preview tokens and URLs
  and resolving a token to an employee. Controllers delegate link behavior
  to it.

## Frontend structure

- An Employees index page using the template table and its empty-state
  pattern.
- An employee create/edit page or dialog following the template's form
  pattern, with its validation-error display.
- An action that opens or copies the personal-page preview link.
- A separate minimal employee personal page, no manager navigation, built
  on `CenteredLayout`.
- Template-native confirmation dialogs and saved/invalid feedback.

## Key decisions

- Local dev on SQLite; no container runtime. Migrations target PostgreSQL
  semantics (real FK actions, enum constraint) so phase 2 switches the
  driver without a rewrite. Verified once against PostgreSQL during
  scaffolding.
- Preview tokens are plain text and non-secure by design. Synthetic data
  only. Deployment stays local or restricted to trusted reviewers.
- No authentication or authorization code in this increment. The
  template's `auth` middleware still guards the manager routes; the
  personal-page routes are token-only and unguarded.
- All user-facing text goes in `resources/lang/en.json` under an
  `employees.*` / `personal.*` namespace.

## Non-goals

- Department management UI and standard day schedules.
- Published employee schedule view.
- Detailed availability and wishes.
- Schedule optimization and queue jobs.
- Entra ID SSO or any manager login change.
- Organizations and multi-tenancy.
- Server-side email delivery.

## Acceptance criteria

- A reviewer creates an employee with name, email, one department, and a
  valid preference.
- Invalid or missing fields show the template's standard validation
  feedback.
- The list displays created employees and supports editing them.
- A generated preview link opens the matching employee's focused page.
- A reviewer saves a new preference through the personal page.
- A malformed preview link does not resolve to an employee page.
- The personal page has no manager navigation and exposes no other
  employee records.
- The feature uses the template's existing layout and components and
  passes its automated checks.
