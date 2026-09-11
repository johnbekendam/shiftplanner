# Auth Tabbed Card — Spec

This feature adds a tabbed header to the login page, so an employee can
re-request their personal page link from the same card a user signs in
on. `/signup`, the standalone new-employee signup page, stays a
separate page.

## Revision note

The first version of this feature moved the full signup form (first
name, last name, and email, which creates a new employee) into the
card's second tab, and retired `/signup` as a route into it. That was a
misunderstanding of the request: signing up a new employee and
re-requesting an existing employee's link are different tasks with
different fields. This revision splits them back apart: `/signup` is
its own page again, unchanged from before this feature, and the card's
second tab is a new, smaller "resend my link" form.

## Problem

`/login` is for users: email and password, or an emailed sign-in link.
An employee who already has a personal page but lost the link has no
way to get it again except finding the admin, or guessing `/signup`
exists and using it, which asks for their name again and reads like
new-employee registration. The two logged-in-user and
already-an-employee audiences are not clearly separated on `/login`.

## Solution

`Auth/AccessCard.vue` replaces `Auth/Login.vue`. It renders through
`CenteredLayout`, with a two-tab header (no separate title text, the
tabs sit directly in the card's header):

- **Sign in** — the existing sign-in form, unchanged: email and
  password fields, a **Sign in** button, and the **Email me a login
  link** action (`POST /login/link`), with its own success notice.
- **Get my link** — a new, email-only form: one email field, a submit
  button, and copy that says plainly what it does, "Already have a
  personal page? Enter your email and we will send you the link
  again."
  It never creates an employee. A small link under the form, "New
  employee? Request access", points to `/signup` for anyone who needs
  that instead.

`GET /login` (`LoginController::showForm`) renders the card. `/signup`
is no longer connected to `AccessCard` at all: `SignupController::show`
renders the original `Signup.vue` page again, first name, last name,
and email, creating the employee when the email is unknown, exactly as
before this feature started.

A tab switch on the card is a client-side toggle, a `ref` in
`AccessCard.vue`. It does not navigate, change the URL, or reload the
page. Each tab keeps its own Inertia `useForm` state, validation
errors, and success notice. Leaving a tab and returning to it resets
that tab.

### Backend for "Get my link"

New route `POST /personal-link` (`PersonalLinkController::request`),
throttled the same as `/signup` (`throttle:5,1`). It validates only
`email` (`required|email|max:255`) and calls a new
`SelfSignupService::resend(string $email)`:

- Match an employee by email, case-insensitive, the same lookup
  `register()` already uses.
- No match: do nothing. No employee is created.
- Match: send the personal-page link through the same mailbox pipeline
  `register()` uses today (`PersonalPageLink` template, `Message` row,
  `SendMailboxMessage`).
- Same per-email rate limit as `register()` (`self-signup:` prefix
  reused, since both send the same message type to the same address
  and should share one throttle window), so a person cannot use `Get my
  link` and `/signup` to double the send rate.

The response is the same neutral confirmation either way, so the page
never reveals whether an email is registered: `Get my link` reuses
`auth.link_sent`-style copy, not `signup.confirmation`, since it is a
distinct form on a distinct page.

## Key decisions

- **`/signup` reverts to its own page.** Creating an employee and
  resending a link are different actions with different required
  fields (name is meaningless for a resend). Folding them into one tab
  made the resend form ask for information it does not need, and made
  the tab's purpose ambiguous. Splitting them back apart removes that
  ambiguity, at the cost of one more page in the app, which is the
  right trade-off here.
- **A new `resend()` method, not a shared one.** `register()` creates an
  employee on a miss. `resend()` must not. Branching one method on a
  "should I create" flag would make `register()` harder to read for no
  benefit, since the two call sites already know which behavior they
  want.
- **Shared rate-limit key.** `Get my link` and `/signup` both end in the
  same send-the-link action. A shared `self-signup:` key keeps someone
  from working around the per-email limit by alternating between the
  two forms.
- **Fixed card header, no title text.** Carried over from the first
  version: the tabs sit directly in the card's header slot, with no
  "ShiftPlanner" heading above them.
- **A link from the card to `/signup`, not the reverse.** Someone on the
  card's `Get my link` tab who turns out to have no account yet needs a
  way forward, so a small link points at `/signup`. `/signup` itself
  does not need a link back, since it is not part of the sign-in flow.
- **Independent form state per tab.** `v-if`, not `v-show`, mounts only
  the active tab's form. This stops the sign-in and resend forms from
  sharing one notice, and resets a tab when it goes out of view and
  back.

## Non-goals

- Any change to the sign-in or login-link logic, validation,
  throttling, or emails.
- Any change to `/signup`'s own behavior. It is restored, not redesigned.
- Employee authentication or the personal-page token mechanism itself.
  Out of scope, and still prototype-only per `PersonalPageController`.
