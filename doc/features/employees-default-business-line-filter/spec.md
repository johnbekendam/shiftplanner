# Employees Default Business Line Filter - Spec

## Problem

A user can have an assigned business line.
When that user opens the Employees page, the page starts with all business lines selected.
The user must manually filter to their assigned business line.

## Solution

When a logged-in user has `business_line_id`, apply that business line as the default Employees page filter.
Apply it only once per browser tab.
Store the applied flag in `sessionStorage`.
After the user changes filters or navigates in the tab, do not apply the default again.

## Key Decisions

- Use the existing `auth.user.business_line_id` Inertia prop.
  The business line assignment already exists on the logged-in user payload.
- Apply the default on the client.
  This keeps the behavior tab-scoped and avoids new server session state.
- Use `sessionStorage` for the once-per-session flag.
  This matches the requested browser-tab session behavior.
- Apply only when the Employees page has no explicit business line query.
  An explicit query means the user or another page already chose the filter.

## Non-goals / Scope Boundaries

- Do not change the business line filter UI.
- Do not add a new server-side preference.
- Do not change employee assignment to business lines.
- Do not change filters for users without an assigned business line.
