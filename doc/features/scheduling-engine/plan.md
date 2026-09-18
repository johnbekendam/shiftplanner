# Scheduling Engine — Plan

Status: in progress — 2/5

Spec: `spec.md`. Reversed mid-design from a separate Python/OR-Tools
service to an in-process PHP heuristic — this plan reflects that
reversal; there is no earlier version to reconcile against since no
code was written before the reversal. Each step should leave the repo
in a working, tested state.

- [x] 1. **Data model, job scaffold, greedy construction only.**
  New migration `plan_generation_runs` (`cycle_start`, `status`,
  `changes` json, `unfulfilled` json, `error`, timestamps) +
  `PlanGenerationRun` model. Interface is
  `App\Services\Planning\PlanGeneratorContract` (not
  `App\Contracts\PlanGenerator` as first drafted — matches this
  project's existing convention of a `*Contract` interface living
  alongside its implementations in a themed `Services` subfolder, the
  same shape `App\Services\Auth\AuthServiceContract` already uses),
  bound to `HeuristicPlanGenerator` in `AppServiceProvider`.
  `PlanProblem`/`PlanSolution` readonly value objects. New
  `PlanAssignmentSet` (mutable working state — every current
  assignment in the cycle, locked or not, with overlap/hour/day-count
  helpers) and `PlanEligibility` (hard-rule lookups precomputed from a
  `PlanProblem`) — both written to be shared with the hill-climbing
  phase, not just construction. `GreedyConstructor` implements the
  most-constrained-first, least-loaded-candidate fill described in
  `spec.md`, tracking which cells *ever* had a candidate during the
  search so an unfulfilled spot is correctly classified
  `hard_cap_reached` (had one, lost it to a cap) vs.
  `no_eligible_employee` (never had one) — the first draft only
  checked final-state eligibility and conflated the two. New
  `PlanningCycle::containing()` helper for the 2-week cycle boundary
  math (`planning-rules`' anchor-on-`period_start` rule), shared by
  the controller and the `/planning` page.

  `HeuristicPlanGenerator::generate()` builds the problem, seeds
  `PlanAssignmentSet` with *every* current cycle assignment (not just
  locked ones — needed so construction sees true current occupancy
  and never double-books a cell a non-locked manual assignment already
  fills), runs `GreedyConstructor`, then diffs the result against the
  cycle's current non-locked assignments and applies it
  transactionally. One real bug found and fixed here: computing that
  diff via an `Illuminate\Database\Eloquent\Collection::keyBy()`
  result and then calling `except()` on it silently filters by the
  model's *primary key*, not the custom `keyBy()` key (Eloquent
  Collection overrides `except()`/`only()` for that) — it looked
  like every previous assignment was being deleted, since a plain
  feature test caught it but an unfiltered read didn't. Fixed by
  converting to a plain `Support\Collection` before `keyBy()`.

  `App\Jobs\GeneratePlan` (queued, `tries = 3`) sets the run to
  `running`, calls the generator, and marks it `failed` with the
  exception message via its `failed()` hook once retries are
  exhausted. `PlanGenerationController@store`
  (`POST /planning/cycles/{cycleStart}/generate`,
  `planning.cycles.generate`) validates the date is a real cycle
  boundary, rejects (`409`) a second pending/running run for the same
  cycle, creates the `pending` row, and dispatches the job.
  `SchedulingController@index` now passes `cycleStart` (null when no
  `period_start` is configured). `Scheduling.vue` shows a minimal
  Generate button when a cycle resolves — no polling, change summary,
  or unfulfilled UI yet, per this step's scope.

  New: `tests/Feature/HeuristicPlanGeneratorTest.php` (11 —
  construction, eligibility filtering per hard rule, most-constrained
  ordering, both unfulfilled reasons, fixed/published/pre-existing
  locking), `PlanGenerationControllerTest.php` (8),
  `GeneratePlanJobTest.php` (2), `PlanningCycleTest.php` (6). Extended
  `SchedulingIndexTest.php` (+2) and `Scheduling.test.js` (+2). 648 PHP
  + 615 JS tests passing, Pint clean on every touched/new file (the
  repo-wide check still reports its existing unrelated baseline),
  `npm run build` green, `php artisan migrate` clean on a fresh
  database.

- [x] 2. **Hill-climbing optimization.** Added `PlanSoftRules` (soft-
  rule data, parsed the same way `PlanEligibility` parses hard rules)
  and `PlanScorer` (the tiered `W1`/`W2`/`W3` penalty — coverage
  shortfall, max-hours-when-`equal_workload`-present, and the sum of
  every soft term: severity-weighted `not_preferred_shift`,
  `alternating_shift_pair` against `previous_week_assignments`, soft
  `competence_required`/`business_line_preference`, and soft caps
  costed per unit over). `HillClimbOptimizer` runs first-improvement
  local search (seeded `mt_srand`, 2,000-iteration/10s budget) over
  **four** move types, not the three `spec.md` first named — a
  **substitute** move (replace one cell's occupant with a different
  eligible employee, no compensating assignment) had to be added once
  it was clear fill+relocate+swap couldn't rebalance a fully-staffed
  cycle where one employee holds nothing at all. Move generation is
  fully exhaustive each pass rather than the "targeted toward the
  max-hours employee" generator `spec.md` first planned — once
  generation is exhaustive rather than sampled, a rebalancing move is
  already in the candidate set by construction, so a separate
  targeting mechanism would only duplicate it; `spec.md` updated to
  match both changes.

  Unfulfilled-reason classification moved out of `GreedyConstructor`
  entirely: `PlanAssignmentSet` now tracks "ever had a candidate" as
  shared state across both phases (`noteCandidateSeen`/`hadCandidate`),
  and `HeuristicPlanGenerator` computes the final unfulfilled list once
  after optimization settles — construction's own list would go stale,
  since a relocate can change *which* cell ends up open without
  changing how many do.

  New `tests/Feature/HillClimbOptimizerTest.php` (9 tests, all
  end-to-end through `generate()`): `equal_workload` rebalancing a
  fully-staffed cycle via substitute, a fixed and a published
  assignment each surviving that same rebalancing pressure untouched,
  `not_preferred_shift` correcting a bad construction pick, soft
  `max_shifts_per_day` and soft `max_hours_per_week` each preferring
  the under-cap candidate, `alternating_shift_pair` reaching into
  `previous_week_assignments`, and soft `competence_required`/
  `business_line_preference` each preferring the matching candidate.
  657 PHP tests passing (was 648), 615 JS unaffected, Pint clean,
  `npm run build` green.

- [ ] 3. **Trigger UX: cycle resolution, run status, polling.**
  `/planning` resolves the cycle containing the viewed week (same
  boundary math as `planning-rules`) and shows its date range on the
  Generate button. While the viewed cycle has a pending/running run,
  the button shows a disabled "Generating…" state and the page polls
  until it resolves; a failed run shows its error with a Generate
  again affordance. Feature + Vitest coverage for cycle resolution,
  the 409 on a concurrent run, and the polling/failed states.

- [ ] 4. **Change summary + inline unfulfilled reasons.** A dismissible
  panel above the week cards renders a `done` run's `changes` (counts
  plus an expandable added/removed line list; an employee in both an
  added and removed row that day reads as a move). `ShiftWeekTable`'s
  "Open" cells show an icon + tooltip when the viewed cycle's most
  recent run left that spot unfulfilled. Vitest coverage for both.

- [ ] 5. **Docs and full checks.** `doc/roadmap.md` — phase 5 row and
  section: scheduling engine shipped, what it covers, what's still
  advisory-only (a manager must still review). `doc/concept.md` if it
  still names anything provisional about the optimizer. Pint clean.
  Full PHP suite green (this feature's tests included — no separate
  test command to document, unlike the abandoned Python plan). Full
  JS suite green. `npm run build` green. `php artisan migrate` clean
  on a fresh database.

## Not done / deferred

- Everything under the spec's non-goals: a staging/accept flow,
  per-cell change highlighting, a finer unfulfilled-reason taxonomy,
  completion notifications, per-workcenter-scoped runs, run history
  UI beyond the most recent run, and any future reconsideration of a
  separate solver service.
- Manual browser pass — AGENTS.md leaves UI verification to the user.
