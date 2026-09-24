# Employee Picker Eligibility

## Problem

The manual planning picker shows only employees who can take the selected shift. A planner cannot see why another confirmed employee is absent from the list.

## Solution

Show every confirmed employee in the manual planning picker. Keep eligible employees selectable. Show blocked employees as disabled rows with one primary reason.

The picker remains searchable. It loads all confirmed employees when the planner opens it.

Use the same eligibility result for the picker and assignment creation. This keeps the displayed reason consistent with server validation.

## Key decisions

- Include all confirmed employees. Unconfirmed employees do not appear because the picker is an operational planning tool.
- Use one list. This lets a planner search for any confirmed employee without changing filters.
- Show one primary blocking reason. Use the assignment validation order to select that reason.
- Disable blocked rows. A planner cannot submit an assignment that the server will reject.
- Keep eligible employees first only if the current name ordering supports it without a separate group. Do not split the list into sections.
- Keep the current warnings for a not-preferred shift and a not-preferred workcenter.
- Load the complete confirmed employee list. Client-side search is sufficient for the current employee count.

## Non-goals

- Do not include unconfirmed employees.
- Do not change automatic plan generation.
- Do not create a shared reason model for automatic and manual planning.
- Do not show all applicable blocking reasons.
- Do not change assignment capacity or eligibility rules.
