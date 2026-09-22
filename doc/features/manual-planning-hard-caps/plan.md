Status: done - 4/4

- [x] 1. Added shared hard-cap evaluation to `SchedulingEligibility`, using the existing 14-day `PlanningCycle` and all assignments across workcenters.
- [x] 2. Applied hard-cap filtering to the eligible-employee endpoint and hard-cap validation to manual assignment creation.
- [x] 3. Added focused feature tests for picker filtering and direct-request rejection for daily-shift and planning-cycle hour caps, including cross-workcenter existing assignments.
- [x] 4. Verification passed: focused PHP tests (40), full PHP suite (870), JavaScript suite (746), and `npm run build`.
