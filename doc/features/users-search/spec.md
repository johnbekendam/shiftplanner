# Users Search - Spec

## Problem

The Employees page has a search field.
The Users page does not.
Admins and managers must scan the full Users table to find a user.

## Solution

Add search to the Users page with the same interaction pattern as the Employees page.
The search field filters on the server.
The page stores the search term in the `search` query parameter.
Typing in the search field reloads the Users page after a short debounce.
Clearing the search field removes the query parameter.

## Key Decisions

- Search user `name` and `email`.
  This matches the Employees page behavior for name and email lookup.
- Keep the search server-side.
  This matches the Employees page and keeps the URL shareable.
- Reuse the existing `SearchInput` component.
  It already provides the standard styled search field.
- Do not add sorting or extra filters.
  This feature only adds search.

## Non-goals / Scope Boundaries

- Do not search role, status, or business line.
- Do not add pagination to the Users table.
- Do not change user create, edit, invite, or permission behavior.
- Do not change the Employees page.
