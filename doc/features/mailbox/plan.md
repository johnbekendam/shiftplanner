# Mailbox — Plan

Status: done — 7/7

Spec: `spec.md`. Roadmap phase 3.8. Typed messages on top of the template
mailbox baseline; one type (`personal_page_link`); Microsoft Graph
transport prepared but inert (`MAIL_MAILER=log` in dev).

- [x] 1. **Message type + template store (backend).** `MessageType` enum
  (`personal_page_link`, with placeholder list and "needs employees").
  Migration: add `type` string to `messages` (backfill
  `personal_page_link`); create `message_templates` (`type` unique,
  `subject`, `body`, timestamps). `MessageTemplate` model with
  `forType()` that seeds from `en.json` on first read. `en.json` seed
  keys (`mailbox.type.personal_page_link.{label,subject,body}`). Unit test
  for `forType()` seeding. Full PHP suite green.
  Done: `app/Enums/MessageType.php`; migrations `..._000011` /
  `..._000012`; `app/Models/MessageTemplate.php`; `type` added to
  `Message` `$fillable` + cast and to `MessageFactory`;
  `tests/Unit/MessageTemplateTest.php` (2 tests). `type` is a plain
  string column (not a DB enum) so new cases need no schema change —
  validated via `Rule::enum(MessageType::class)`. Suite 257 green, Pint
  clean.

- [x] 2. **Compose backend: typed store, template save, preview.**
  `MailboxController`: `store()` takes `type` + `subject` + `body` +
  `employee_ids[]` + `send_mode`, resolves `:name` / `:link` per employee
  via `App\Services\PersonalLinkMessage` (which calls
  `EmployeePersonalLinkService::linkFor()`), creates one `Message` per
  employee with resolved `body` / `body_html`. `preview()` takes `type` +
  optional `employee_id`, renders against that employee or sample values
  (`mailbox.preview.sample_name`, `/personal/EXAMPLE-TOKEN`).
  `updateTemplate(MessageType $type)` + route
  `PUT /mailbox/templates/{type}` (implicit enum binding, 404 on unknown)
  behind `admin`. `to` / `parseRecipients` removed. New i18n:
  `mailbox.flash.drafts_created`, `mailbox.flash.queued`,
  `mailbox.flash.template_saved`, `mailbox.preview.sample_name`. Suite
  green (259), Pint clean.

- [x] 3. **Shared mailbox + composed-by.** `Message::forUser` scope
  removed; `index()` and `bulkDelete()` no longer filter by user;
  `send()` / `destroy()` dropped the ownership `abort_unless` (kept the
  `status === 'draft'` 422 in `send()`). `index()` rows go through
  `->through()` and carry `composed_by` (author name) plus
  `recipient_name`. `MailboxTest` covers: index shows every admin's
  messages; an admin can send/delete another admin's message; manager
  gets 403. Done together with step 2 in `MailboxController`.

- [x] 4. **Compose tab (frontend).** `Mailbox.vue`: type `SelectInput`
  (one option, from `compose.types`, `@change` reloads via `?type=`);
  editable subject + `MultilineInput` body seeded from
  `compose.template`; **Save template** button →
  `router.put('/mailbox/templates/'+type)`; new
  `components/mailbox/EmployeeMultiSelect.vue` (searchable checkbox list
  from `compose.employees`) replacing the email box, preselecting
  `compose.preselected_employee_id`. **Preview** posts `type` + first
  selected `employee_id`; **Create drafts** / **Send now** post `type` +
  `subject` + `body` + `employee_ids` + `send_mode`. List gains a
  "Composed by" column. `MailboxController@index` gains
  `composePayload()` (types, current template, employee list, preselected
  id). `en.json` mailbox block reworked: removed `compose.to*`,
  `save_draft`, `send`, `compose.error.*`, `flash.draft`, `flash.queue`;
  added `compose.type`, `compose.template_save`, `compose.create_drafts`,
  `compose.employees*`, `column.composed_by`. `tests/js/Mailbox.test.js`
  (7 tests). JS 248, PHP 259, build all green.

- [x] 5. **Employees page trigger.** `EmployeeController@index` runs one
  grouped `Message` query (`type = personal_page_link`, `status = sent`,
  `whereIn recipient_email`) → `link_sent` per row; `edit()` payload
  gains `link_sent` via `->exists()`. `Employees/Index.vue` gains a
  trailing action column and `Employees/Form.vue` a Details-tab action:
  **Send link** / **Resend link** by `link_sent`, a `Link` to
  `/mailbox?tab=compose&type=personal_page_link&employee={id}` (`@click.stop`
  on the row so it doesn't open the editor). `en.json`
  `employees.action.send_link` / `.resend_link`. `EmployeeAdminTest`:
  index marks link-sent (draft doesn't count), edit payload carries it.
  `EmployeesIndex.test.js` / `EmployeesForm.test.js` updated (label flips,
  href, row-action click doesn't navigate). JS 252, PHP 261, build green.

- [x] 6. **Graph transport (prepared, inert).**
  `App\Services\Graph\GraphClient` (client-credentials token via the
  `Http` client, cached for `expires_in − 300s`; `sendMail()` POSTs to
  `/users/{sender}/sendMail`). `App\Mail\Transport\GraphTransport extends
  AbstractTransport` → translates the Symfony `Email` to the Graph
  `message` shape (`saveToSentItems: true`). `AppServiceProvider`:
  `GraphClient` singleton from `config('services.graph.*')` +
  `Mail::extend('graph', …)`. `config/services.php` `graph` block;
  `config/mail.php` `mailers.graph`; `.env.example` blank `GRAPH_*`.
  `tests/Unit/GraphTransportTest.php` (`Http::fake` — payload shape +
  bearer + URL encoding; token reused across sends; a 500 raises
  `RequestException` so the job retries). `Mail::mailer('graph')`
  resolves. `MAIL_MAILER` stays `log`. PHP 264 green, Pint clean.

- [x] 7. **Nav, docs, full checks.** `AppLayout.vue` admin nav gains the
  Mailbox item (`envelope`); `AppLayoutNav.test.js` updated. Dangling
  `mailbox-baseline` reference in `MessageComposer.php` rewritten.
  `doc/roadmap.md` phase 3.8 row → "In progress", phase 2 `mailto:` line
  points here. `doc/concept.md` Managers section describes the typed
  mailbox + shared Graph delivery; deferred/out-of-scope lists updated.
  Pint clean, `php artisan test` 264, `npm run test` 252, `npm run build`
  green, `php artisan migrate` applied `..._000011` / `..._000012` on the
  dev DB.

## Not done / deferred

- Everything under the spec's Non-goals: a second message type, bulk
  send, per-send text edits, delegated Graph send, inbound mail.
- Live Graph verification — done on the other machine once `GRAPH_*` and
  `MAIL_MAILER=graph` are set.
- Manual browser pass — AGENTS.md leaves UI verification to the user.
