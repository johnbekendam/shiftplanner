# Confirmed Employees - Spec

## Problem

The app treats every employee as ready for planning as soon as the row
exists. This can overstate dashboard coverage and can let a manager assign
employees who have not yet been reviewed.

A manager needs a clear way to mark an employee as confirmed before the
employee affects planning numbers or appears as eligible for a shift.

## Solution

Add a boolean confirmed status to employees. New employees default to
unconfirmed. Existing employees also stay unconfirmed after the migration.

Add a Confirmed column to the employees list. The column contains a toggle
for each employee. A user who can edit employees can change the toggle. The
change saves immediately for that row. The column header has a tooltip:
"Only confirmed employees can be planned. Manager should set the emmployee
to confirmed in this column".

Dashboard coverage uses only confirmed employees. The dashboard also shows
one global notice above the coverage cards when unconfirmed employees exist.
The notice states the number of unconfirmed employees that the dashboard
excludes.

Scheduling eligibility uses only confirmed employees. Unconfirmed employees
do not appear in the eligible employee picker. Existing assignments stay in
place if an employee later becomes unconfirmed.

## Key Decisions

- **Default unconfirmed.** A new employee should not affect planning until a
  manager confirms the employee.
- **Existing employees stay unconfirmed.** The migration forces managers to
  review current employees before they count again.
- **Immediate row save.** The list toggle sends a small request for one row.
  This avoids a separate batch-save flow.
- **Header help text.** The Confirmed column explains the planning effect in
  a tooltip, so the list stays compact.
- **Dashboard and scheduling eligibility only.** The employee list remains
  the management surface for confirmed and unconfirmed employees.
- **One dashboard notice.** A single dashboard-level count avoids repeated
  text on each business-line card.
- **Keep existing assignments.** Confirmed status gates future eligibility.
  It does not delete or block existing schedule records.
- **Use current employee edit access.** Anyone who can edit employees can
  change the confirmed status.

## Non-goals

- Automatic confirmation from signup, invite, or profile completion.
- A confirmation review workflow with comments or history.
- Removing existing assignments when an employee becomes unconfirmed.
- Hiding unconfirmed employees from the employees list.
- Filtering mailbox recipients or other non-scheduling employee pickers.
- Per-business-line dashboard unconfirmed counts.