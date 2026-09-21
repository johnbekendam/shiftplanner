# Planning Assignment Status

## Problem

The planning table does not show whether an employee knows about an assignment. A planner also needs sent planning to stay stable after later publication changes.

## Solution

Show assignment status with a full-width badge in the planning table:

- An unfixed assignment uses a muted badge.
- A fixed assignment uses a standard badge.
- An informed assignment uses a success badge.

Informed status has visual precedence over fixed status. An informed assignment always uses the success badge.

Hovering over or focusing an assignment shows a tooltip with the full employee name and its state.

When the system sends a Planning email, it marks every assignment listed in that email as both informed and fixed. This protects the emailed planning from later automatic replanning, including after a manager unpublishes the week.

The assignment payload includes the informed state. The planning table uses the existing success text token. No new theme token is needed.

## Key decisions

- The state badges appear beside employee names in the planning table.
- A fixed but uninformed assignment uses the standard badge.
- An informed assignment uses the success badge, whether or not it was already fixed.
- Sending a Planning email fixes every assignment listed in that email.
- The existing per-assignment `informed_at` field remains the source of truth.

## Non-goals

- Do not change manual fixed or unfixed actions.
- Do not change which assignments the Planning email lists.
- Reuse the existing info and success badge tokens.
- Do not change employee-facing planning pages or email content.
