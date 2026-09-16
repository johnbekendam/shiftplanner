# Mailbox — Custom Messages and User Recipients — Spec

## Problem

The mailbox (`doc/features/mailbox/spec.md`) ships one message type,
`personal_page_link`, deliberately with no free-form path — "a `general`
type can be added later if a real need appears." That need now exists:
admins want to send a one-off message with no template behind it, and to
reach platform `User` accounts (e.g. non-employee admins), not just
`Employee` rows. The recipient picker also breaks down at scale: a plain
checkbox list scrolls the selected rows out of view. There is no way to
see who is picked once the list is longer than the visible area.

## Solution

### Custom message type

- `App\Enums\MessageType` gains `case Custom = 'custom'`. It appears in
  the Compose type list like `PersonalPageLink`. Its seed template
  (`message_templates`, `mailbox.type.custom.*` lang keys) starts with an
  empty subject and body — there is no fixed wording to seed.
- `needsEmployees()` is renamed `composable()` and now gates only
  whether a type shows in the Compose type list. It is true for
  `PersonalPageLink` and `Custom`, false for the system-issued
  `UserInvite` / `UserLoginLink`, unchanged. It no longer implies
  "employees only" — see Recipient picker below, which is the same for
  every composable type.

### Placeholder registry (generalized)

Per-type fixed placeholder lists (`MessageType::placeholders()`) are
replaced by one global registry, so every placeholder is usable in every
composable type's subject/body, including `Custom`.

- New `App\Services\Placeholders\PlaceholderRegistry`: a list of
  placeholder definitions, each `{ token, resolveForEmployee(Employee):
  ?string, resolveForUser(User): ?string, sampleValue }`. Ships with the
  two existing tokens: `:name` (employee first name / user name) and
  `:link` (personal page link). `:link` resolves for an `Employee`, or
  for a `User` with a linked `Employee`, and returns `null` otherwise.
  `:sender_name` stays specific to the system-issued `UserInvite` flow
  and is not in the general registry — that flow does not use this
  resolver.
- `App\Services\MessagePlaceholders` (replaces `PersonalLinkMessage`):
  given a recipient (`Employee` or `User`) and a subject+body pair, scans
  for every registry token actually present in the text, resolves each
  against the recipient, and reports which (if any) came back `null`.
  It returns the substituted text and the list of unresolved tokens for
  that recipient. Text with an unresolved token is never sent — see
  below.
- Compose gets a small "insert placeholder" affordance listing the full
  registry (not a per-type subset), so `:name`/`:link` are always
  available to insert into `Custom`'s free body.

### Recipient model: Employees ∪ Users

- `messages` table and `Message` model are unchanged (`recipient_name`,
  `recipient_email`). A message row does not carry which source it came
  from.
- Compose's recipient picker now offers two sources, **Employees** and
  **Users**, combinable in one send. `composePayload()` returns both
  `employees` (unchanged) and a new `users` list
  (`User::query()->orderBy('name')->get(['id','name','email'])`, no
  self-exclusion beyond what auth already implies — the composing admin
  can select themselves).
- `store()` accepts `employee_ids` and `user_ids` (both optional, at
  least one non-empty across the two). Recipients are built as one list
  of `{ source: 'employee'|'user', model }`, then **de-duplicated by
  email** (an Employee and a User sharing an email count once,
  keeping the Employee copy so `:link` still resolves) before the
  per-recipient fan-out that already exists in `store()`.

### Pre-send placeholder warning

- `store()` and `preview()` still resolve placeholders per recipient
  server-side, but `store()` now runs a dry-run resolution pass first
  (via `MessagePlaceholders`) over the deduplicated recipient list.
  Recipients with one or more unresolved tokens are set aside.
- If any recipient has unresolved placeholders, `POST /mailbox/compose`
  does **not** create messages yet. It returns the list of affected
  recipients (name/email) and which token(s) failed for each. The
  Compose UI shows this as a confirmation dialog: "N recipients cannot
  receive this message as written. Go back to fix it, or continue
  without them." **Continue** re-submits with an
  `exclude_unresolved: true` flag. The controller then creates messages
  only for the resolvable recipients. **Cancel** closes the dialog and
  changes nothing.
- Recipients with zero unresolved tokens are unaffected by this dialog.
  The common case (e.g. `Custom` with only `:name`, or `PersonalPageLink`
  sent to employees) never sees it.

### Recipient picker UI

`EmployeeMultiSelect.vue` is replaced by
`resources/js/components/mailbox/RecipientPicker.vue`, used for every
composable type (not just `Custom`):

- A source toggle (`Employees` / `Users` tabs) above a `SearchInput` and
  a scrollable list. Each row has a **+** icon button instead of a
  checkbox. Clicking it adds the person and the row shows a checkmark
  and dims to mark it "already added." The row stays clickable to
  remove, so the source list is still a valid way to deselect.
- A **visible selected list** below the picker, independent of the
  source list's scroll position. It shows one row per selected person
  (name, email, a small badge for Employee/User), each with a remove
  (×) button. This list stays on screen while the source list scrolls —
  it directly answers "selected people do not stay visible."
- `v-model` becomes `{ employee_ids: number[], user_ids: number[] }`
  instead of a flat array of employee ids, matching the two-source
  payload above.

### Compose form

- `composeForm` gains `user_ids: []` alongside `employee_ids: []`. The
  submit-disabled check becomes
  `!composeForm.employee_ids.length && !composeForm.user_ids.length`.
  `previewCompose()` sends the first selected recipient from either list
  (employee takes precedence if both are picked) as before, just against
  a generalized `preview()` payload (`employee_id` or `user_id`).
- The unresolved-recipients dialog is a new small modal component
  (`UnresolvedRecipientsDialog.vue`) reusing the existing `Card`/button
  primitives, triggered from `submitCompose()` when the server responds
  with the "needs confirmation" shape instead of a redirect.

## Key decisions

- **Custom is a `MessageType`, not a parallel flow.** This keeps one
  Message/MessageTemplate/MessageComposer pipeline. Drafts, outbox, sent,
  preview, and Graph delivery all work for it unchanged.
- **Recipients are a union across sources, deduplicated by email.** This
  matches "build up an audience" rather than forcing one mode per send.
  An Employee/User pair sharing an email is the same person and should
  get one email, not two.
- **Business line / workcenter grouping is out of scope.** The original
  ask's "target group" turned out to mean the employee/user axis, not a
  new bulk-by-group send. `doc/features/mailbox/spec.md` already lists
  "bulk send (whole business line...)" as a non-goal. That stands.
- **Placeholders are global, not per-type.** A fixed per-type placeholder
  list was the thing blocking `Custom` from being useful (no employee
  link to promise) and blocking `PersonalPageLink` from ever reaching a
  User. One registry, resolved per recipient, is simpler than special
  cases per type.
- **Unresolvable placeholders exclude the recipient, never send broken
  text.** A literal `:link` in someone's inbox is worse than not sending
  to them. The confirm-and-exclude dialog keeps the admin in control
  instead of silently dropping people.
- **The picker generalizes to every composable type**, not just
  `Custom`, because the old Employees-only, checkbox, no-visible-selection
  picker was the concrete complaint driving this feature — fixing it only
  for the new type would leave the original problem in place for
  `PersonalPageLink`.

## Non-goals

- Business line, workcenter, or any other group-based bulk targeting.
- A generic future-placeholder plugin system beyond the fixed
  `PlaceholderRegistry` list (`:name`, `:link` today) — new tokens are
  still added by editing the registry, not configured at runtime.
- Editing a sent/outbox message's recipient list after creation.
- Changing how `UserInvite` / `UserLoginLink` resolve `:sender_name` —
  those stay on their own system-issued path, untouched.
- Any change to Microsoft Graph delivery, `SendMailboxMessage`, or the
  Draft/Outbox/Sent tabs beyond what "Custom is a type like any other"
  already implies.
