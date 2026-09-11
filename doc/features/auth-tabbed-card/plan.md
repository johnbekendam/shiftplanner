# Auth Tabbed Card — Plan

Status: done — 3/3

Spec: `spec.md`. See the spec's Revision note for why step 3 exists.

- [x] 1. **Shared `Auth/AccessCard.vue` behind both routes.** (superseded
  by step 3 — `/signup` no longer routes into this component.) New
  `resources/js/pages/Auth/AccessCard.vue`: `CenteredLayout`, fixed
  title `auth.page_title` ("ShiftPlanner"), the existing `ui/Tabs.vue`
  component with two entries (`signin` "Sign in", `personal-link` "Get
  my link"), an `activeTab` prop that sets the initial `ref`. Move
  `Login.vue`'s template and script into the `signin` branch unchanged:
  the email and password form, the **Email me a login link** action,
  and its notice. Move `Signup.vue`'s template and script into the
  `personal-link` branch unchanged: the name and email form, and its
  confirmation message. Each branch is `v-if`-gated, so only the active
  tab's `useForm` mounts. Delete `Login.vue` and `Signup.vue`. Update
  `LoginController::showForm` to call
  `Inertia::render('Auth/AccessCard', ['activeTab' => 'signin'])`.
  Update `SignupController::show` to call
  `Inertia::render('Auth/AccessCard', ['activeTab' => 'personal-link'])`.
  Remove `signup.login_link` from `en.json`. Confirm no other reference
  to the old cross-link remains.

  Tests: update `tests/Feature/Auth/LoginTest.php` and
  `tests/Feature/SelfSignupTest.php` to assert
  `component('Auth/AccessCard')` with the matching `activeTab` prop,
  in place of `component('Login')` and `component('Signup')`. Keep the
  existing `POST /login`, `POST /login/link`, and `POST /signup`
  assertions as they are. Rewrite `tests/js/AuthLogin.test.js` and
  `tests/js/Signup.test.js` against `AccessCard.vue`. Each test mounts
  the card with the matching `activeTab`, asserts only that tab's
  fields render, and keeps every existing form-submission assertion
  (fields posted, success notice shown) for both tabs. Add one new
  test: from the `signin` tab, a click on **Get my link** shows the
  signup fields and hides the sign-in fields, and the reverse also
  works. Run the full PHP and JS suites and `npm run build`. All must
  pass.

- [x] 2. **Docs.** Add a one-line note to
  `doc/features/login-links/spec.md` and
  `doc/features/employee-self-signup/spec.md`, each pointing at
  `auth-tabbed-card` for the current page layout. This stops a future
  reader from following the old two-page description. Set this
  `plan.md` header to `2/2`.

- [x] 3. **Revision: split `/signup` back out, make `Get my link` an
  email-only resend.** Restore `resources/js/pages/Signup.vue` (the
  original first name, last name, email form with its confirmation
  message) from git history, unchanged. Update `SignupController::show`
  to render `Inertia::render('Signup')` again, not `Auth/AccessCard`.
  Drop `AccessCard`'s `activeTab` prop entirely, since only `/login`
  renders it now. Add `SelfSignupService::resend(string $email): void`:
  reuse `register()`'s case-insensitive lookup, but return silently on
  a miss instead of creating an employee. Both `resend()` and
  `register()` call `RateLimiter::hit`/`tooManyAttempts` on the same
  `self-signup:` key prefix, so the two forms share one per-email
  throttle window.

  New route `POST /personal-link` (`throttle:5,1`, matching `/signup`),
  new `PersonalLinkController::request`: validates `email`
  (`required|email|max:255`), calls `SelfSignupService::resend($email)`,
  redirects back with the same neutral confirmation text `/signup` uses
  today, `signup.confirmation`, since both forms make the same
  "we sent it if it matched" promise.

  In `AccessCard.vue`, replace the `personal-link` branch's form: one
  email field, a submit button labeled `auth.action.request_personal_link`
  ("Send me the link"), intro copy `auth.personal_link.intro` ("Already
  have a personal page? Enter your email and we will send you the link
  again."), and its own success notice `auth.personal_link.sent`, shown
  the same way the `signin` tab's `auth.link_sent` notice is. Below the
  form, a link `auth.personal_link.new_employee` ("New employee? Request
  access") to `/signup`. Remove `signup.field.first_name`,
  `signup.field.last_name` from the `personal-link` branch's use (the
  keys themselves stay, `Signup.vue` still uses them).

  Tests: `SelfSignupServiceTest` (new or extended) covers `resend()`: a
  known email sends the existing link and creates a `Message`, an
  unknown email sends nothing and creates no employee, a second call
  inside the throttle window is silent, and a prior `register()` call
  for the same email exhausts `resend()`'s window too (shared key).
  Feature test for `POST /personal-link`: known email sends, unknown
  email is silent, both return the same redirect and flash text,
  throttled at 5 per minute. `tests/Feature/SelfSignupTest.php`'s
  `signup page renders for a guest` reverts to asserting
  `component('Signup')`. `tests/Feature/Auth/LoginTest.php` drops the
  `activeTab` assertion (or keeps `signin` only, since it is now the
  only value). Vitest: restore `tests/js/Signup.test.js` against the
  standalone `Signup.vue` (its original pre-step-1 content). Rewrite
  `tests/js/AuthLogin.test.js`'s `personal-link`-tab coverage: shows
  only an email field and the resend button, posts `{ email }` to
  `/personal-link`, shows the intro and notice text, and the
  `New employee?` link points to `/signup`. Full PHP and JS suites,
  `npm run build`, green. Set this `plan.md` header to `3/3`.
