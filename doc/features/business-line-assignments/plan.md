# Business Line Assignments — Plan

Status: done — 11/11

## Steps

- [x] **Migrations + model relations.** `users.business_line_id`
      (nullable FK → `business_lines`, `nullOnDelete()`) and
      `business_lines.responsible_user_id` (nullable FK → `users`,
      `nullOnDelete()`). `User::businessLine(): BelongsTo`,
      `BusinessLine::responsibleUser(): BelongsTo`. Added both columns to
      their model's `$fillable`; added `responsible_user_id` to
      `BusinessLine::toPayload()`.
- [x] **`UserController` — business line field.** `store`/`update`
      validation gains `business_line_id` (`nullable`,
      `exists:business_lines,id`); `edit`'s `only([...])` gains it;
      `create`/`edit` pass a `businessLines` prop (`id`, `abbreviation`).
      `update` clears `responsible_user_id` on any business line pointing
      at this user when `business_line_id` changes (compared against
      `$data['business_line_id'] ?? null`, since Laravel's `validate()`
      omits a `nullable`-without-`sometimes` key entirely when the
      request doesn't send it). Feature tests added to
      `tests/Feature/Auth/UserManagementTest.php`.
- [x] **`BusinessLineController` — responsible field.** Shared
      `validated()` gains `responsible_user_id` with a scoped
      `Rule::exists('users', 'id')->where('business_line_id', $ignore?->id ?? 0)->where('is_active', true)`.
      Feature tests added to `tests/Feature/BusinessLineConfigTest.php`:
      accepts a user assigned + active on that line, rejects a user on a
      different line, rejects an inactive user, rejects any value on
      `store`.
- [x] **`SettingsController` — users payload.** New `users()` private
      method: active users only, `id`/`name`/`business_line_id`, passed
      as a `users` prop alongside the existing `businessLines` prop.
      Asserted in `tests/Feature/BusinessLineConfigTest.php`.
- [x] **`Users/Form.vue`.** New `businessLines` prop, `business_line_id`
      form field, `businessLineOptions` computed (`null`-option-prepended,
      matching `EmployeeFields.vue`), a `LabeledInput`/`SelectInput` row
      after `role`, hidden when no business lines exist. Component test
      additions in `tests/js/UsersForm.test.js`.
- [x] **`BusinessLineList.vue`.** New `users` prop; new "Responsible"
      column — per-row `SelectInput` bound to `item.responsible_user_id`,
      options = active users whose `business_line_id` matches that row's
      `id` (disabled for an unsaved row, `id === null`). Component test
      additions in `tests/js/BusinessLineList.test.js`.
- [x] **`Settings/Index.vue`.** New `users` prop, passed through to
      `BusinessLineList`. Dirty-check and `saveBusinessLines()`'s `PUT`
      body both gain `responsible_user_id`. Test additions in
      `tests/js/SettingsIndex.test.js`.
- [x] **Language keys.** `users.field.business_line`,
      `users.field.business_line_none`, `business_lines.responsible`,
      `business_lines.responsible_none` in `resources/lang/en.json`.
- [x] **Self-assignment via the Account page.** Found mid-build: a
      non-admin's `Users/Form.vue` edit view is entirely read-only, so
      "a user must be able to reassign themselves" needed a different
      home. Added `PUT /account/business-line` →
      `AccountController::updateBusinessLine` (same validation and
      responsible-person cleanup as `UserController@update`, no admin
      gate, always acts on `$request->user()`), a `businessLines` prop
      on `AccountController::show`, and `business_line_id` added to the
      shared `auth.user` Inertia prop (`HandleInertiaRequests`). New
      section on `Account/Show.vue` (`SelectInput`, unconditional —
      shown to admins too, unlike the employee-link section). Feature
      tests in `tests/Feature/Auth/AccountTest.php` and
      `tests/Feature/Auth/UserAccountFieldsTest.php`; component tests in
      `tests/js/AccountShow.test.js`.
- [x] **Bug fix found during manual verification.**
      `SettingsController::users()` selected `['id', 'name', 'business_line_id']`
      — no `is_active`. `BusinessLineList.vue`'s responsible-picker filter
      checks `user.is_active`, which was therefore always `undefined`
      (falsy), so the picker was always empty regardless of real data.
      Fixed by adding `is_active` to the select. Regression test:
      `test_settings_passes_only_active_users` now asserts
      `users.0.is_active`.

- [x] **Unlocked in place on `Users/Form.vue` for your own record.**
      Feedback after the Account-page addition: a non-admin's own detail
      page (reached via the Users table) still showed business line
      grayed out like every other field, which read as a bug even though
      `/account` already worked. Added `isOwnRecord` and
      `businessLineSelfEditable` computeds; when true the select swaps
      to an independent `selfBusinessLineForm` with its own inline Save
      button, submitting to the same `PUT /account/business-line`
      (never the page's main admin-only form/route). Test additions in
      `tests/js/UsersForm.test.js` (own record vs. someone else's).
- [x] **Both new inline Save buttons disabled until the value actually
      changes**, matching the rest of the app's explicit-save
      convention. Uses Inertia's own `form.isDirty` (compares current
      values against the form's recorded defaults) rather than a
      hand-rolled comparison, and calls `form.defaults()` in `onSuccess`
      to reset the dirty baseline after a save — the same pattern
      `PeriodSettingsForm.vue` already uses. Applies to both
      `Account/Show.vue`'s `businessLineForm` and `Users/Form.vue`'s
      `selfBusinessLineForm`.

## Verification

- `php artisan test`: 576 passed.
- `npx vitest run`: 549 passed.
- `npx vite build`: clean.

## Notes

- Employee ↔ BusinessLine (`employees.business_line_id`,
  `EmployeeFields.vue`) is untouched — this feature is a second,
  independent relationship on `User`, not a change to the Employee one.
- Two independent write paths for `users.business_line_id`: admin-only
  `PUT /users/{user}` and self-only `PUT /account/business-line`. Both
  duplicate the same small responsible-person cleanup check rather than
  sharing a service — one `if` block, not enough to justify extracting.
