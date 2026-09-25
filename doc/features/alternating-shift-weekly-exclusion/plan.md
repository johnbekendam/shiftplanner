# Alternating Shift Weekly Exclusion — Plan

Status: done — 5/5

- [x] 1. Data: a migration sets existing pair rows to `mode = hard`, `severity = null`. `PlanningRuleController` always stores these values for a pair and ignores client mode and severity.
- [x] 2. Automatic planning: `PlanEligibility` rejects a candidate who holds the other pair shift in the same Monday–Sunday week. Remove the pair cost from `PlanScorer`, the pair from `PlanSoftRules`, and the previous-week assignments from `PlanProblem` and `HeuristicPlanGenerator`. Rewrite `AlternatingShiftPairRuleTest`.
- [x] 3. Manual planning: `SchedulingEligibility` reports a pair violation for the proposed assignment. The eligible-employee endpoint excludes the employee. The assignment endpoint rejects the request with a validation error on `employee_id`.
- [x] 4. Rules page: a pair row and the add form show `Hard` as mode, with no severity input. Add a hint that the two shifts cannot be combined in the same week.
- [x] 5. Docs: update the alternating-pair parts of `doc/features/planning-rules/spec.md` to the new rule.
