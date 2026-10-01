# What's New — Plan

Status: in progress — 6/9

- [x] 1. Read the entries. A `WhatsNew` service loads `resources/whats-new/*.md`, parses the front matter, renders the body and filters by audience (admin also gets `manager`). Sort newest first.
- [x] 2. Store the seen state. Add the migration for `users.whats_new_seen_at` and `employees.whats_new_seen_at`. Set the newest entry date when a user or an employee is created.
- [x] 3. Show the dialog to admins and managers. Share the entries and the unseen count as Inertia props. `AppLayout` opens the dialog with the unseen entries. A close posts to `/whats-new/seen`. Add the "What's new" link at the bottom of the sidebar.
- [x] 4. Show the dialog to employees. The personal page gets its entries and unseen count. A close posts to `/personal/{token}/whats-new/seen`, also when changes are locked. Add the "What's new" link.
- [x] 5. Write the first entries and the process rule. Add the entries for Demand, the date slots and the Schedule rename. Add the rule to `AGENTS.md`.

Revision: badge and page, users only.

- [x] 6. Remove the employee parts. Remove the personal-page dialog, its route and prop, the `Employee` creation hook, the `employees` column from the migration and the `employee` audience.
- [ ] 7. Add the What's new page. `GET /whats-new` shows the list of entries and the selected entry (`?entry=<id>`, default the newest), with Previous and Next. Mark the entries that were unseen as "New". The visit stores the newest entry date as seen.
- [ ] 8. Replace the dialog with a badge. Share only the unseen count. The sidebar item links to `/whats-new` and shows the count as a badge. Remove the dialog, `useWhatsNew` and `POST /whats-new/seen`.
- [ ] 9. Update the process rule and the entries for the users-only scope.
