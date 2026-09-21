# Employee Optional Email — Spec

An admin can create an employee without an email address and add the
address later.

## Problem

Every employee needs an email address today. An admin often knows a new
employee before the address is known. The admin must then enter a fake
address or wait. Both options are wrong.

## Solution

### Data

- `employees.email` becomes nullable. The unique index stays. Postgres
  and SQLite both allow more than one NULL under a unique index.
- The server stores an empty string as NULL.
- A filled email must still be valid and unique.
- Email is optional on create and on edit. An admin can clear an existing
  email.

### Adding the email later

The admin edits the employee and enters the address. No other path
exists.

Self-signup does not change. It matches employees by email only. When a
person without a stored email signs up, the app creates a second
employee. The admin then deletes or completes one of the two records.

### Mail features

An employee without an email is not a mail recipient.

- The Mailbox recipient picker does not list the employee.
- On the Employees page, the "send link" button is disabled. The page
  has no "no email" marker.
- "Send planning" skips the employee. Their planning stays uninformed
  until an email is added.
- "Email selected" in the Missing availability report and in the
  Uninformed planning report skips the employee.
- Both reports show the employee with a "no email" marker next to the
  name, so the admin can see who is not reachable.
- The "copy personal-page link" action stays available. The link token
  does not depend on email.

### Backup

- The employee export writes `email: null` for these employees.
- The employee import accepts a null email. It then finds an existing
  employee that has no email and the same first and last name
  (case-insensitive). It updates that employee. If none exists, it
  creates one.
- The import rejects an archive that has two records with the same name
  and no email. The rule is the same as the duplicate-email rule.
- Implementation checks that the full `ApplicationBackup` restores a
  null email without change.

### Personal page

The read-only email field is hidden when the email is empty.

## Key decisions

- **Admin edit only.** A public form that adds an email to an existing
  record lets anyone claim a record and receive its personal link.
- **No name matching in self-signup.** The same reason. A duplicate
  record is a small cost. A wrong claim is a security fault.
- **Exclude and mark.** One rule for all mail flows gives no failed
  sends. The marker tells the admin why an employee gets no mail.
- **Match by name in the backup import.** A backup then keeps its data
  and a second import does not create duplicates.

## Non-goals

- A tool to merge duplicate employees.
- Name matching in self-signup.
- Email entry by the employee on the personal page.
- Changes to user (manager) accounts. They keep a required email.
