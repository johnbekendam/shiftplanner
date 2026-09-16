# Business Line Assignments — Spec

A grilling session with the user settled this design before this
document was written.

## Problem

`BusinessLine` already relates to `Employee` (`employees.business_line_id`,
optional, editable on the Employee form). It has no relation to `User`,
the separate login-account concept (`role` admin/manager, optionally
linked to one `Employee` via `users.employee_id`). Nothing lets a manager
say which business line a User account belongs to, or who is responsible
for a given business line.

## Solution

### User → Business Line (new, optional)

A new nullable `users.business_line_id` foreign key to `business_lines`,
`nullOnDelete()` — same shape as the existing `users.employee_id` and
`employees.business_line_id` columns.

Two editing paths, for two different people:

- **An admin assigning someone else** — a `SelectInput` on
  `Users/Form.vue` (both create and edit), listing every business line
  plus a `null` "none" option, following the exact pattern
  `EmployeeFields.vue` already uses for the employee-side business line
  picker (`{ value: null, label: __(...) }` prepended to the mapped
  options). `Users/Form.vue`'s edit view is read-only for a non-admin
  (`readOnly = isEdit && !isAdmin`, same as `role`) — an admin viewing
  their own or someone else's record can edit it there; a non-admin
  cannot, even for their own record, since `PUT /users/{user}` sits
  behind the `admin` route middleware. It is not shown as a column on
  `Users/Index.vue`.
- **Anyone assigning themselves** — a second, independent `SelectInput`
  on the self-service `Account/Show.vue` page (`/account`), the existing
  home for self-management (`updatePassword`, `linkEmployee`). This is
  the actual answer to "a user must be able to reassign themselves" —
  discovered mid-build when a non-admin tester found `Users/Form.vue`
  entirely read-only for their own record. Rather than punching a
  self-edit exception into the admin-only `PUT /users/{user}` route
  (two authorization paths on one endpoint), this is a new, always-
  unrestricted `PUT /account/business-line`, mirroring `linkEmployee`'s
  shape: no `{user}` route parameter, always acts on `$request->user()`.
  Available to admins too, not just managers — unlike the employee-link
  section, this one isn't conditionally hidden.
- **The same field, unlocked in place on `Users/Form.vue` when it's your
  own record.** A non-admin landing on their own read-only detail page
  (reached from the Users table, the natural place to look) saw every
  field grayed out, business line included — confusing, since they
  *can* change it, just not from here. `Users/Form.vue` now special-cases
  this one field: `businessLineSelfEditable = readOnly && isOwnRecord`
  (`isOwnRecord` compares the logged-in user's id to the record being
  viewed). When true, the select swaps to its own `selfBusinessLineForm`
  and gets its own inline Save button next to it, submitting to the same
  `PUT /account/business-line` the Account page uses — not the page's
  main form/route, which stays admin-only. Every other field, and
  viewing anyone else's record, is unaffected.

`UserController` gains `business_line_id` in its `store`/`update`
validation (`nullable`, `exists:business_lines,id`) and in the `edit`
payload's `only([...])` list. `create`/`edit` also pass a `businessLines`
prop (`id`, `abbreviation`), mirroring how `Employees/Form.vue` already
receives its list. `AccountController::show` passes the same shape as a
`businessLines` prop; `AccountController::updateBusinessLine` runs the
same validation and the same responsible-person cleanup rule (below) as
`UserController@update`, duplicated rather than extracted — it's one
`if` block in each of two controllers, not enough to justify a shared
service. The shared `auth.user` Inertia prop (`HandleInertiaRequests`)
gains `business_line_id` alongside its existing `employee_id`, since
`Account/Show.vue` seeds its form from `useAuth()` rather than a
per-page prop.

### Business Line → Responsible person (new, optional)

A new nullable `business_lines.responsible_user_id` foreign key to
`users`, `nullOnDelete()`.

Edited via a per-row `SelectInput` in `BusinessLineList.vue`, on the
Settings page's Business Lines tab — a new "Responsible" column next to
abbreviation/description/target FTE, following the per-row-dropdown
pattern `PlanningRuleList.vue` already uses. Options for a row are the
**active** users whose `business_line_id` matches that row's business
line, plus a `null` "none" option. A brand-new, not-yet-saved row (no
`id` yet) can't have any responsible user — nobody could be assigned to
a business line that doesn't exist yet — so its cell renders disabled.

The tab keeps its existing explicit-save model
(`doc/features/explicit-save-consolidation/`): `responsible_user_id`
joins the existing dirty-check and the `PUT` body `saveBusinessLines()`
already sends per edited row. `SettingsController` gains a `users` prop
(active users only: `id`, `name`, `business_line_id`), passed down to
`BusinessLineList`.

`BusinessLineController`'s shared `validated()` gains
`responsible_user_id`, validated with a scoped `Rule::exists`
(`business_line_id` = this row's id, `is_active` = true) — enforced
server-side regardless of what the frontend's filtered dropdown sends.
On `store()` there is no row `id` yet, so the scope resolves to an
impossible `business_line_id = 0`, naturally rejecting any
`responsible_user_id` on create without a special case.

### Consistency rule

If a user's `business_line_id` changes to a different value (including
to `null`), `UserController@update` **and** `AccountController::updateBusinessLine`
both clear `responsible_user_id` on any business line that pointed to
this user — that assignment would otherwise become invalid, since the
responsible-person picker only offers users currently assigned to the
line. Deactivating a user (`is_active` → false) does **not** auto-clear
them as responsible; that value is left as-is (matches the user's own
stated preference).

### Payloads and models

- `User::businessLine(): BelongsTo`; `BusinessLine::responsibleUser(): BelongsTo`.
- `BusinessLine::$fillable` gains `responsible_user_id`; `toPayload()`
  gains `responsible_user_id`.
- `User::$fillable` gains `business_line_id`.

### Language keys

New keys: `users.field.business_line`, `users.field.business_line_none`,
`business_lines.responsible`, `business_lines.responsible_none`,
`account.business_line.heading`, `account.business_line.label`,
`account.business_line.none`, `account.business_line.save`,
`account.flash.business_line_saved`.

## Key decisions

- **"Users" means login accounts, not Employees.** Employee already has
  its own, separate, already-shipped business line assignment. This
  feature is additive — a second, independent relationship on `User` —
  not a rename or reuse of the Employee one.
- **Responsible-person picker is scoped to users already assigned to
  that business line**, not every user in the system — keeps "who's
  responsible" meaningfully tied to the team, at the cost of the picker
  being empty until users are assigned first.
- **Active users only** in that picker — a deactivated user shouldn't be
  selectable as the current responsible person. Deactivation does not
  retroactively clear an existing selection, though; that's a separate
  concern from the assignment-changed cleanup rule below.
- **Reassignment auto-clears stale responsibility.** Changing a user's
  business line (or clearing it) clears `responsible_user_id` on any
  business line that named them, keeping the "must be assigned" rule
  true at all times without relying on the frontend alone.
- **Enforced server-side, not just via a filtered dropdown.** The scoped
  `Rule::exists` on `BusinessLineController` prevents an invalid
  responsible-user assignment even from a direct request.
- **Self-assignment lives on `/account`, not as an exception carved into
  `Users/Form.vue`.** The original "always editable" answer turned out
  to mean self-service, not that non-admins should get write access to
  the admin user-management page. Splitting it into two independent
  endpoints (`PUT /users/{user}`, admin-only; `PUT /account/business-line`,
  self-only) keeps `EnsureAdmin` on the admin route unconditional, rather
  than adding a second, narrower authorization path to it.

## Non-goals

- Any change to the existing `Employee` ↔ `BusinessLine` relationship or
  its UI (`EmployeeFields.vue`, `employees.business_line_id`).
- Showing business line on `Users/Index.vue`.
- Any notification, permission, or behavioral change tied to being the
  "responsible" person — this feature only stores and displays the
  assignment.
- Bulk/multi-user assignment to a business line, or assigning more than
  one responsible person per business line.
