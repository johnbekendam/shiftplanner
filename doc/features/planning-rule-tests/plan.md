# Planning Rule Tests — Plan

Status: in progress — 12/15

Each step is one commit on the `work` branch. Each rule step follows the
same order: write the control and rule tests, see them fail, fix the
planner if a defect shows, and run the full suite.

- [x] 1. Add the `BuildsPlanningScenarios` trait in `tests/Feature/Planning/`. Move the existing planner tests into the new files. Only the location changes. All tests stay green.
- [x] 2. `EligibilityTest`: holiday, unavailable and missing availability, `not_preferred` still assignable, workcenter hard-mode restriction, and shift overlap.
- [x] 3. Hard `not_preferred_shift`: `PlanEligibility` excludes `not_preferred` cells. Update the `planning-rules` and `scheduling-engine` specs.
- [x] 4. `max_hours_per_week`: hard (exclusion, exact limit, `weekly_hours × 2` cap, unfulfilled reason) and soft (control and rule, cost for each hour over).
- [x] 5. `max_shifts_per_day`: hard (exclusion, exact limit, unfulfilled reason) and soft (control and rule, cost for each shift over).
- [x] 6. `competence_required`: hard (exclusion, two competences on one workcenter, unfulfilled reason) and soft (control and rule, severity conflict).
- [x] 7. `business_line_preference`: hard (exclusion, unfulfilled reason) and soft (control and rule, severity conflict).
- [x] 8. `not_preferred_shift`, soft: control and rule, coverage before the rule, severity conflict.
- [x] 9. `equal_workload`: absolute hours (not a percentage), fixed and published hours count, and coverage comes first.
- [x] 10. `alternating_shift_pair`: second week against the generated first week, no preference for both or neither member, opposite shift satisfies the rule, unrelated shifts, severity conflict.
- [x] 11. `SpotsAndLockingTest`, spots: weekday-default capacity, date overrides, the cycle bounds (first and last day, and which hours count toward the cap), archived workcenters, and a cell with fewer candidates than spots.
- [x] 12. `SpotsAndLockingTest`, locking: the second week of a cycle is locked and frozen. Fixed and published rows staying in place under fairness pressure landed in step 9.
- [ ] 13. `SpotsAndLockingTest`, result: the run records `removed` changes, and created rows are not fixed.
- [ ] 14. `ObjectiveTiersTest`: coverage before fairness, fairness before soft rules, the optimizer uses fill, relocate, and swap moves, and a fill move that raises the highest hour total still wins (the `W1` and `W2` weight mutants survived step 9).
- [ ] 15. Run the one-off mutation script. Add a test or fix for each surviving rule mutant. Report the result and each planner fix.
