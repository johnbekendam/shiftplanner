# Auth Tabbed Card — Plan

Status: done — 2/2

Spec: `spec.md`.

- [x] 1. **Shared `Auth/AccessCard.vue` behind both routes.** New
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
