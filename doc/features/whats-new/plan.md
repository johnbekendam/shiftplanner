# What's New — Plan

Status: in progress — 3/5

- [x] 1. Read the entries. A `WhatsNew` service loads `resources/whats-new/*.md`, parses the front matter, renders the body and filters by audience (admin also gets `manager`). Sort newest first.
- [x] 2. Store the seen state. Add the migration for `users.whats_new_seen_at` and `employees.whats_new_seen_at`. Set the newest entry date when a user or an employee is created.
- [x] 3. Show the dialog to admins and managers. Share the entries and the unseen count as Inertia props. `AppLayout` opens the dialog with the unseen entries. A close posts to `/whats-new/seen`. Add the "What's new" link at the bottom of the sidebar.
- [ ] 4. Show the dialog to employees. The personal page gets its entries and unseen count. A close posts to `/personal/{token}/whats-new/seen`, also when changes are locked. Add the "What's new" link.
- [ ] 5. Write the first entries and the process rule. Add the entries for Demand, the date slots and the Schedule rename. Add the rule to `AGENTS.md`.
