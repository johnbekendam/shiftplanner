# Login Links — Spec

Replaces the account-management login code with an emailed link. Extends
`doc/features/account-management/`.

## Problem

`account-management` shipped `/users` create with no password and no
email — a new user has to already know to visit the login page and
request a one-time code. It also shipped a 6-digit code as the only
passwordless path, with no way to set a password other than being
already signed in. The app needs: an invite email when an admin creates a
user, a page reached from that email where the person sets a password,
and a login-page action that emails a link covering both "I forgot my
password" and "email me a link instead of typing one."

## Solution

### One link mechanism, two purposes

A single token table replaces `login_codes`. Every row has a `purpose`:

- **`invite`** — sent when an admin creates a user, and by a
  **Resend invite** action. The link lands on a **set your password**
  page. Expires in 7 days.
- **`login`** — sent by the login page's **Email me a login link**
  action, covering both "forgot my password" and "sign me in without
  one." The link signs the user straight in. Expires in 15 minutes.

Both purposes are single-use, hashed at rest, and revoke any unconsumed
link of the same purpose for that user when a new one is requested — the
same pattern `login_codes` used.

### Schema — `login_links` replaces `login_codes`

A new migration creates `login_links`; a later migration drops
`login_codes` once nothing reads it, the same pattern the codebase
already uses for a schema change that lands after the table's own
migration has shipped (for example `add_shift_schedule_note_to_
planning_settings`). No data to preserve either way.

- `id`
- `user_id` — cascade delete
- `token_hash` — `hash('sha256', $token)`, not `Hash::make`. A login
  link, unlike a code, carries no email alongside it — the token itself
  must resolve to a row with one indexed lookup. A code's bcrypt hash
  works because `LoginCodeService::verify` already knows the user from
  the submitted email; a link has no such anchor.
- `purpose` — `invite` or `login`
- `expires_at`
- `consumed_at` — nullable
- timestamps

`LoginLink` model replaces `LoginCode`: `user()` belongsTo, `scopeLive`
(`consumed_at` null and `expires_at` in the future) unchanged in shape.

### `LoginLinkService` replaces `LoginCodeService`

- `sendInvite(User $user)` — void any live `invite` link for the user,
  create one (token, hash, 7-day expiry), email it. Called from
  `UserController::store` and the new resend action.
- `requestLogin(string $email)` — same silence rules as today's
  `request()`: nothing for an unknown or inactive email, throttled 5 per
  email per 15 minutes. Void any live `login` link, create one (15-minute
  expiry), email it.
- `resolve(string $token)` — look up by `hash('sha256', $token)`,
  `live()` only, eager-load `user`.
- `consumeLogin(LoginLink $link)` — mark consumed, `Auth::login`,
  regenerate the session. Called only from a `login`-purpose confirm
  POST.
- `consumeInvite(LoginLink $link, string $password)` — set the user's
  password, mark consumed, `Auth::login`, regenerate the session. Called
  only from an `invite`-purpose password POST.
- `is_active` enforced on every send and every consume, as today.

One mailable, `LoginLinkMail`, takes the URL and the purpose; the
subject and body copy branch on purpose through `auth.*` i18n keys.
Sent synchronously — the `log` mailer locally — matching
`account-management`'s no-queued-mail decision.

### Routes and the link-landing page

```
POST /login/link              — request a login-purpose link (email only)
GET  /login/link/{token}      — show: renders the landing page for a
                                 live link, or an "expired/used" view
POST /login/link/{token}      — confirm: consumes the link
```

The GET never consumes — only a POST the person triggers by clicking a
button does. This is what stops an email client's link-scanner from
silently burning the token before the real person opens it; no separate
confirmation step is needed beyond the page's own button already being a
POST.

- `login` purpose: the page shows a **Sign in** button. Its POST calls
  `consumeLogin` and redirects to `/`.
- `invite` purpose: the page shows the password-set form (password +
  confirmation) and a **Continue without password** action. Its POST
  (`/login/link/{token}`, with `password` and `password_confirmation`)
  calls `consumeInvite` and redirects to `/`. **Continue without
  password** posts to `/login/link/{token}/skip`, which signs the user
  in and consumes the link immediately — no password set, no email sent.
  Next time, the person uses the login page's **Email me a login link**.
- An expired or already-consumed link renders a short explanation.
  `login` purpose: a link back to the login page to request a new one.
  `invite` purpose: text to contact an admin — only an admin resends an
  invite (see below).

`throttle:login-link` (renamed from `throttle:login-code`) guards
`POST /login/link`.

### Admin `/users` — Resend invite

A **Resend invite** row action, shown only when `has_password` is false
(the column `/users` already computes). Calls `sendInvite` again,
voiding any unconsumed invite link first. No new route parameters beyond
the user; `POST /users/{user}/resend-invite`, admin-only like the rest of
`/users`.

`UserController::store` calls `LoginLinkService::sendInvite($user)` right
after creating the row.

### Login page

`Auth/Login.vue`: drop the code section entirely — no code field, no
`codeRequested` state, no `/login/code*` calls. Replace
**Email me a code** with **Email me a login link**, posting
`POST /login/link` with the email field. On success, show the same
neutral notice pattern the code flow used ("If that email matches an
account, a link is on its way.") — nothing else changes; the password
field stays optional and **Sign in** stays as is.

### i18n

`auth.*` gains link-flow keys (mail subject per purpose, the landing-page
copy, the notice text, button labels) and loses the code-only keys
(`auth.field.code`, `auth.action.request_code`,
`auth.action.verify_code`, `auth.code_sent`, `auth.code_invalid`).
`users.*` gains a resend-invite action label and flash message.

## Key decisions

- **One link mechanism, not link-plus-code.** The invite already needs a
  link to reach a set-password page. Keeping the 6-digit code alongside
  it would mean two passwordless systems to build, style, and test; the
  link replaces the code everywhere.
- **Login-purpose links never force a password step.** A person who
  chose "no password, always a link" should never be routed to set one.
  Forgot-password and passwordless-login collapse into the same login
  action for this reason — a login link only ever signs someone in. A
  person can still add a password anytime from `/account`.
- **Invite links can be skipped in place, not just avoided.** The
  set-password page's **Continue without password** signs the person in
  immediately, using the same link they already opened — no separate
  email, no navigating to the login page first. It reuses
  `consumeLogin`, which only ever signs in and consumes; it does not
  care which purpose issued the link.
- **Different expiries by purpose.** An invite email may sit unread for
  days. A login link is requested and clicked in the same session. 7
  days versus 15 minutes matches that.
- **Invite resend is admin-only, not self-serve.** A login-purpose link
  cannot route to set-password (previous decision), so a stuck invite
  has no self-serve recovery path. The admin who created the account is
  already the one managing `/users`.
- **SHA-256 lookup hash, not bcrypt.** A login code is verified against a
  user the request already identifies by email. A link has to identify
  the user from the token alone, and a salted bcrypt hash cannot do that
  by lookup. The token itself carries the entropy (`Str::random(40)`), so
  a fast, deterministic hash for storage is the standard, safe choice
  here — the same tradeoff Laravel's own password-broker tokens make.
- **Consume only on POST.** Corporate email scanners pre-fetch GET links.
  Making the GET side-effect-free and requiring the person's own click to
  POST is what protects a single-use token without adding a separate
  "are you sure" step.
- **One token table, a purpose column.** Two tables would duplicate the
  hash/expiry/consume machinery for no gain; the two purposes differ only
  in TTL and what the landing page's POST does.

## Non-goals

- "Remember this device," authenticator apps, or any second factor.
- Entra ID / OIDC and the PostgreSQL switch — both stay in roadmap phase
  2, unaffected by this change.
- Employee login accounts — unrelated to this feature, still out of
  scope per `account-management`.
- Rate-limiting the token-lookup routes (`GET`/`POST /login/link/{token}`)
  beyond what a high-entropy, single-use, short-lived token already
  provides.
- A visible list of outstanding invites, or bulk resend.
