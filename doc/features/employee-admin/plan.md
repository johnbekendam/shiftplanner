# Employee Admin Prototype — Plan

Status: done — 9/9

Spec: `spec.md`. Roadmap phase 1.

- [x] 1. **Migrations + models + factories + seeder.** `departments`,
  `employees` (FK `department_id` restrict-on-delete, unique `email`,
  `shift_preference` enum), `employee_personal_links` (unique `token`,
  cascade on employee delete). `Department`, `Employee` (with
  `SHIFT_PREFERENCES` const + `scopeSearch`), `EmployeePersonalLink`
  models. Factories for all three. `DepartmentSeeder` (5 fixed names),
  called from `DatabaseSeeder`.
- [x] 2. **`EmployeePersonalLinkService`.** `linkFor(Employee): string`
  (get-or-create, absolute `/personal/{token}` URL) and
  `resolve(string): ?Employee`. Opaque `Str::random(40)` token, plain
  text, documented preview-only.
- [x] 3. **Validation.** Inline `$request->validate()` in the controllers
  (shared `validated()` helper on `EmployeeController` with unique-email
  ignore-on-update; `shift_preference` `Rule::in` on the personal page) —
  matching the template, which ships no FormRequest classes.
- [x] 4. **Controllers + routes.** `EmployeeController` (index + search +
  pagination, create, store, edit, update, `personalPage` → JSON `{url}`)
  and `PersonalPageController` (show, updatePreference, `resolveOrFail` →
  404). Manager routes behind `auth`; `/personal/{token}` routes
  token-only. Registered in `routes/web.php`.
- [x] 5. **Feature tests.** `EmployeeAdminTest` (10) + `PersonalPageTest`
  (5): create valid + validation errors + unique email, list + search +
  link status, edit/update + own-email keep, personal-link action +
  idempotency, token lookup, malformed token 404, preference update +
  bad value, no other employees exposed. 70 total suite green.
- [x] 6. **Employees index page.** `pages/Employees/Index.vue` on
  `AppLayout`, `Card`, `SearchInput`, template table + empty state,
  columns name/email/department/preference/link status, row actions edit +
  copy-link (axios → clipboard), footer pagination.
- [x] 7. **Employee create/edit form.** `pages/Employees/Form.vue`,
  `useForm`, `LabeledInput` + `TextInput`/`EmailInput` + `SelectInput`
  for department and preference, `ButtonPrimary`/`ButtonSecondary`,
  per-field error display. One component for create and edit.
- [x] 8. **Employee personal page.** `pages/Personal/Show.vue` on
  `CenteredLayout`, greeting + `SelectInput` preference + save,
  `form.recentlySuccessful` inline confirmation, no manager nav.
- [x] 9. **i18n + nav + checks.** `employees.*` / `personal.*` /
  `nav.employees` keys in `en.json`, Employees item (icon `users`) first
  in `AppLayout` nav, `link` icon added to `Icon.vue` map. Pint clean,
  `php artisan test` 70 green, `npm run test` 100 green, `npm run build`
  clean.

## Not done / deferred

- Manual browser pass of both workflows — the template's AGENTS.md leaves
  UI verification to the user.
- Everything under the spec's "Non-goals" and roadmap phase 2+.
