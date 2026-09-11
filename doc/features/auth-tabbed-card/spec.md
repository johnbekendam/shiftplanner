# Auth Tabbed Card — Spec

This feature merges the login page and the employee self-signup page
into one card with a tabbed header.

## Problem

`/login` and `/signup` are two disconnected pages. `/login` is for
users: email and password, or an emailed sign-in link. `/signup` is for
employees who want the link to their personal page. An employee reaches
`/signup` only through a small text link under the login form, and
`/signup` has no link back to `/login`. The two audiences are not
clearly separated, and there is no visible way to request a personal
page link again after losing it.

## Solution

One Vue page, `Auth/AccessCard.vue`, replaces `Auth/Login.vue` and
`Signup.vue`. It renders through `CenteredLayout` with a fixed title,
`auth.page_title` = "ShiftPlanner", and a two-tab header above the form
area:

- **Sign in** — today's `Login.vue` form, unchanged: email and password
  fields, a **Sign in** button, and the **Email me a login link** action
  (`POST /login/link`), with its own success notice.
- **Get my link** — today's `Signup.vue` form, unchanged: first name,
  last name, and email fields, a submit button, and its own confirmation
  message.

Both routes keep working. Each route picks which tab starts active:

- `GET /login` (`LoginController::showForm`) renders the card with
  `activeTab: 'signin'`.
- `GET /signup` (`SignupController::show`) renders the same card with
  `activeTab: 'personal-link'`.

A tab switch is a client-side toggle, a `ref` in `AccessCard.vue`. It
does not navigate, change the URL, or reload the page. Only the active
tab's form is mounted (`v-if`), so each tab keeps its own Inertia
`useForm` state, validation errors, and success notice separate from
the other tab. Leaving a tab and returning to it resets that tab, the
same way loading either page fresh does today.

This removes the old cross-links:

- `signup.login_link`, the "Request your personal link" text link under
  the login form that pointed to `/signup`, is gone. The tab replaces
  it.
- No link from `/signup` back to `/login` existed before. None is
  added, for the same reason.

`POST /login`, `POST /login/link`, and `POST /signup` do not change:
same routes, same controllers, same validation, same throttling.

## Key decisions

- **Two routes, one component.** `/login` and `/signup` both stay live
  and bookmarkable, so nothing that already links to `/signup` (mailbox
  templates, docs) breaks. The `activeTab` prop picks which tab opens.
  We considered one canonical URL with a redirect and rejected it: it
  adds a query-param pattern the app does not otherwise use, and breaks
  existing `/signup` links until they follow the redirect.
- **Fixed card title, not tab-dependent.** "ShiftPlanner" sits above the
  tab bar and never changes, so a tab switch does not move the heading.
  Each tab's own intro text, the signup tab keeps its existing intro
  paragraph, carries the tab-specific context instead.
- **Pure UI merge, no service or route changes.** Both flows,
  `login-links` and `employee-self-signup`, already work correctly.
  This feature only changes how they are presented. `LoginController`,
  `LoginLinkController`, `SignupController`, `SelfSignupService`, and
  `EmployeePersonalLinkService` stay untouched.
- **Independent form state per tab.** `v-if`, not `v-show`, mounts only
  the active tab's form. This stops the two `useForm` instances from
  sharing one flash message, and matches today's fresh-page-load
  behavior when an employee returns to a tab.

## Non-goals

- Any change to the sign-in, login-link, or personal-link-request
  logic, validation, throttling, or emails.
- A shared or unified form, for example one email field feeding both
  flows. The two tabs stay functionally separate forms.
- Employee authentication or the personal-page token mechanism itself.
  Out of scope, and still prototype-only per `PersonalPageController`.
