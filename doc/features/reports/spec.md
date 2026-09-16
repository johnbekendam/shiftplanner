# Reports

## Problem

Managers cannot see which employees have not set their weekly availability
for a shift. They have no way to email those employees as a group.

## Solution

Add a Reports page. The page holds a card with tabs in the header, one tab
per report type. This feature ships one tab: **Missing availability**.

The tab shows employees who have never set availability for a shift the
manager picks. The manager can select employees from the list and send
them a custom email from the Compose page, with recipients preloaded.

### Missing-availability report

Controls above the table:

- **Shift** — defaults to "All shifts".
- **Business line** — optional filter.
- **Include unconfirmed employees** — toggle, off by default.

An employee appears in the table when all of these hold:

- They have zero `recurring_availabilities` rows for the picked shift, on
  any weekday. With "All shifts" picked, they have zero rows at all,
  across every shift.
- Their `weekly_hours` is greater than 0.
- They are confirmed, or the "include unconfirmed" toggle is on.
- If a business-line filter is set, they match it.

Table columns: Name, Business line, Weekly hours, Confirmed status. No
email column — the report only feeds the compose flow, not a mailing view.

Each row has a checkbox. A "select all" checkbox toggles every visible
row. An "Email selected (N)" button sends the manager to Compose with the
selected employees preloaded.

### Compose handoff

The button navigates to:

```
/mailbox?tab=compose&type=custom&employee_ids[]=1&employee_ids[]=2...
```

`MailboxController::composePayload()` gains support for a plural
`employee_ids[]` query parameter. It reads this alongside the existing
singular `employee` parameter, which stays unchanged for the
personal-page-link flow. Both seed `RecipientPicker`'s `employee_ids`
model.

### Access and navigation

The `/reports` route sits in the same `admin` middleware group as
`/mailbox` and `/settings`. A "Reports" link appears in the sidebar's
admin section, near Mailbox.

## Key decisions

- **"Not set" means zero rows for the shift, any weekday.** Availability
  storage is sparse. A missing row already means "not set" for
  scheduling. Checking "any weekday missing" would also flag employees
  who filled in most weekdays but missed one. That is a different,
  noisier report the manager did not ask for.
- **"All shifts" means zero rows at all, not "missing any one shift."**
  An employee who has set availability for some shifts but not others has
  still engaged with the feature. "All shifts" surfaces employees who
  have never touched availability at all, not everyone with a gap
  somewhere.
- **Employees with `weekly_hours = 0` are excluded.** They have no hours
  to allocate, so asking them to set shift availability is not
  actionable.
- **Unconfirmed employees are excluded by default.** They are still
  onboarding. Chasing them for shift availability is premature until a
  manager opts in.
- **No email column in the table.** The table is a worklist, not a
  mailing list. The email address only matters once the manager reaches
  Compose.
- **The tab container is generic, but only one tab ships now.** No other
  report type is planned yet. Building a second tab's abstraction ahead
  of a real need would be speculative.
- **Reuse `Tabs.vue` for the card header**, not a hand-rolled bar like
  Mailbox's. Mailbox predates the shared component. New UI should use it.

## Non-goals

- No per-date availability. The report follows the app's existing
  weekly-recurring model.
- No report beyond "missing availability" in this feature.
- No change to who can access Mailbox or send email — the report reuses
  existing admin-only send permissions.
