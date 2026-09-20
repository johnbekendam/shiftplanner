# Planning Notifications

## Problem

A planner cannot see which employees do not know their published shifts yet. The planner must email each employee by hand and has no record of who got the planning.

## Solution

Track per shift assignment whether the employee was told. Add a `Planning` message type. Add a report of employees with uninformed planning. Add a button on the planning page that emails all of them.

### Informed flag

A new nullable `informed_at` column on `shift_assignments`. An assignment is **uninformed planning** when all of these hold:

- Its date is today or later.
- Its workcenter is published for its week.
- `informed_at` is null.

The queue job sets `informed_at` after it sends a Planning email successfully. It sets it on exactly the assignments that email lists. A draft, a queued message, or a failed send marks nothing.

A new nullable `assignment_ids` JSON column on `messages` stores the assignments a Planning message lists. The store step fills it when the message is created.

### Planning message type

A new composable `MessageType::Planning` with its own editable template. The seed template has a subject, a greeting with `:name`, the `:planning` placeholder, and a button to the personal page.

A new `:planning` placeholder in `PlaceholderRegistry` resolves to a list of all upcoming published shifts of the employee. Each line has the day, the date (`dd-mm-yyyy`), the shift with its times, and the workcenter. The placeholder does not resolve for an employee with no upcoming published shifts. The existing unresolved-recipient handling then applies.

A Planning email lists all upcoming published shifts, not only the uninformed ones. It marks all of them as informed.

### Report

The Reports page gets a second tab, **Uninformed planning**. It has a business-line filter. The table lists each employee with uninformed planning. Columns: Name, Business line, Uninformed shifts (count), First shift date.

Each row has a checkbox, and a header checkbox selects all rows. An "Email selected (N)" button opens Compose with `type=planning` and the selected `employee_ids[]`. This is the same handoff as the Missing availability tab.

### Planning page button

A **Send planning** button on the planning page. It is active only when at least one employee has uninformed planning. A click opens a confirm dialog with the number of employees. On confirmation the server creates one Planning message per employee from the saved Planning template and queues all of them. The logged-in user is the sender. The flash message states how many emails are queued.

## Key Decisions

- **Flag per assignment.** A shift published or added later makes the employee uninformed again, and only that change is detected. A flag per employee would miss later weeks.
- **Set on real send.** A failed send must not mark an employee as informed. This needs the message to remember its assignments.
- **List all upcoming published shifts.** The employee always gets the current overview, and a moved shift shows its new place without extra text.
- **Only published shifts count.** The employee cannot see draft shifts, so a draft is not planning to tell.
- **Removed or unpublished shifts are ignored.** Only new shifts make an employee uninformed. The employee sees the current state in the next email or on the personal page.
- **Existing published shifts start as uninformed.** No email about them was sent by this system.
- **Confirm before a direct send.** This follows the confirm dialogs of Generate and Clear planning.
- **Reuse the placeholder registry and the compose flow.** No second sending path is added, except the direct send, which uses the same message creation and queue job.

## Non-goals

- Emails about removed, moved, or unpublished shifts.
- Reminders, or scheduled sending.
- A per-employee "send now" button on the planning page.
- A change of the personal page.
