Status: done — 6/6

Spec: `spec.md`. Add confirmed single and bulk sending for existing draft
messages through the current queued mailbox job.

- [x] 1. Add failing PHP feature tests for draft send authorization,
  single-send transition, all-or-nothing bulk validation, and dispatch.
- [x] 2. Add failing Vue tests for row Send, selected-draft Send selected,
  confirmation content, cancellation, and success/error refresh behavior.
- [x] 3. Implement the backend single and bulk send actions, preserving the
  existing draft-only and admin-only rules.
- [x] 4. Implement the mailbox Drafts-tab actions and confirmation dialogs,
  then refresh the list and counts after a successful queue request.
- [x] 5. Update translations and mailbox documentation.
- [x] 6. Run focused tests, the full PHP and JS suites, the production build,
  and formatting checks.

Full checks: PHP 606 passed; JS 604 passed; build green. Touched PHP files
pass Pint. Repository-wide Pint still reports unrelated pre-existing
violations in other files.

## Deferred

- Failed-message status and retry.
- Resend of sent messages.
- Synchronous delivery.
- Manual browser pass; AGENTS.md leaves UI verification to the user.
