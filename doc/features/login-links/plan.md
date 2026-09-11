# Login Links — Plan

Status: done — 5/5

Spec: `spec.md`. Extends `doc/features/account-management/`.

- [x] 1. **Schema, model, and service: add `login_links` alongside
  `login_codes`.** The old code flow keeps working on `login_codes`
  until step 3 removes it, so this step is additive: a new migration,
  not an edit to `create_login_codes_table` — step 3 drops that table in
  its own migration once `LoginCodeController` and its routes are gone,
  the same pattern `add_shift_schedule_note_to_planning_settings` used
  for a change that landed after its table's own migration had shipped.
  New migration `create_login_links_table`: `id`, `user_id` (cascade),
  `token_hash`, `purpose` (string, `invite` or `login`), `expires_at`,
  `consumed_at` nullable, timestamps — no `attempts` column, a
  high-entropy token needs no guess counter. `LoginLink` model
  (`user()`, `scopeLive`). `User::loginLinks()` hasMany, alongside the
  existing `loginCodes()`. `LoginLinkService`: `sendInvite(User $user)`,
  `requestLogin(string $email)`, `resolve(string $token)` (looks up
  `hash('sha256', $token)`, `live()` only), `consumeLogin(LoginLink
  $link)`, `consumeInvite(LoginLink $link, string $password)`.
  `LoginLinkMail`, constructed with a URL and a purpose, subject and
  body branching on purpose through `auth.*` keys; new
  `emails/login-link.blade.php` with both purpose variants (leave
  `emails/login-code.blade.php` alone for now). Tests: `sendInvite`
  creates a 7-day-live row and emails a link; a second `sendInvite`
  voids the first; `requestLogin` is silent for an unknown or inactive
  email and throttled at 5 per 15 minutes; `resolve` finds a live row
  and misses an expired or consumed one; `consumeLogin` signs in and
  marks consumed; `consumeInvite` sets the password, signs in, and
  marks consumed; `is_active` blocks both consume paths. These are
  service-level tests only — nothing is wired to a route yet. Full PHP
  suite green, including the untouched `LoginCodeTest`.

- [x] 2. **Invite flow: create, resend, and the set-password landing
  page.** `UserController::store` calls `sendInvite` after creating the
  row. New `POST /users/{user}/resend-invite` (admin-only, `abort_if`
  when `has_password` is true) calling `sendInvite` again. `Users/
  Index.vue` gets a **Resend invite** row action shown only when
  `has_password` is false. Routes `GET /login/link/{token}` and
  `POST /login/link/{token}` (`LoginLinkController::show`, `confirm`).
  `show`: `resolve($token)`; a miss renders `Auth/LinkExpired.vue` with
  `purpose`-specific copy (login: link back to the login page; invite:
  text to contact an admin); a hit renders `Auth/SetPassword.vue`
  (invite) with the password/confirmation form and a line that a
  password is optional and a login link works instead. `confirm` for an
  invite-purpose link validates `password`/`password_confirmation`
  (`min:8|confirmed`), calls `consumeInvite`, redirects to `/`.
  `auth.*` and `users.*` i18n for the resend action, the set-password
  page, and the expired/used page. Tests: `store` sends an invite email
  and creates a `live()` `invite` row; resend is 403 for a manager, 404
  when the user already has a password, and voids the previous link
  when it succeeds; `GET /login/link/{token}` with a live invite token
  renders the set-password page, with an expired or consumed one renders
  the expired page; `POST` with a valid password signs the user in and
  marks the link consumed; a mismatched confirmation fails validation.
  Vitest: `Users/Index` shows **Resend invite** only for a passwordless
  row; `Auth/SetPassword` submits password and confirmation; `Auth/
  LinkExpired` renders the purpose-specific message. Full suites and
  `npm run build` green.

- [x] 3. **Login-purpose link: request from the login page, sign-in
  landing page.** Route `POST /login/link` (`LoginLinkController::
  request`, email only, `throttle:login-link` renamed from
  `throttle:login-code`) calls `requestLogin`. `show`/`confirm` extend
  to the `login` purpose: a live link renders `Auth/SignInLink.vue` (a
  **Sign in** button); its POST calls `consumeLogin` and redirects to
  `/`. `Auth/Login.vue`: remove the code section, `codeRequested`
  state, and the `/login/code*` calls entirely; replace **Email me a
  code** with **Email me a login link** posting `POST /login/link`,
  showing the existing neutral notice on success. Remove
  `LoginCodeController` and its two routes, `LoginCodeService`,
  `LoginCodeMail`, the `LoginCode` model, `emails/login-code.blade.php`,
  and `tests/Feature/Auth/LoginCodeTest.php`. New migration
  `drop_login_codes_table`; `User::loginCodes()` removed in favor of the
  `loginLinks()` added in step 1. Rename the `login-code` RateLimiter
  in `AppServiceProvider` to `login-link` and the key `LoginLinkService`
  uses to match. `auth.*` i18n: add the login-link request/notice/mail
  keys, remove
  `auth.field.code`/`auth.action.request_code`/
  `auth.action.verify_code`/`auth.code_sent`/`auth.code_invalid`.
  Tests: `POST /login/link` is silent and throttled like the old code
  request; `GET`/`POST /login/link/{token}` for a `login`-purpose link
  renders the sign-in page and signs in on confirm; an inactive user's
  link is refused at request and at confirm. Vitest: `Auth/Login` has
  no code field and no password-required attribute, shows **Email me a
  login link**, and posts `{ email }` to `/login/link` on click. Full
  suites and `npm run build` green.

- [x] 4. **Docs and full checks.** `doc/features/account-management/
  spec.md` gets a short note pointing at `login-links` for the
  passwordless mechanism, so a future reader does not follow the
  superseded code-flow description. `doc/roadmap.md` and
  `doc/concept.md` — update the account-management summary to describe
  the link flow (invite, forgot-password, passwordless) instead of the
  code. Set this `plan.md` header to `4/4`. Run Pint, `php artisan
  test`, `npm run test`, `npm run build`, and `php artisan migrate` on
  the dev database — all green.

- [x] 5. **Continue without password on the set-password page.** The
  page's copy pointed at the login page's link action but gave no way to
  act on it there, and the form's `required` password fields made the
  page unskippable. Route `POST /login/link/{token}/skip`
  (`LoginLinkController::skip`): resolve the token, `abort_unless`
  purpose is `invite`, call `consumeLogin($link)` (already
  purpose-agnostic — sign in and consume, no password touched),
  redirect to `/`. `Auth/SetPassword.vue`: drop the login-page mention
  from the intro; add a **Continue without password** `ButtonSecondary`
  next to the submit button, posting to the skip route. `auth.setpw.*`
  i18n: replace the intro line, add `auth.setpw.skip`. Tests: skip on a
  live invite link signs in, consumes the link, and sets no password;
  skip on an expired/consumed link is a 404; skip on a login-purpose
  link is a 404 (only invite links expose this). Vitest: `Auth/
  SetPassword` shows **Continue without password** and posts to
  `/login/link/{token}/skip` on click, without touching the password
  fields. Full suites and `npm run build` green.

## Not done / deferred

- Everything under the spec's Non-goals (second factor, Entra/OIDC,
  Postgres switch, employee login accounts, an invite-list UI).
- Manual browser pass — AGENTS.md leaves UI verification to the user.
