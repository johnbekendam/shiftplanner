# Users Business Line Column - Spec

## Problem

Admins and managers can assign a business line to a user account.
The Users table does not show that assignment.
They must open each user to see who has a business line.

## Solution

Add a read-only Business line column to the Users table.
Show the assigned business line abbreviation for each user.
Show a dash when the user has no assigned business line.

## Key Decisions

- Use the existing `users.business_line_id` relation.
  This feature only shows data that already exists.
- Show the business line abbreviation.
  This keeps the table compact and matches nearby employee table behavior.
- Show a dash for users with no business line.
  This makes unassigned users easy to scan.
- Do not add a new color token.
  Existing table text tokens are sufficient for the new column.

## Non-goals / Scope Boundaries

- Do not add sorting or filtering to the Users table.
- Do not change how admins assign a business line to a user.
- Do not change employee business line assignment.
- Do not change responsible user behavior for business lines.
