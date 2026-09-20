# Planning Rule Tests — Spec

Every planning rule needs a test that fails when the rule stops working.
This feature adds those tests. It also fixes one known gap in the planner.

## Problem

A mutation check of `app/Services/Planning/` showed weak tests. About 55
rule-level changes to the source were tried, one at a time. 29 of them
did not make any test fail.

- Hard `max_hours_per_week`, `competence_required`, and
  `business_line_preference` have no test.
- The soft tests for `competence_required`, `business_line_preference`,
  `max_shifts_per_day`, and `max_hours_per_week` pass with the rule
  removed. The greedy step already picks the correct employee, because
  it takes the least-loaded candidate and breaks ties by lowest id.
- Hard eligibility has no test for unavailable or missing availability,
  the workcenter hard-mode restriction, or shift overlap.
- The tests for fixed and published assignments pass with the lock
  removed. The fairness optimum never needs to move those rows.
- No test covers weekday-default capacity, the last day of the cycle,
  archived workcenters, or `removed` entries in the run changes.
- No test covers severity, the second week of the alternating pair, or
  the order of the objective tiers.

One gap is a defect. The rules page offers `hard` for
`not_preferred_shift`, and new rules default to `hard`. The planner
ignores such a rule.

## Solution

### Test structure

Add `tests/Feature/Planning/`. It holds these files:

- `BuildsPlanningScenarios` — a shared trait. It replaces the helpers
  that `HeuristicPlanGeneratorTest` and `HillClimbOptimizerTest` both
  define.
- One test file for each of the seven rule types.
- `EligibilityTest` — holidays, availability, workcenter restriction,
  and shift overlap.
- `SpotsAndLockingTest` — capacity resolution, fixed and published
  assignments, and the recorded changes.
- `ObjectiveTiersTest` — coverage before fairness, and fairness before
  soft rules.

The existing planner tests move into these files. A test stays only if
it pins real behavior. A test that passes with its rule removed is
rewritten or dropped.

### Control and rule runs

Each scenario makes the planner pick the wrong candidate when no rule
exists. The wrong candidate is created first and has equal or lower
load. The test then runs twice:

1. **Control.** No rule exists. The violating candidate wins.
2. **Rule.** The rule exists. The compliant candidate wins.

The control proves that the rule caused the result. It runs with every
suite run, so a scenario cannot become vacuous later without notice.

### Cases for each rule

- **Hard rules** (`max_hours_per_week`, `max_shifts_per_day`,
  `competence_required`, `business_line_preference`,
  `not_preferred_shift`):
  - The planner excludes the violating candidate.
  - A candidate exactly at the limit is still assigned.
  - When only violating candidates exist, the spot stays open with the
    correct unfulfilled reason.
  - Max hours also covers the cap of `weekly_hours × 2`.
  - Competence also covers two required competences on one workcenter.
- **Soft rules:**
  - The compliant candidate wins.
  - The planner still breaks the rule when that is the only way to fill
    the spot.
  - A higher severity wins a conflict with a lower severity.
  - Max hours and max shifts cost more for each unit over the cap.
- **`equal_workload`:**
  - It compares absolute hours, not a percentage of `weekly_hours`.
  - Fixed and published hours count toward the totals.
  - It applies only after coverage.
- **`alternating_shift_pair`:**
  - The second week compares with the generated first week.
  - No preference exists when the earlier day has both or neither
    member.
  - The opposite shift satisfies the rule even when the repeated shift
    is also present.
  - Unrelated shifts do not change the result.
  - A higher severity wins a conflict.

### Engine cases

- Weekday-default capacity, the last day of the cycle, and archived
  workcenters.
- A fixed or published assignment stays in place, also when a move
  would improve the score. The scenario must make the optimizer want
  that move.
- Locking and freezing of the second week of a cycle.
- The run records `removed` changes. Created rows are not fixed.

### Hard not-preferred shift

When `not_preferred_shift` is hard, `PlanEligibility` treats a
`not_preferred` cell as ineligible for that employee. Soft behavior
does not change. Update the `planning-rules` and `scheduling-engine`
specs, which describe this rule as a penalty only.

### Verification

Run the one-off mutation script when the steps are done. It changes the
planner source one rule at a time and runs the planner tests. The goal
is no surviving rule mutant, except mutants that do not change
behavior. The script stays outside the repository.

## Key decisions

- **One test file for each rule.** The two current files split tests by
  engine phase. This makes it hard to see if a rule has a test.
- **Control and rule runs.** A second run with no rule is a permanent
  check. A one-time mutation check cannot give this.
- **Hard not-preferred excludes the cell.** The rules page already
  offers hard mode and uses it as the default. The planner now does what
  the page says.
- **Fix defects in the same step.** When a new test exposes a planner
  defect, the step fixes it and commits the test and the fix together.
  The final report lists each fix.
- **A one-off script, not Infection.** Infection needs a coverage
  driver that this machine does not have. The repository has no CI to
  run a score gate. The control runs already prevent vacuous tests.

## Non-goals

- Enforcing rules on manual planning.
- Changing the planner algorithm, except fixes that new tests expose.
- Frontend tests.
- CI, or a permanent mutation-testing tool.
