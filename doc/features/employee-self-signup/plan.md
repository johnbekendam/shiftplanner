# Employee Self-Signup — Plan

Status: done — 6/6

- [x] **1. `messages.user_id` nullable.** Fold `->nullable()` into the
  `create_messages_table` migration, keeping the FK and
  `cascadeOnDelete`. Add `mailbox.compose.self_signup` to `en.json` and
  show it in the mailbox list's Composed-by column when `user_id` is
  null. Test: a `Message` saves with `user_id` null; the mailbox index
  payload returns the self-signup label for that row.

- [x] **2. Signup service — create-or-find plus send.** A
  `SelfSignupService` (or a method group) that takes first name, last
  name, email and: matches an employee by lower-cased email, creates one
  when none matches, ensures the personal link, and creates the
  `outbox` `Message` from the `personal_page_link` template with
  `user_id` null, then dispatches `SendMailboxMessage`. Tests
  (`Mail::fake`/`Bus::fake`): new email creates exactly one employee and
  one queued message; known email creates no employee and one message;
  a name that differs from the stored one does not change the row;
  `:link` in the sent body is the employee's personal URL.

- [x] **3. `POST /signup` route and controller.** Public route with
  `throttle:5,1`. Validate the three fields. Apply the per-email
  `RateLimiter` limit (1 per 10 minutes on the lower-cased email); when
  spent, skip the service call. Always redirect back with the same
  `success` flash. Tests: valid post hits the service and flashes
  success; invalid post returns validation errors; a second post with
  the same email inside the window flashes success but runs no send;
  the route rejects a 7th call in a minute.

- [x] **4. Signup page — `GET /signup`.** Public route rendering a
  `Signup` Inertia page in `CenteredLayout`: `TextInput` first/last
  name, `EmailInput`, `ButtonPrimary`. On the `success` flash, replace
  the form with the confirmation panel. All copy from `signup.*` keys.
  Tests: the route renders the `Signup` component for a guest;
  `signup.*` keys resolve.

- [x] **5. Login-card link.** Add "Request your personal link" to
  `Login.vue`, pointing at `/signup`, using a `signup.*` key. Test:
  the login page renders and the `signup.login_link` key exists.

- [x] **6. Full-suite pass and docs.** Run `php artisan test` and
  `npm run test`; fix any regression. Mark the roadmap phase 2 note
  and this plan done.
