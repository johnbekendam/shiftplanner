# Weekly Hours Distribution Cap

## Problem

The hard `max_hours_per_week` rule limits total hours across a two-week planning cycle. It does not limit either week by itself.

The planner can assign most cycle hours in one week and few or no hours in the other week. This creates an uneven workload even when the cycle total is valid.

## Solution

Extend the hard `max_hours_per_week` rule with a per-week limit. Keep the existing two-week limit.

For an employee with `weekly_hours` of $H$:

- The two-week cycle limit is $2H$.
- Each Monday-Sunday week limit is $H + 4$.

Both limits use the existing rounded cap-hours. An assignment is eligible only when it stays within both limits.

Apply both limits to automatic generation and manual assignment. Show a distinct reason when the weekly limit blocks a manual assignment.

## Key Decisions

- Extend the existing rule. Do not add a separate planning rule.
- Use a fixed four-hour weekly allowance. Do not add rule configuration.
- Use the two Monday-Sunday weeks in the existing planning cycle.
- Enforce the weekly limit in automatic and manual planning.
- Keep existing assignments that exceed the weekly limit. Block new assignments in that week.
- Use a distinct weekly-limit reason. Keep the existing reason for the two-week cycle limit.
- Use rounded cap-hours for both limits. This keeps one hour-counting model.
- Leave soft `max_hours_per_week` scoring unchanged. The soft rule continues to score only two-week cycle excess.

## Non-Goals

- Do not rebalance existing assignments automatically.
- Do not block publishing because an existing week exceeds the limit.
- Do not add an override workflow.
- Do not change the weekly-hours field or its allowed values.
- Do not change other planning rules.
