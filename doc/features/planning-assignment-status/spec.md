# Planning Assignment Status

## Problem

The planning table does not show whether an employee knows about an assignment. A planner also needs sent planning to stay stable after later publication changes.

## Solution

Show assignment status with text color in the planning table:

- An unfixed assignment uses the muted text color.
- A fixed assignment uses the normal text color.
- An informed assignment uses the success text color.

Informed status has visual precedence over fixed status. An informed assignment always uses the success text color.

When the system sends a Planning email, it marks every assignment listed in that email as both informed and fixed. This protects the emailed planning from later automatic replanning, including after a manager unpublishes the week.

The assignment payload includes the informed state. The planning table uses the existing success text token. No new theme token is needed.

## Key decisions

- The state colors apply to employee names in the planning table.
- A fixed but uninformed assignment uses the normal text color.
- An informed assignment uses the success text color, whether or not it was already fixed.
- Sending a Planning email fixes every assignment listed in that email.
- The existing per-assignment `informed_at` field remains the source of truth.

## Non-goals

- Do not change manual fixed or un fixed actions.
- Do not change which assignments the Planning email lists.
- Do not add a new Theme Builder color setting.
- Do not change employee-facing planning pages or email content.
