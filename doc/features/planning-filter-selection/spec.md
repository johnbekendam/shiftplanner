# Planning Filter Selection — Spec

## Problem

The planning page has two filter cards: workcenters and shifts. A planner
must click each checkbox one at a time to show or hide items. There is no
fast way to select all or clear all.

The page keeps the selection in `sessionStorage`. The selection is lost
when the tab closes, and it does not move to another browser or device.

## Solution

1. Each filter card header gets an "All" checkbox.
2. The server stores the selection for each user. The page loads it on
   each visit and saves each change at once.

## Key decisions

- **Store the selection on the server.** The selection follows the user
  across browsers and devices. The `sessionStorage` code goes away.
- **Store the hidden ids, not the selected ids.** A JSON column on `users`
  holds the hidden workcenter ids and the hidden shift ids. An item that
  is not hidden shows. A new workcenter or shift thus shows automatically.
  The id of a deleted item has no effect.
- **Load through the page props.** `SchedulingController@index` sends the
  hidden ids as a prop. The page starts from these ids.
- **Save each change at once.** Each change sends a background
  `axios.put` to `/planning/filter`. The endpoint returns 204. There is no
  Inertia reload and no Save button. This is the same pattern as the
  existing `/planning/verify` call.
- **Validate the request.** The endpoint accepts two arrays of integers.
  Each array can be empty.
- **Use a header checkbox.** Each card header shows a `CheckboxInput`
  with the label "All". It is checked when all items show. A click when
  all items show hides all items. A click in all other cases shows all
  items. The report and employee tables use the same pattern.
- **Add the i18n key `scheduling.filter_all`** in `en.json`.

## Non-goals

- No server-side filtering of the planning data.
- No migration of the old `sessionStorage` values. The filter shows all
  items once, then keeps the new selection.
- No change to other pages.
