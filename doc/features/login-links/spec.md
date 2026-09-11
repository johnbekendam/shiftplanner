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
- `consumeWithoutPassword(LoginLink $link)` — mark consumed,
  `Auth::login`, regenerate the session. Purpose-agnostic: called from
  the **Continue without password** action on any link's landing page.
- `consumeWithPassword(LoginLink $link, string $password)` — set the
  user's password, mark consumed, `Auth::login`, regenerate the
  session. Purpose-agnostic: called from the password form on any
  link's landing page.
- `is_active` enforced on every send and every consume, as today.

### Mail — the mailbox pipeline, not a second mailable

Two new `MessageType` cases, `UserInvite` and `UserLoginLink`, each with
its own `MessageTemplate` (subject + Markdown body, `:name` / `:link`
placeholders, a `:button[Label](:link)` call to action) — the same
mechanism `personal_page_link` uses, so the invite and sign-in emails
get the branded HTML layout every other ShiftPlanner email uses instead
of a plain-text one-off. `needsEmployees()` is `false` for both: they
carry no employee concept, and this also excludes them from the
mailbox's Compose tab — nothing manually composes an account link, only
`LoginLinkService` issues one.

`LoginLinkService` resolves the template, renders it through
`MessageComposer`, and sends the resulting `ComposedMessage` — the same
mailable `MailboxController` and self-signup send — **synchronously**
(`Mail::to(...)->send(...)`, not `SendMailboxMessage::dispatch()`,
which queues on this app's database queue driver). A `Message` row is
created alongside it with `status: 'sent'` and `sent_at: now()`, so it
appears in the mailbox's **Sent** tab immediately — there is no
`outbox` interval, because delivery already happened by the time the
row exists. An invite's `Message.user_id` is the admin who created (or
resent) the invite, shown as normal on the **Composed by** column; a
login-purpose link's is null, like a self-signup send, since no admin
composed it.

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

Every live link, invite or login-purpose, lands on the same
set-password page — the person decides whether to set a password every
time, not just on the invite:

- The page shows the password-set form (password + confirmation) and a
  **Continue without password** action. Its POST (`/login/link/{token}`,
  with `password` and `password_confirmation`) calls
  `consumeWithPassword` and redirects to `/`.
- **Continue without password** posts to `/login/link/{token}/skip`,
  which calls `consumeWithoutPassword` — signs the user in and consumes
  the link immediately, no password touched, no email sent.
- An expired or already-consumed link renders a short explanation.
  `login` purpose: a link back to the login page to request a new one.
  `invite` purpose: text to contact an admin — only an admin resends an
  invite (see below).

`GET /login/link/{token}` no longer branches on purpose to choose a
page (`Auth/SetPassword` vs. the now-removed `Auth/SignInLink`) — every
live link renders `Auth/SetPassword`.

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
- **Every link lands on the set-password page — superseded.** The first
  version routed only invite-purpose links there, reasoning that a
  person who chose "no password, always a link" should never be routed
  to set one. Revised: every link, invite or login-purpose, lands on the
  same page, so the choice — set a password, or continue without one —
  is offered every time, not fixed at invite time. `Auth/SignInLink.vue`
  (the login-purpose-only "just sign in" page) is removed; there is one
  landing page for every link.
- **Consume methods are named by what they do, not which purpose calls
  them.** `consumeLogin`/`consumeInvite` renamed to
  `consumeWithoutPassword`/`consumeWithPassword` once both ran from
  either purpose — the old names implied a purpose binding that no
  longer exists.
- **Continue without password skips in place.** It signs the person in
  immediately, using the same link they already opened — no separate
  email, no navigating to the login page first. `consumeWithoutPassword`
  only ever signs in and consumes; it does not care which purpose issued
  the link.
- **Different expiries by purpose.** An invite email may sit unread for
  days. A login link is requested and clicked in the same session. 7
  days versus 15 minutes matches that.
- **Invite resend is admin-only, not self-serve.** The admin who created
  the account is already the one managing `/users`. (Since every link now
  lands on the set-password page, a person whose invite expired could
  alternatively request a login link from the login page and set a
  password there — the admin action stays as the direct, no-guessing
  path, not the only one.)
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
- **The mailbox pipeline, not a bespoke mailable.** An early version sent
  a plain-text `LoginLinkMail` directly and left no record anywhere. That
  meant no branded styling and no way for an admin to see or audit that
  an invite went out. Two new `MessageType` cases reuse the exact
  machinery `personal_page_link` already proved out — template, branded
  HTML, a `Message` row — while `needsEmployees() === false` keeps them
  out of the Compose tab, since nothing about an account link needs
  employee selection.
- **Still synchronous, just recorded as sent.** The no-queued-mail
  decision above still holds — `SendMailboxMessage::dispatch()` would
  queue on this app's database driver and could sit unsent with no
  worker running. Sending inline and writing the `Message` row with
  `status: 'sent'` gets both branded delivery and outbox visibility
  without taking on a queue dependency.

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
