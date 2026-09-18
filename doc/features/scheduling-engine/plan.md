# Scheduling Engine — Plan

Status: done — 5/5

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

- [x] 3. **Trigger UX: cycle resolution, run status, polling.**
  `SchedulingController@index` gained `generationRun` — the most
  recent `PlanGenerationRun` for the resolved cycle (`{ status, error
  }`, or `null` if none has ever run). `Scheduling.vue`: the Generate
  button's label now carries the cycle's date range
  (`planning.generate_cycle`, e.g. "Generate Sep 7 – Sep 20");
  while `generationRun.status` is `pending`/`running` it's disabled
  and reads "Generating…"; a `failed` run shows its error text next
  to the button, relabeled "Generate again" and re-enabled. Polling:
  a `setTimeout`-based loop (3s interval) reloads `generationRun` +
  `weekCells` + `coverage` (not the whole page) while a run is active,
  self-perpetuating via the reload's own `onFinish` callback rather
  than relying on a prop-change watcher alone — that watcher still
  starts the first poll (e.g. right after clicking Generate) and stops
  it once a reload's response resolves the run, but the reload chain
  doesn't depend on Vue treating each response as a distinct object
  reference. Cleaned up via `onBeforeUnmount`.

  Extended `tests/Feature/SchedulingIndexTest.php` (+4: null with no
  cycle, null with no run yet, reflects the latest of several runs,
  ignores a run from a different cycle) and `tests/js/Scheduling.test.js`
  (+8: dated label, disabled/"Generating…" for pending and running,
  failed state's error text and re-enabled "Generate again", error
  hidden once not failed, polling starts/continues/stops, no poll on
  an already-resolved mount, poll cleared on unmount). The 409 on a
  concurrent run was already covered in step 1's
  `PlanGenerationControllerTest.php`. 661 PHP + 621 JS tests passing,
  Pint clean, `npm run build` green.

  **Revised after this step shipped**: Generate no longer targets one
  cycle — see `spec.md`'s "Cycle and scope" (Reversed mid-build). Route
  is now `POST /planning/generate` (no params); `PlanGenerationController`
  loops `PlanningCycle::allWithinPeriod()` and creates/dispatches one
  run per cycle. `SchedulingController@index` gained `planningPeriod`
  (`{ start, end }` from Settings, or `null`) and `generationStatus`
  (`{ active, failedCount, firstError }`, aggregated over every
  cycle's latest run) — `cycleStart`/`generationRun` (the viewed
  cycle's own run) stay as they were, now driving nothing directly in
  the UI but carried forward for step 4's per-week change
  summary/unfulfilled display. `Scheduling.vue`'s button and poll now
  key off `planningPeriod`/`generationStatus` instead. New
  `PlanningCycle::allWithinPeriod()`, tested in `PlanningCycleTest.php`
  (+4). `PlanGenerationControllerTest.php` rewritten for the new route
  and multi-cycle semantics (9 tests). `SchedulingIndexTest.php` (+7)
  and `Scheduling.test.js` (button/polling tests rewritten against the
  new props) extended for `planningPeriod`/`generationStatus`. 674 PHP
  + 622 JS tests passing, Pint clean, `npm run build` green.

- [x] 4. **Change summary + inline unfulfilled reasons.** New
  `GenerationChangeSummary.vue`: a dismissible panel (shown when the
  viewed cycle's run is `done` with a non-empty `changes` list, keyed
  by `generationRun.id` so a new run resets any prior dismiss/expand
  state) showing counts — added/moved/removed — with an expand toggle
  revealing a per-line list. Pairing into "moved" happens client-side,
  by employee, not by matching dates (a relocate can land on a
  different day in the cycle than it left); `spec.md` corrected to
  match (dropped the "that day" qualifier from the original draft).
  `ShiftWeekTable.vue`'s "Open" cells gained an `unfulfilled` prop —
  a warning-triangle icon with a `title` tooltip appears next to the
  existing "+" button (which stays fully clickable) when the cell
  matches an entry in the viewed cycle's run; threaded down through
  `WorkcenterScheduleCard.vue` from `Scheduling.vue`, which passes `[]`
  unless the run is `done`.

  `SchedulingController::latestGenerationRun()` now resolves the
  stored `changes`/`unfulfilled` (raw employee/workcenter/shift IDs)
  to display names via bulk lookups, falling back to `#id` for a
  since-deleted entity; also gained the run's own `id`, needed for the
  panel's remount key.

  New `tests/js/GenerationChangeSummary.test.js` (8: empty state,
  count summary, move-pairing including the uneven-count and
  different-employee-substitute cases, expand/collapse, dismiss).
  Extended `tests/js/ShiftWeekTable.test.js` (+5: icon shown/hidden
  per exact workcenter+shift+date match, both reason texts, the fill
  button still works alongside it) and `tests/js/Scheduling.test.js`
  (+6: summary/unfulfilled shown only once `done`, hidden for every
  other status or an empty change list, correct props reach the
  children). `tests/Feature/SchedulingIndexTest.php` (+3: `id` and
  empty arrays on a non-`done` run, names resolved correctly, the
  `#id` fallback). 677 PHP + 641 JS tests passing, Pint clean,
  `npm run build` green.

- [x] 5. **Docs and full checks.** `doc/roadmap.md`: phase 5's status
  row marked `Done`; its section rewritten around what actually
  shipped (four move types including the added-mid-build substitute,
  the period-wide Generate reversal, fixed/published locking, the UI).
  Fixed several now-stale cross-references left over from the
  Python/`/solve` plan and from phase 5 having been "still doesn't
  exist" at the time they were written: phase 3's and phase 4's
  "still open" notes, phase 6's status row and closing paragraph
  (published weeks are now actually protected by a real
  `PlanGenerator`, not just a documented invariant for future work).
  `doc/concept.md`: the optimizer paragraph's stale "not built in the
  first increment" closing sentence replaced with what shipped; the
  Deferred Decisions list updated — fairness definitions and the
  problem/solution shape are resolved (only planning cadence, i.e. no
  automatic/scheduled regeneration, stays open), and the date-specific-
  exceptions entry now notes its shelving condition (phase 5 existing)
  is met, so it's open-but-unblocked rather than still waiting.

  Pint clean (repo-wide check still reports its existing 14-file
  unrelated baseline, none of them touched by this feature). Full PHP
  suite green (677 passed). Full JS suite green (641 passed).
  `npm run build` green. `php artisan migrate` clean against a fresh
  database. No separate test command to document, unlike the
  abandoned Python plan — everything runs through this repo's normal
  `php artisan test` / `npm run test`.

## Not done / deferred

- Everything under the spec's non-goals: a staging/accept flow,
  per-cell change highlighting, a finer unfulfilled-reason taxonomy,
  completion notifications, per-workcenter-scoped runs, run history
  UI beyond the most recent run, and any future reconsideration of a
  separate solver service.
- Manual browser pass — AGENTS.md leaves UI verification to the user.
