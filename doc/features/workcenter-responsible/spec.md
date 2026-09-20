# Workcenter Responsible

## Problem

An employee needs to know who to contact for a workcenter. The Responsible column in the planning tables shows a dash because the app does not store this person.

## Solution

Add an optional text field, `responsible`, to each workcenter.

- **Data:** a new nullable `responsible` string column on `workcenters`. The maximum length is 50 characters, the same as `name`. The field holds a name only. A blank value means no responsible person.
- **Editing:** Settings → Workcenters gets a "Contact" text input on each row, next to the name. New rows have it too. It saves with the existing Save action of the tab.
- **Display:** the Contact column in the planning tables on the employee edit page and on the employee's own page shows this name. It shows `-` when the field is blank.
- **Backend:** `Workcenter::toPayload()` and the workcenter validation include `responsible`. `PlannedShifts` adds `responsible` to each assignment.

## Key Decisions

- Use a plain text field. The responsible person is not an employee and has no login. Users and roles come in a later stage.
- Store a name only. Contact details are not in scope.
- Do not enforce uniqueness. One person can be responsible for many workcenters.
- Archived workcenters keep their value.

## Non-goals

- Users, roles, permissions, or invite emails.
- Email or phone for the responsible person.
- A migration path from this text to a user link. The later stage handles this.
