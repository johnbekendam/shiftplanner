# Employee Deletion

## Problem

Managers and administrators can create and edit employees, but they cannot delete them. Obsolete employee records remain in the employee list and scheduling data.

Users need a clear way to select employees from the table and delete them in one action. Search, sorting, and pagination must continue to work.

## Solution

Add a checkbox to each row in the employee table. Add a select-all checkbox that selects the visible rows on the current page.

Show a danger button below the table with the label `Delete selected (N)`. Keep the button visible and disable it when no employees are selected.

Before deletion, show a browser confirmation dialog. The dialog states the selected employee count and warns that deletion permanently removes dependent scheduling data.

After confirmation, permanently delete the selected employees. Existing foreign-key rules delete their personal links, holidays, recurring availability, competence assignments, and availability-question assignments.

Mailbox messages keep their stored recipient details. A linked login account also remains, but its employee link becomes null.

## Key Decisions

- Managers and administrators can delete employees because both roles already manage employee records.
- Selection applies only to the current page. This keeps bulk deletion clear and limits accidental scope.
- Search, sort, and page changes clear the selection. Hidden rows cannot remain selected.
- The select-all checkbox selects only the visible rows on the current page.
- The delete button always appears below the table and shows the selected count.
- A browser confirmation dialog follows the existing confirmation pattern in the application.
- Employee deletion is permanent. The database uses its existing cascade rules for dependent scheduling data.
- Linked login accounts remain available. Employee deletion only removes the account link.
- Mailbox history remains available because messages store recipient snapshots.

## Non-Goals / Scope Boundaries

- Employee archiving or deactivation.
- Selection across multiple pages.
- Selection of all filtered results.
- Deletion or deactivation of linked login accounts.
- Restore support for deleted employees.
- A new audit log.
- A custom confirmation modal.
