Status: in progress — 1/3

- [x] 1. Add the rounded cap duration to `Shift` (4-hour step, ties up, minimum 4) with unit tests.
- [ ] 2. Use the rounded duration in the autoplanner hard rule and soft-rule cost, through a new capped-hours total on `PlanAssignmentSet`. Test with 8.25-hour and 9-hour shifts.
- [ ] 3. Use the rounded duration in the manual-planning hard cap and update the rule hint text. Test the picker and the assignment endpoint.
