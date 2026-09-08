# Account Management — Plan

Status: in progress — 4/7

Spec: `spec.md`. Roadmap phase 3.7.

- [x] 1. **Schema and model: roles, optional password, employee link.**
  One migration: add `users.role` (string/enum, default `manager`), make
  `users.password` nullable, add `users.employee_id` (nullable, unique,
  `nullOnDelete`), and backfill the row whose email matches
  `config('auth.seed_user.email')` to `admin`. `User`: `role` and
  `employee_id` fillable, `ROLE_ADMIN` / `ROLE_MANAGER` constants,
  `isAdmin()`, `employee()` belongsTo. `Employee::user()` hasOne.
  `UserFactory`: `role` default `manager`, plus an `admin()` state;
  `password` still hashed by default (a `passwordless()` state sets it
  null). `DatabaseSeeder` sets the seed user's role to `admin`.
  `HandleInertiaRequests` shares `role` and `employee_id` on
  `auth.user`. Tests: migration runs on the dev copy; the seed user is
  `admin` after seeding; `UserFactory::admin()` and `passwordless()`
  behave; the shared props carry `role` and `employee_id`. Full PHP
  suite green.

- [x] 2. **`role:admin` middleware and route gating.** New middleware
  (registered as `admin`) that 403s a signed-in non-admin and defers to
  `auth` for a guest. Wrap the `/settings*`, `/theme-builder*`, and
  `/mailbox*` routes in it; `/employees*` stays `auth`-only. Update every
  existing feature test that signs in to reach those routes so it uses
  `User::factory()->admin()` (ThemeBuilder, Mailbox, Competence and
  ProductGroup config, and any employee test that also touches settings).
  Tests: a `manager` gets 403 from `/settings`, `/theme-builder`,
  `/mailbox`; an `admin` gets through; a `manager` still reaches
  `/employees`; a guest still redirects to `/login`. Full PHP suite
  green.

- [x] 3. **One-time login code: table, service, endpoints, email.**
  Migration `login_codes` (`user_id` cascade, `code_hash`, `expires_at`,
  `consumed_at` nullable, `attempts` default 0, timestamps). `LoginCode`
  model. `LoginCodeService`: `request(email)` — void any live code for
  that user, create a fresh six-digit code (store the hash), send
  `LoginCodeMail`; silently does nothing for an unknown or inactive
  email. `verify(email, code)` — take the newest live code, check the
  hash, `attempts++`, void it after 5 failures, and on success set
  `consumed_at`, enforce `is_active`, `Auth::login`, regenerate the
  session. Request throttle: 5 per email per 15 minutes (RateLimiter).
  Routes `POST /login/code` and `POST /login/code/verify` with the
  `throttle` middleware. `LoginCodeMail` Mailable and a plain blade view.
  `auth.*` keys for the copy. Tests: request answers 200 for any email
  and creates a row only for an active account; a good code signs in; a
  wrong code counts and the fifth voids it; an expired code fails; a new
  request voids the old code; the sixth request in the window is
  throttled; an inactive account cannot verify. Full PHP suite green.

- [x] 4. **Login page: one screen with the code flow.** `Auth/Login.vue`
  — keep email and an optional password (drop `required` on the password
  field), keep **Sign in**, add **Email me a code** which posts
  `/login/code` and then reveals a six-digit code field and a **Verify**
  action posting `/login/code/verify`. Show the neutral
  "if that email matches an account…" line once a code is requested.
  `auth.*` keys for the button, the notice, the code label, and verify.
  Vitest: the form shows email, password, and both buttons; the password
  field is not `required`; requesting a code reveals the code field and
  the notice; **Verify** posts `{ email, code }` to
  `/login/code/verify`. Front-end suite and `npm run build` green.

- [ ] 5. **Admin `/users` page.** `UserController` `index` / `create` /
  `store` / `edit` / `update`, all behind `auth` + `admin`. `store`:
  `name`, `email` (unique on users), `role` (in `admin`,`manager`); the
  new row has a null password. `update`: same fields plus `is_active`;
  refuse to clear `is_active` or move `role` off `admin` when that row is
  the last active admin. `Users/Index.vue` and `Users/Form.vue` mirror
  the `Employees/` pages (a row per user with role and active state, a
  create and an edit form). Sidebar gains a **Users** item, admin only.
  `nav.users` and `users.*` i18n. Tests: guest and `manager` get 403;
  `admin` sees the list; create makes a password-less user; a duplicate
  email is rejected; edit changes role and active state; the last-admin
  guard blocks a deactivate and a demote; reactivating is allowed.
  Vitest: `Users/Index` renders rows; `Users/Form` submits create and
  edit; the sidebar shows **Users** for an admin and hides it for a
  manager. Full suites and `npm run build` green.

- [ ] 6. **Account page `/account`.** `AccountController` `show` /
  `updatePassword` / `linkEmployee`, behind `auth`. `updatePassword`:
  require `current_password` only when the user already has one; set the
  new password (min length rule); the model cast hashes it.
  `linkEmployee`: `manager` role, not already linked — link an
  `employees` row with the same email or create one from the user's name
  and email with the default weekly hours, then set `employee_id`.
  Routes `GET /account`, `PUT /account/password`, `POST /account/employee`.
  `Account/Show.vue` in `AppLayout`: a password card, and for an
  unlinked manager an "Add me as an employee" action. The sidebar gains
  a **My details** item (link to `/employees/{employee_id}/edit`) when
  `auth.user.employee_id` is set; the user menu gains an **Account**
  link. `account.*` and `nav.*` i18n. Tests: setting a first password
  needs no current password; changing one needs the correct current
  password; `linkEmployee` creates and links, links an existing row by
  email, is `manager`-only, and refuses when already linked. Vitest:
  `Account/Show` renders the password form; the "Add me as an employee"
  action shows for an unlinked manager and not for an admin or a linked
  user; the sidebar shows **My details** when linked. Full suites and
  `npm run build` green.

- [ ] 7. **Docs and full checks.** `doc/roadmap.md` — a **Phase 3.7 —
  Account management** row and section; note phase 2 still owns
  PostgreSQL, Entra ID, and employee token hardening. `doc/concept.md` —
  rewrite the Authentication section for the interim local accounts
  (roles, password or one-time code, employees still link-only, a
  manager may also be an employee). Set this `plan.md` header to `7/7`.
  Run Pint, `php artisan test`, `npm run test`, `npm run build`, and
  `php artisan migrate` on the dev database — all green.

## Not done / deferred

- Everything under the spec's "Non-goals" (Entra/OIDC, the PostgreSQL
  switch, employee accounts, reset links, queued mail).
- Manual browser pass — AGENTS.md leaves UI verification to the user.
