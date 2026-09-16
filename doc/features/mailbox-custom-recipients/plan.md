# Mailbox — Custom Messages and User Recipients — Plan

Status: done — 5/5

Spec: `spec.md`. Adds a `Custom` message type, a global placeholder
registry, User recipients alongside Employees (deduplicated by email), a
pre-send warning for unresolvable placeholders, and a new "+ to add,
visible selected list" recipient picker used by every composable type.

- [x] 1. **Custom type + placeholder registry (backend).**
  `MessageType` gains `case Custom = 'custom'`; `needsEmployees()`
  renamed `composable()`, same true/false split. New
  `App\Services\Placeholders\PlaceholderRegistry` with `:name` and
  `:link` definitions (resolve-for-employee, resolve-for-user, sample).
  New `App\Services\MessagePlaceholders` replacing `PersonalLinkMessage`
  in `MailboxController`: resolves all registry tokens present in a
  subject/body against one recipient (`Employee` or `User`), returns
  substituted text plus the list of unresolved tokens. `en.json` gains
  `mailbox.type.custom.{label,subject,body}` (empty subject/body — no
  fixed wording). Unit tests for `MessagePlaceholders` (resolves for
  Employee, resolves for User with linked Employee, returns `:link`
  unresolved for a User with none, leaves non-registry tokens alone).
  Done: `app/Enums/MessageType.php`;
  `app/Services/Placeholders/PlaceholderRegistry.php`;
  `app/Services/MessagePlaceholders.php`; `PersonalLinkMessage` is
  untouched and stays the resolver for `EmployeeController::sendLink` /
  `SelfSignupService`, which are single-type, Employee-only flows outside
  this feature's scope. `tests/Unit/MessagePlaceholdersTest.php` (4
  tests). Suite 585 green, Pint clean.

- [x] 2. **Recipient union + pre-send warning (backend).**
  `MailboxController::composePayload()` adds a `users` list (id, name,
  email). `store()` accepts `employee_ids` and `user_ids` (validated: at
  least one of the two non-empty), builds the combined recipient list via
  `resolveRecipients()`, deduplicated by email (case-insensitive) keeping
  the Employee copy on a collision. Runs a dry-run `MessagePlaceholders`
  pass per recipient before creating any `Message`; if any recipient has
  unresolved tokens and the request did not pass
  `exclude_unresolved: true`, flashes the affected-recipients list
  (`session('unresolved_recipients')`, read back into `composePayload()`)
  and redirects back without creating anything. With
  `exclude_unresolved: true`, creates messages only for resolvable
  recipients. `preview()` accepts `employee_id` or `user_id`. Feature
  tests added to `MailboxTest`: custom message to users, employees+users
  combined with an overlapping email deduplicated to one message, the
  unresolved-placeholder warning, and `exclude_unresolved` skipping the
  bad recipient; `preview` for a User with a linked Employee. Suite 585
  green, Pint clean.

- [x] 3. **Recipient picker (frontend).** New
  `resources/js/components/mailbox/RecipientPicker.vue` replacing
  `EmployeeMultiSelect.vue`: Employees/Users source tabs, `SearchInput`,
  a scrollable list with a **+** add button per row (`check-circle` icon
  once added, still clickable to remove), and a visible selected list
  below with per-person remove (×) and a source badge. `v-model` is
  `{ employee_ids: number[], user_ids: number[] }`. New i18n keys:
  `mailbox.compose.source.{employees,users}`, `recipient_add`,
  `recipient_remove`, `badge.{employee,user}`, `recipients*`. Done:
  `resources/js/components/mailbox/RecipientPicker.vue`;
  `tests/js/RecipientPicker.test.js` (5 tests: add via +, remove from
  the selected list, tab switch keeps prior selections, search filters
  the active source only, selected list spans both sources).
  `EmployeeMultiSelect.vue` deleted (no remaining references). JS suite
  557 green.

- [x] 4. **Compose form wiring (frontend).** `Mailbox.vue`: type list
  now includes `custom` (server-filtered via `composable()`);
  `composeForm` gains `user_ids`; `RecipientPicker` swapped in behind a
  `recipients` computed (get/set over the two id arrays);
  submit-disabled check is `hasRecipients`; `previewCompose()` sends
  `employee_id`, or the first `user_id` when no employee is selected.
  New `UnresolvedRecipientsDialog.vue` (Teleport + Card-style markup,
  `ButtonPrimary`/`ButtonSecondary`), shown from a local
  `unresolvedRecipients` ref seeded from
  `compose.unresolved_recipients` and refreshed in `submitCompose()`'s
  `onSuccess`; **Continue** resubmits via `continueWithoutUnresolved()`
  (`exclude_unresolved: true`, same `send_mode` as the original
  attempt), **Cancel** just clears the ref. `en.json`
  `mailbox.compose.unresolved.*`. `tests/js/Mailbox.test.js`
  updated/extended (12 tests): Custom type free-text compose to
  selected users, the unresolved-recipients dialog rendering (via
  `attachTo: document.body` — the dialog teleports) and both of its
  actions. JS suite 557 green, `npm run build` green.

- [x] 5. **Full checks.** Re-read against `spec.md` surfaced one gap: the
  spec's "insert placeholder" affordance (§ Placeholder registry) was not
  yet built. Added: `MessagePlaceholders::tokens()`, `composePayload()`
  gains `placeholder_tokens`, and `Mailbox.vue` renders one button per
  token under the body field (`insertPlaceholder()` appends it to
  `composeForm.body`) — available for every composable type, not a
  per-type subset. `mailbox.compose.insert_placeholder` i18n key.
  Covered by `test_compose_tab_lists_users_and_placeholder_tokens` (PHP)
  and a click-appends-token test (JS). Otherwise the landed code matches
  `spec.md`, except the confirm/resubmit wiring for the unresolved-
  recipients dialog lives in `Mailbox.vue` rather than inside
  `UnresolvedRecipientsDialog.vue` itself — the dialog component is
  presentational only (`recipients` in, `cancel`/`continue` events out),
  matching how `EmailPreviewModal.vue` is split. Full PHP suite (586),
  full JS suite (558), `npm run build`, Pint — all green. No new
  migration: `custom` reuses `MessageTemplate::forType()`'s existing
  seed-on-first-read.

## Post-landing fixes

- **Seed-fallback bug.** `MessageTemplate::forType()`'s seed read
  `__($type->langKey().'.subject')` directly; called once before the
  `mailbox.type.custom.*` lang keys existed, Laravel's missing-
  translation fallback (return the key itself) got baked into the
  `custom` row permanently, showing literal `mailbox.type.custom.subject`
  text in Compose. Fixed: `forType()` now treats a lang lookup that
  returns its own key as blank, not a real value; the corrupted dev-DB
  row was reset by hand. Regression test in `MessageTemplateTest`.
- **Compose defaults to `custom`, not `personal_page_link`.** Changed
  the fallback in `MailboxController::composePayload()` (no `type` query
  param) and `Mailbox.vue`'s `composeForm` seed. The Employees page's
  **Send link** action is unaffected — it always passes
  `type=personal_page_link` explicitly. Regression test:
  `test_compose_tab_defaults_to_the_custom_type`.
- **RecipientPicker: hide selected people, add a whole group at once.**
  `filtered` now excludes ids already in `modelValue` for the active
  source, so an added person drops out of the source list instead of
  showing a checkmark — the selected list below is the only place they
  still appear. Added an **Add all** action (shown only when the source
  list has visible rows) that adds every currently filtered person in
  one click — respects the search term, so a filtered "Add all" adds
  only the matches, not the whole source. `tests/js/RecipientPicker.test.js`
  covers both (5 new/updated cases).

## Not done / deferred

- Everything under the spec's Non-goals: business line / workcenter
  targeting, a runtime-configurable placeholder system, editing a
  message's recipient list after creation, `:sender_name` / Graph /
  Draft-Outbox-Sent changes beyond treating `Custom` as an ordinary type.
- Manual browser pass — AGENTS.md leaves UI verification to the user.
