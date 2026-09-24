# Planning Selection Session Persistence

## Problem

The planning page resets its week and workcenter selection after a planner visits another page. The planner must restore the same view when they return.

## Solution

Store the selected week's Monday in browser `sessionStorage`. Use a key that includes the signed-in user ID.

When a planner opens `/planning` without a date, restore the stored week. When the URL contains a date, use that date and store its week instead.

Discard a stored value if it is not a valid ISO date. Keep the current default-week behavior when no valid stored week exists.

Store the selected workcenter IDs in a separate per-user session value. Restore an empty selection and all other valid selections. Remove IDs for deleted workcenters. Select new workcenters by default when they do not exist in the stored selection.

## Key Decisions

- **Use browser-tab session storage.** The selection survives navigation and refresh. It resets when the browser-tab session ends.
- **Store one week per user.** Account switches in the same tab do not share a selected week.
- **Let explicit URLs win.** A link with a date shows that date's week and replaces the stored week.
- **Store the Monday.** The feature persists the selected week, not the exact clicked day.
- **Preserve workcenter choices.** The feature stores selected and deselected workcenters, including an empty selection.
- **Select new workcenters.** A new workcenter starts selected. A deleted workcenter is removed from the stored selection.
- **Keep persistence in the frontend.** The feature needs no server session or database changes.

## Non-goals

- Persistence after the browser-tab session ends.
- Synchronization across browser tabs or devices.
- A database preference for the selected week.
- Changes to planning-period limits or week-selection rules.
