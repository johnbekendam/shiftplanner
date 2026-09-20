# Assignee Name Color

## Problem

The week table on the planning page shows a pin icon next to the name of a fixed employee. The icon adds noise, and the table does not show which names are still drafts.

## Solution

Remove the pin icon. Show the state of an assignment with the color of the name:

- **Gray:** the assignment is neither fixed nor published. Generate can still change it.
- **Standard text color:** the assignment is fixed, or its workcenter is published for the week.

`WorkcenterScheduleCard` passes `published` to each `ShiftWeekTable`. The table uses the existing muted and primary text tokens. No new color token is added.

## Key Decisions

- **Color only.** The state shows in the name color. The Freeze and Unfreeze menu on the name still shows and changes the fixed state.
- **Fixed and published look the same.** Both mean Generate does not move the assignment.
- **Reuse the text tokens.** `--color-text-muted` for a draft and `--color-text-primary` for the rest.

## Non-goals

- A new marker for fixed assignments.
- A change to the sort order (fixed names stay first).
- A change to the Freeze and Unfreeze menu.
