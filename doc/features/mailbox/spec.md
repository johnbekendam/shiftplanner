# Mailbox — Spec

Roadmap phase 3.8. Turns the template's generic mailbox baseline into a
typed message tool. One message type ships: a personal-page link for an
employee. Microsoft Graph delivery is prepared but not wired, because the
Graph tenant is not reachable from the development machine.

## Problem

The phase-0 template snapshot left a working but generic mailbox
(`MailboxController`, `Message`, `SendMailboxMessage`, `Mailbox.vue`,
`messages` table). It has free-form compose, no message types, no link to
an employee, no nav entry, and no send transport beyond `log`. Roadmap
phase 2 only names a `mailto:` invitation. ShiftPlanner needs to send an
employee the link to their personal page, from a shared mailbox, with a
preview and a reusable template, and to keep the door open for more
message types later.

## Solution

### Message types

Every message has a `type`. One type ships: `personal_page_link`. The
free-form compose path from the baseline is removed.

- `messages` gains a `type` string column, not null. Existing rows (none
  in production) are irrelevant; the migration backfills `personal_page_link`.
- A `MessageType` enum (`app/Enums/MessageType.php`) holds the cases and,
  per case, its placeholder list and whether it needs employee recipients.
  `personal_page_link` needs employees and offers `:name` and `:link`.

### Templates

Each type has one stored, editable template.

- New `message_templates` table: `type` (unique), `subject`, `body`
  (Markdown), timestamps. Seeded with the `personal_page_link` default
  from `en.json` keys so the copy stays translatable at seed time.
- `MessageTemplate::forType(MessageType)` returns the row, creating it
  from the seed default on first read (the `PlanningSettings::current()`
  pattern).
- `PUT /mailbox/templates/{type}` → `MailboxController@updateTemplate`,
  behind `admin`. Validation: `subject` `required|string|max:255`, `body`
  `required|string|max:20000`. Saving from the Compose tab updates the
  stored template, so the next compose of that type starts from it.

### Compose tab

`/mailbox?tab=compose`. The type select defaults to `personal_page_link`
(the only option). For that type:

1. The stored template loads into an editable subject field and a
   `MultilineInput` body field. A **Save template** button persists edits
   back to `message_templates`.
2. A searchable employee multi-select replaces the free email box. It
   lists every employee (name + email).
3. **Preview** renders the branded email through `MessageComposer`
   against the first selected employee; with none selected, against
   sample values (`:name` → a sample name, `:link` →
   `url('/personal/EXAMPLE-TOKEN')`). The existing `EmailPreviewModal`
   shows it.
4. **Create drafts** makes one `Message` per selected employee, `type`
   `personal_page_link`, `status` `draft`, `recipient_email` the
   employee's email, `recipient_name` the employee's name, placeholders
   resolved per employee and baked into stored `body` and `body_html`
   (the baseline already renders `body_html` at store time). Creating a
   message calls `EmployeePersonalLinkService::linkFor($employee)` first,
   so the token exists; an existing token is reused, never regenerated.
5. **Send now** does step 4 then promotes each new message to `outbox`
   and dispatches `SendMailboxMessage`, as the baseline `store()` does
   for `send_mode = queue`.

Placeholder resolution is a small service (`PersonalLinkMessage` or a
method on `MessageComposer`): `:name` → employee name, `:link` → the
absolute personal URL. Resolution happens once, at message creation, so
stored, previewed, and sent content match.

### Draft / Outbox / Sent tabs — shared

The per-user filter is dropped. Any admin sees every message in each tab.
`Message::forUser()` scope is removed from the mailbox queries; `user_id`
stays on the row as "composed by" and shows in a new list column.
`send`, `destroy`, and `bulkDelete` drop the
`abort_unless($message->user_id === …)` ownership check — `admin`
middleware is the only gate. Counts and search are unchanged apart from
losing the user scope.

### Employees page — start a message

- `Employees/Index.vue`: each row gains a **Send link** / **Resend link**
  action. The label is **Resend link** when a `sent` message of type
  `personal_page_link` to that employee's email exists, else **Send
  link**. The list controller computes this with one grouped query
  (`whereIn recipient_email … where type … where status = 'sent'`).
- `Employees/Form.vue`: the same button in the editor header.
- Both navigate to `/mailbox?tab=compose&type=personal_page_link&employee={id}`.
  Compose reads `employee` from the query and preselects it. Nothing is
  created until the admin uses Create drafts / Send now.

### Microsoft Graph transport — prepared, inert

Delivery stays behind Laravel's mail transport. A custom `graph` mailer
is added and selected in production; local dev keeps `MAIL_MAILER=log`,
so Compose → Send → Outbox → Sent works unchanged during development.

- `App\Mail\Transport\GraphTransport extends
  Symfony\Component\Mailer\Transport\AbstractTransport`. `doSend()` reads
  the `SentMessage`'s `Symfony\Component\Mime\Email`, builds the Graph
  `sendMail` JSON (subject, HTML body, `toRecipients`, `ccRecipients`,
  `replyTo`), and `POST`s to
  `https://graph.microsoft.com/v1.0/users/{sender}/sendMail` with
  `saveToSentItems: true`.
- `App\Services\Graph\GraphClient`: client-credentials token from
  `https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token`
  (`scope=https://graph.microsoft.com/.default`), cached until just
  before expiry. Uses the Laravel `Http` client. No Graph SDK dependency.
- `config/services.php` gains a `graph` block: `tenant_id`, `client_id`,
  `client_secret`, `mail_from` — all from `GRAPH_*` env.
- `config/mail.php` `mailers` gains
  `graph => ['transport' => 'graph']`. A `MailManager` extension in
  `AppServiceProvider` (or a dedicated provider) registers the
  `graph` creator returning `new GraphTransport(app(GraphClient::class),
  config('services.graph.mail_from'))`.
- `.env.example` gains blank `GRAPH_TENANT_ID`, `GRAPH_CLIENT_ID`,
  `GRAPH_CLIENT_SECRET`, `GRAPH_MAIL_FROM`, and a commented
  `# MAIL_MAILER=graph` note.
- `MailboxLogger` (already present, unused) is left as-is; the mailbox
  keeps its own `store()` flow.

### Navigation

`AppLayout.vue` admin branch gains
`{ label: __('nav.mailbox'), href: '/mailbox', icon: 'envelope' }`
before Users. `nav.mailbox` and the `envelope` icon already exist.

### i18n

New `mailbox.*` keys: `mailbox.compose.type`,
`mailbox.type.personal_page_link` (label),
`mailbox.type.personal_page_link.subject`,
`mailbox.type.personal_page_link.body` (seed template, with `:name` /
`:link`), `mailbox.compose.template_save`,
`mailbox.compose.employees`, `mailbox.compose.employees_hint`,
`mailbox.compose.create_drafts`, `mailbox.flash.template_saved`,
`mailbox.flash.drafts_created` (`:count`), `mailbox.column.composed_by`,
`mailbox.preview.sample_name`, `employees.action.send_link`,
`employees.action.resend_link`. The `mailbox.compose.to*` keys and the
free-form flash keys are removed.

## Key decisions

- **Typed messages, no free-form.** Everything the product sends is a
  known kind. A generic path is dead weight and a second recipient mode
  to maintain. A `general` type can be added later if a real need
  appears.
- **One stored template per type, edited in Compose.** The org changes
  standard wording without a deploy. Kept in its own table, not on
  `planning_settings`, because it is per-type and the set of types will
  grow.
- **Employees are the recipients, not free text.** `:link` only means
  something for a known employee. A multi-select makes one draft per
  person with the link resolved, and supports a later bulk send.
- **Placeholders resolved once, at creation.** Matches the baseline,
  which renders `body_html` at store time, and keeps preview, stored, and
  sent content identical.
- **Shared mailbox across admins.** The team is small and the send
  identity is one shared Graph mailbox; a per-composer view sits oddly
  with that. `user_id` stays only as an author label.
- **Graph behind a custom mail transport.** The job, the
  `ComposedMessage` mailable, the preview pipeline, and `Mail::fake()` in
  tests are all untouched. Production flips `MAIL_MAILER`; nothing else
  changes.
- **App-permission client credentials, shared mailbox.** No per-user
  token, works from the queue, one stable From address, independent of
  the phase-2 Entra SSO work. Needs an Azure app registration with
  admin-consented `Mail.Send` on one mailbox.
- **No Graph SDK.** One token call and one `sendMail` POST do not justify
  the dependency; the Laravel `Http` client is enough.

## Non-goals

- A `general` or any second message type.
- Bulk send (whole business line, everyone not yet invited) — the design
  leaves room; the UI is not built.
- Editing a message's text per send after Create drafts — the draft is
  edited by deleting and recomposing, as in the baseline.
- Delegated Graph send, `/me/sendMail`, Sent Items in a personal mailbox.
- Inbound mail, threading, replies, read receipts, delivery status beyond
  `sent`.
- Retrying Graph auth failures differently from the baseline job's
  existing backoff.
- Translating template content after seed; only UI labels and the seed
  default live in the language file.
- Token hardening (hashing, expiry, revocation) — still roadmap phase 2.
