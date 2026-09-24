# Employee Audit and Archival - Spec

## Problem

Managers and administrators can permanently delete employee records. A delete
also removes assignments, availability, and other employee data. The app does
not record who made the change or what data changed.

The organization needs a permanent history of employee changes. It also needs
to reverse an employee removal without data loss.

## Solution

### Employee archival

Replace employee deletion with archival. Add a nullable `archived_at` timestamp
to each employee. Archival preserves the employee and all related records.

The employee list defaults to active employees. Add Active, Archived, and All
status filters. An archived employee remains available from the Archived and
All results. The employee page shows archived records as read-only.

Managers and administrators can archive employees under the current employee
access rules. Only administrators can restore archived employees. Managers can
view the history of employees that they can access.

Archived employees do not appear in planning, scheduling choices, or other
operational employee selectors. Personal-link requests cannot change an
archived employee. Archival does not disable or unlink a related manager or
administrator account.

Public signup and account linking must not reactivate an archived employee.
When an email matches an archived employee, the app shows a neutral message
that tells the person to contact an administrator.

### Audit events

Add an immutable audit event for each employee-related change. Each event
belongs to one employee and stores:

- The action and the affected record type.
- The event timestamp and source.
- The changed field values before and after the action.
- The actor type and available actor identifier.
- A snapshot of the actor name, email, and role when available.

The app records changes to these employee data areas:

- Core profile, weekly hours, business line, and confirmation.
- Archive and restore state.
- Holidays and recurring availability.
- Competences and workcenters.
- Availability question answers.

Bulk actions create one event for each affected employee. Imports record the
employee changes that they cause.

Authenticated actions use the user as the actor. Other actions use a typed
source: Public signup, Employee personal link, System, or Backup import. A
personal-link event identifies the employee when possible. A backup import
also records the importing administrator snapshot.

Audit events remain in the live database indefinitely. The app has no action
to edit or delete them. Application backup exports and restores exclude audit
events.

### Audit history

Add an audit timeline to the employee page. Show the newest events first and
let the user expand an event to inspect its changed values.

Add a global audit page for administrators. The page shows the newest events
first. It supports employee or actor search and filters for action, source,
and date range.

## Key Decisions

- **Archive instead of delete.** Archival prevents cascading data loss and
  supports restoration.
- **Suspend operational use.** Archived employees cannot enter new planning
  or personal workflows, but their historical data remains intact.
- **Administrator restore.** Restoration can reverse a removal, so only an
  administrator can perform it.
- **Audit all employee data.** The history includes profile and related
  scheduling data because both can affect planning decisions.
- **Store changed values.** Old and new values explain the effect of an event
  without duplicating a full employee snapshot each time.
- **Store actor snapshots.** Later user account changes do not alter the
  historical identity shown for an event.
- **Use typed non-user sources.** Public and token-based workflows remain
  traceable without claiming that an authenticated user made the change.
- **Require manual restoration.** Signup and account linking cannot override
  an earlier archive decision.
- **Keep live history indefinitely.** Audit events have no user-controlled
  deletion or automatic expiry.
- **Exclude history from backups.** Application backups contain operational
  data, but they do not export or restore audit events.

## Non-goals

- Hard deletion of employees or audit events.
- Automatic restoration from signup or account linking.
- A configurable retention period or automatic audit cleanup.
- Audit history export through the application backup feature.
- Changes to the login status of a linked manager or administrator account.
- Audit logging for records that are not related to an employee.