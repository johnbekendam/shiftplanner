# Mailbox Draft Sending — Spec

Allow an admin to send existing draft messages from the mailbox. The
feature uses the existing queued mail job and does not add a new transport
or message status.

## Problem

The mailbox can create and delete drafts, but an admin cannot send a draft
created with **Create drafts**. The existing queue path works for messages
created with **Send now**, but it is not available from the Drafts tab.

## Solution

Add a **Send** action to each draft row. Add a **Send selected** action to
the Drafts tab for selected drafts. Both actions require confirmation.

A single-message confirmation shows the recipient and subject. A bulk
confirmation shows the number of drafts and a compact recipient summary.
The message body is already stored on each draft, so sending does not
recompose or edit the message.

The server validates that every requested message exists and is still a
draft before changing any row. Bulk sending is all-or-nothing. After
validation, each draft changes to `outbox` and dispatches the existing
`SendMailboxMessage` job. Successful job delivery changes the message to
`sent` and sets `sent_at`, as it does for the existing queue flow.

## Key decisions

- **Drafts only.** Outbox and sent messages are not sendable through this
  feature. Failed-message retry and resend are separate future work.
- **Single and bulk actions.** Admins can send one draft or selected drafts
  from the Drafts tab.
- **Confirmation is mandatory.** Sending is an irreversible queue action.
  The confirmation action uses the danger button style.
- **Bulk validation is all-or-nothing.** A stale or non-draft selection
  prevents the whole batch from being queued. No selected message changes
  when validation fails.
- **Use the existing queue.** The web request changes drafts to outbox and
  dispatches `SendMailboxMessage`; it does not wait for SMTP or Graph.
- **Shared mailbox authorization remains unchanged.** The existing admin
  middleware protects the actions, and any admin can send any shared draft.
- **No new status.** Delivery failures remain governed by the existing job
  retry behavior. Failed-message state and retry UI are out of scope.

## Non-goals

- Retry or resend for sent or failed messages.
- Synchronous delivery or transport-specific behavior.
- Editing draft content during send.
- Sending outbox or sent messages.
- Per-user mailbox ownership.
- A new message type, mail transport, or database table.
