# Fresh Planning Generation

## Problem

Generate Planning seeds the planner with existing movable assignments. An old draft can affect capacity, workload, overlap, and optimizer choices. The result can be worse than a fresh solve.

The Clear Planning action exists to remove these assignments before a new solve. This adds a destructive action to a normal planning workflow.

## Solution

Generate Planning must start each cycle with only fixed assignments and assignments in published workcenter-weeks. It must not use movable assignments from the current draft as solver input.

The `Allow autoplanner` setting does not make published assignments movable. When it is enabled, Generate Planning may fill empty spots in that published workcenter-week only. It must not move, replace, or remove an existing published assignment. When it is disabled, the published workcenter-week has no planner spots.

After a successful solve, the generator must replace the cycle's movable assignments with the new solution. Fixed assignments and assignments in published workcenter-weeks must remain unchanged.

The generator must apply the replacement in a database transaction. If solving fails, the existing movable assignments must remain unchanged.

## Key decisions

- A movable assignment is an assignment with `fixed = false` that belongs to an unpublished workcenter-week.
- Fixed assignments remain solver input and remain protected.
- Published workcenter-week assignments remain solver input and remain protected.
- `Allow autoplanner` permits fill-only planning for empty spots in a published workcenter-week.
- Generate Planning remains the only planning action needed for a fresh replan.
- Clear Planning is removed from the planning page and its route, controller, language keys, and tests.
- Existing generation change summaries continue to describe the replacement.

## Non-goals

- Do not change planning rules or eligibility rules.
- Do not change the meaning of fixed assignments.
- Do not change publication or planner-open behavior.
- Do not add an undo action.
