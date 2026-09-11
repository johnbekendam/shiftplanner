# Account Management — Spec

Interim authentication. Roadmap phase 3.7. Phase 2 still owns the
PostgreSQL switch, Entra ID (OIDC), and employee token hardening.

**Superseded passwordless mechanism.** The one-time email code described
below (`login_codes`, `LoginCodeService`, "Email me a code") was
replaced by an emailed link — see `doc/features/login-links/`. Every
other decision on this page (roles, `/users`, `/account`, the employee
link) still stands.

## Problem

The app has one hard-coded seed account and a password-only login. Real
use needs more than one person, roles, and a way in that does not depend
on a shared password. It also needs a manager who runs the planner to be
schedulable like any other employee.

Employees stay as they are: they reach only their personal page, by
token link, with no account.

## Solution

### Roles

`users.role` is an enum, `admin` or `manager`, default `manager`. The
migration backfills the existing seed user to `admin`. At least one
active admin must exist at all times.

- **Admin** — everything. Account management (`/users`), the Settings
  page, Theme Builder, Mailbox, and all employee management.
- **Manager** — `/employees` only for now (list and editor: hours,
  availability, holidays, competences, product groups). The scope grows
  in later phases.

A `role:admin` middleware guards `/users`, `/settings`, `/theme-builder`,
and `/mailbox`. `/employees` and `/account` need only a signed-in user.
The sidebar shows each item by role.

### Login

One screen. Email, an optional password, a **Sign in** button, and an
**Email me a code** button.

**Password path.** Unchanged `LocalAuthService` (session, `is_active`
enforced). `users.password` becomes nullable. A blank password submits
no password attempt.

**One-time code path.** "Email me a code" always answers the same way —
"If that email matches an account, a code is on its way." — and reveals a
six-digit code field. A real code is sent only to an active account.
Entering a valid code signs the user in.

- Code: six digits, valid 10 minutes, single use. A new request voids the
  previous code.
- Throttle: at most 5 requests per email per 15 minutes; 5 wrong entries
  void the code.
- `is_active` is enforced on request and on verify.
- New `login_codes` table: `user_id` (cascade), `code_hash`,
  `expires_at`, `consumed_at`, `attempts`, timestamps.
- A `LoginCodeService` issues and verifies codes and signs the user in
  through the guard. It sits outside `AuthServiceContract` — the code
  path is local-only and Entra will not use it.
- The code email is a Mailable sent synchronously. Local dev keeps the
  `log` mailer.

Routes: `POST /login` (password, exists), `POST /login/code` (request),
`POST /login/code` verify — or `POST /login/code/verify` — with email and
code. `auth.*` i18n keys cover the new copy.

### Admin — `/users` page

An admin-only sidebar item, shaped like `/employees`: an index list, a
create form, an edit form.

- **Create** — `name`, `email` (unique), `role`. No password. The person
  signs in first with a one-time code.
- **Edit** — `name`, `email`, `role`, `is_active`.
- **Deactivate and reactivate** through `is_active`. No hard delete.
- The last active admin cannot be deactivated or demoted.

`/users` does not link accounts to employees.

### Account page — `/account`

For any signed-in user.

- **Set or change password.** Current password is required only when one
  is already set.
- **"Add me as an employee"** — shown to a manager with no linked
  employee. It links an existing `employees` row that has the same email,
  or creates one from the user's name and email with the default weekly
  hours, then sets `users.employee_id`.
- Once linked, a **"My details"** sidebar item opens
  `/employees/{employee_id}/edit`. A manager already edits every
  employee, so this is only a shortcut.

`users.employee_id` is nullable and unique, with `nullOnDelete`.
Deleting the employee row clears the link.

### Shared Inertia data

`HandleInertiaRequests` adds `role` and `employee_id` to the shared
`auth.user`. The sidebar and the "My details" item read them.

### Seed and factory

`DatabaseSeeder` sets the seed user's role to `admin`. `AutoLoginSeedUser`
is unchanged. `UserFactory` gains `role` (default `manager`) and an
`admin()` state.

## Key decisions

- **Interim, seam kept clean.** Local accounts unblock real use.
  `AuthServiceContract` still describes only the password path, so
  `EntraAuthService` can replace it later. The code path is a separate
  service.
- **Two roles, `admin` and `manager`.** The only split needed now is
  "runs the whole app" versus "maintains employees". More roles can join
  the enum later.
- **Employees keep the token link.** An employee is a schedulable
  resource, not a login. Most never sign in. Merging the two tables would
  rework every shipped feature for no user gain.
- **Manager links itself.** "A manager should be able to add
  him/herself as an employee. No admin involved." The action lives on the
  account page, not the `/users` form.
- **`users.employee_id`, not a merge.** One nullable, unique link column.
  The manager-as-employee case is the exception, so it is an optional
  pointer, not a shared identity.
- **Codes, not emailed password links.** A one-time code needs one table
  and no invitation flow, and doubles as password recovery. An admin
  never handles a secret.
- **No hard delete of accounts.** `is_active` off revokes access and
  keeps the history. A last-admin guard stops a lock-out.
- **Password becomes optional.** A new account has none and signs in with
  a code. A user can set one later on the account page.

## Non-goals

- Employee login accounts. Retiring the token link.
- Unlinking yourself from an employee record. Delete the employee row to
  clear the link.
- Password-reset links, "remember this device", authenticator apps.
- Admin-set or emailed-invite passwords.
- Entra ID / OIDC itself, and the PostgreSQL switch — both stay in phase
  2.
- Per-manager scoping of the employee list. Every manager sees every
  employee.
- Queued mail. The code email sends synchronously.
