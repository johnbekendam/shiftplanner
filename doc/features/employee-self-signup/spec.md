# Employee Self-Signup — Spec

A public page where a person requests the link to their own personal
page. The request creates the employee if none exists yet, then sends the
link.

`Signup.vue`, named in this document, is now the "Get my link" tab of
`Auth/AccessCard.vue`. See `doc/features/auth-tabbed-card/spec.md` for
the current page layout.

## Problem

Today an admin creates every employee by hand, or by CSV import, and
sends the personal-page link from the mailbox. A new employee cannot
start the process themselves. The team wants a page that a person reaches
without a login, fills in with their name and email, and gets their
personal link by mail — whether or not an admin has already added them.

## Solution

### Public page — `GET /signup`

- Renders through `CenteredLayout`. No auth, no nav entry.
- Fields, all required: **First name**, **Last name**, **Email**.
- The login card gets a link "Request your personal link" to `/signup`.

### Request — `POST /signup`

Validation: `first_name` and `last_name` `required|string|max:255`;
`email` `required|email|max:255`.

Rate limits:

- Route middleware `throttle:5,1` (per IP).
- An application limit of one accepted request per email per 10 minutes,
  keyed on the lower-cased email through Laravel's `RateLimiter`.

Steps on an accepted request:

1. Find an employee whose email matches, case-insensitive
   (`whereRaw('lower(email) = ?', [Str::lower($email)])`).
2. No match: create the employee now — `first_name`, `last_name`, `email`
   as entered (email trimmed), `business_line_id` null, `weekly_hours`
   left at the column default. No review flag.
3. Match: change nothing on the row. The stored name stays as the admin
   set it.
4. Either case: call `EmployeePersonalLinkService::linkFor($employee)` so
   the token exists, then send the link (see below).

When the per-email limit is already spent, skip step 4 but still return
the same confirmation.

### Sending — the mailbox pipeline

Reuse the `personal_page_link` path from `features/mailbox/`:

- `MessageTemplate::forType(MessageType::PersonalPageLink)` gives the
  stored subject and body.
- `PersonalLinkMessage::forEmployee($employee)` resolves `:name` and
  `:link`; `apply()` fills the template.
- `MessageComposer::render(...)` builds the HTML fragment; a
  `ComposedMessage` with no reply-to address wraps it in the branded
  layout.
- Create one `Message`: `type` `personal_page_link`, `status` `outbox`,
  `user_id` null, `recipient_email` the employee's email,
  `recipient_name` the employee's name, `body` and `body_html` baked from
  the resolved template.
- Dispatch `SendMailboxMessage` for that row, as the mailbox `store()`
  does for a queued send.

The message shows in the admin **Sent** tab as the record of the
request.

### Schema — `messages.user_id` nullable

`messages.user_id` is `NOT NULL` today. A self-signup send has no
composing admin. Fold a change into the `create_messages_table`
migration to make the column nullable (`->nullable()`), keeping the
foreign key and `cascadeOnDelete`.

The mailbox list already reads `$message->user?->name`. When `user_id` is
null, the **Composed by** column shows the text for "Self-signup"
(`mailbox.compose.self_signup`).

### Response

One outcome for every accepted request: the form is replaced by a
confirmation panel with the text
"If that address is valid, we sent your personal link. Check your
email." A new email and an email that already had an employee give the
same result, so the page tells nobody who is registered.

### i18n

New `signup.*` keys: page title, card title, field labels, submit label,
the confirmation text, and the login-card link label. One `mailbox.*`
key: `mailbox.compose.self_signup` for the Composed-by column.

## Key decisions

- **Always on, no admin switch.** The prototype adds a toggle only for a
  concrete need (as `allow_employee_changes` had one). None exists here.
  An admin who wants the page closed changes the route.
- **Reuse the mailbox pipeline, not a second mailable.** One place
  renders a personal-link email, one template holds the copy, and every
  send — admin or self — lands in the Sent tab. The cost is a public
  action that writes a row into the shared mailbox list; that row is the
  wanted audit trail.
- **`user_id` nullable, not a system user.** A placeholder admin user
  would credit a person who did not send the message and would break if
  that user is absent. Null is the honest value; the list already
  handles it.
- **Case-insensitive email match.** Stops a second employee row that
  differs from an existing one only by letter case. New emails are still
  stored as typed.
- **No name reconciliation on a match.** The brief says create or send,
  not update. The admin-maintained name stays authoritative, and a
  mistyped resubmit cannot overwrite good data.
- **Same confirmation for new and existing.** A differing message would
  let anyone probe which emails belong to employees. The team is small,
  but the cost of hiding it is one shared string.
- **No review flag on a self-created employee.** A blank business line
  and the default weekly hours already mark the row as unfinished in the
  employee list. A flag column and its UI are a later feature if the need
  appears.
- **Throttle by IP and by email.** The IP limit stops a burst; the
  per-email limit stops repeat requests and double submits without a
  CAPTCHA. A CAPTCHA needs a dependency the prototype does not carry.

## Non-goals

- An admin on/off toggle for the page.
- A CAPTCHA or any external anti-bot service.
- Email address verification (double opt-in). A typo sends the link
  nowhere; an admin fixes the address later.
- A "self-registered" flag, badge, or list filter.
- Updating an existing employee's name, business line, or hours from the
  form.
- Token hardening (hashing, expiry, revocation) — still roadmap phase 2.
- A nav entry or any authenticated entry point beyond the login-card
  link.
