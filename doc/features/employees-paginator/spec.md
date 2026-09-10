# Employees Paginator — Spec

## Problem

The Employees page has a different paginator from the table preview on the
Theme Builder page. This makes the live page less useful for checking the
pagination theme tokens.

Managers also need a compact footer that shows where they are in the
employee list. The current footer only shows previous and next actions.

## Solution

Update the Employees page card footer to match the Theme Builder table
preview paginator.

The footer shows the item range on the left. It shows grouped page controls
on the right. The controls include previous, page numbers, and next.

The paginator keeps the current search and sort query when a manager changes
page. It shows a maximum of 15 employees per page.

The paginator continues to use the existing Laravel paginator data from the
Employees index response.

## Key Decisions

- Use the compact Theme Builder footer pattern. This gives the Employees page
  the same pagination surface that the theme preview shows.
- Use the existing pagination color tokens. The footer must react to theme
  changes without new color tokens.
- Set the employee page size to 15 rows. This matches the compact footer and
  keeps the list short enough to scan.
- Keep the existing backend pagination shape. Laravel already sends range,
  total, page, and link data.
- Keep pagination in the card footer. The table body stays focused on rows
  and bulk actions.

## Non-Goals / Scope Boundaries

- Do not change the backend query or pagination count.
- Do not add a new theme token.
- Do not build a shared paginator component unless the existing code requires
  it.