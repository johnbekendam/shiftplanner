# Scheduling Engine — Spec

Roadmap phase 5. Depends on phase 3 (`features/scheduling*/`,
`features/workcenter-shift-assignments/`), phase 4 (fairness
definitions, resolved — see `doc/roadmap.md`'s phase 4 section), and
`features/planning-rules/` (the rule data this feature is the first
consumer of). `features/publish-per-workcenter/` is a direct
prerequisite: the planner's "never touch a published week" rule needs
per-workcenter publish granularity, which didn't exist before that
feature shipped.

**Reversed mid-design from a separate Python/OR-Tools service to an
in-process PHP heuristic** — see Key decisions for why. Everything
about the data model, cycle/scope rules, and locking behavior below
was decided before that reversal and carries over unchanged; only the
solving mechanism itself changed.

## Problem

`planning_rules` has no consumer. Every assignment on `/planning` is
manual today. A manager building a multi-week, multi-workcenter roster
by hand, fairly, against a dozen constraints, does not scale.

## Solution

### Architecture

- `App\Services\Planning\PlanGeneratorContract` — one method,
  `generate(PlanGenerationRun $run): void`. Matches this project's
  existing convention (`App\Services\Auth\AuthServiceContract`): a
  `*Contract` interface living alongside its implementation in a
  themed `Services` subfolder, rather than a generic `App\Contracts`
  namespace as first drafted here. `App\Services\Planning\
  HeuristicPlanGenerator` is the only implementation. No separate
  service, container, language runtime, or network boundary — it runs
  in-process, inside the queue worker.
- A `GeneratePlan` queued job resolves the bound contract and
  calls `generate()`. It's still a queued job, not a synchronous
  request: the heuristic's iteration budget (below) can take several
  seconds, too slow to block an HTTP response, and the existing
  `/planning` trigger UX (pending/running/done/failed, polling) is
  unaffected by the reversal — it was never specific to a network
  call.
- Two internal value objects mark the seam the old JSON wire contract
  used to sit at, kept because they're still useful as a clean
  interface even with no process boundary to cross: `PlanProblem`
  (everything the planner reads — see below) and `PlanSolution`
  (`assignments`, `unfulfilled`). `HeuristicPlanGenerator` builds a
  `PlanProblem`, runs the heuristic, returns a `PlanSolution`.

### Cycle and scope

- A **cycle** is the existing 2-week concept from `planning-rules`:
  non-overlapping 2-week blocks starting from the Monday of the week
  containing `PlanningSettings.period_start`.
- One Generate action always covers **every** workcenter for one
  cycle — never a subset, and independent of `/planning`'s
  view-only workcenter/shift filter checkboxes. `equal_workload`'s
  fairness pool needs the whole cross-workcenter picture; scoping a
  run to a subset would silently break that comparison.
- `/planning` resolves "the cycle containing the currently viewed
  week" (same boundary math `planning-rules` already uses) and shows
  one Generate action for it, with its date range
  (e.g. "Generate Sep 7 – Sep 20").

### What the planner may and may not touch

The planner is a full re-optimizer over the cycle, not a gap-filler —
confirmed correction from the original design pass, unaffected by the
Python-to-PHP reversal. It may reassign or remove any existing
assignment in the cycle except:

- An assignment marked **fixed**.
- Any assignment inside a **published** `(workcenter, week)` pair
  (`features/publish-per-workcenter/`) — fully untouched, including
  its still-open spots. "Solving empty spots in a published round is
  blocked, for now."

A cycle with mixed publish state is solved as far as it can be: an
unpublished `(workcenter, week)` pair is optimized freely; a published
pair's assignments count toward `equal_workload`'s and
`max_hours_per_week`'s hour totals as locked context, but nothing
there moves.

A rerun is therefore **not** guaranteed to reproduce the previous
run's output for spots it already filled — a better cycle-wide
arrangement can move or drop a prior non-locked assignment, whether it
was placed by hand or by an earlier run. This is why change visibility
(below) exists.

### `PlanProblem` — what the planner reads

Built by `HeuristicPlanGenerator` from the same data sources
`SchedulingEligibility` and `planning_rules` already expose — no new
data model beyond `plan_generation_runs` (below):

- **Cycle**: start/end dates.
- **Employees** (the fairness pool, matching `planning-rules`'
  definition exactly): confirmed, `weekly_hours > 0`, each carrying
  their holidays, recurring availability (`weekday` + `shift_id` +
  `level`), workcenter modes (`hard`/`soft`), and competences.
- **Shifts**: id, start/end time (for overlap checks).
- **Spots**: one entry per `(workcenter, shift, date)` with
  spots > 0 in the cycle — the resolved count (override-or-weekday-
  default, same as `SchedulingController::spotsFor()`), and how many
  of those spots a locked assignment already occupies. The planner
  only ever decides the remainder.
- **Locked assignments**: every fixed or published assignment in the
  cycle, in full — needed so the planner respects overlap and hour
  totals for the employees holding them, even though it can't change
  them.
- **Previous-week assignments**: read-only, the week immediately
  before the cycle, for `alternating_shift_pair`'s "same weekday, 7
  days earlier" comparison on the cycle's first week.
- **Rules**: every `PlanningRule` row, verbatim (`type`, `mode`,
  `severity`, `config`).

### The heuristic

Two phases, run inside `HeuristicPlanGenerator`:

**1. Greedy construction.** Fills as many open spots as possible,
respecting hard constraints only (holiday/unavailable/workcenter/
competence eligibility, no overlapping shifts, and any rule marked
`hard`), ignoring every soft term. Spots are processed most-
constrained-first (fewest eligible candidates first — a standard
construction-heuristic ordering that fills the hardest-to-satisfy
spots before easier ones exhaust the candidate pool), and each spot's
candidate is the currently-least-loaded eligible employee, to start
already close to workload-balanced rather than relying entirely on
the optimization phase to fix a lopsided starting point.

**2. Hill-climbing optimization.** Repeatedly tries moves and keeps
any that improve a single scalar penalty score built from three
weighted terms, tiered by weight magnitude so a lower tier can never
outweigh a higher one (`W1 ≫ W2 ≫ W3` — the standard way to express
strict lexicographic tiers as one scalar for local search):

| Tier | Term | Weight |
| --- | --- | --- |
| 1 | Unfilled spots (coverage shortfall) | `W1` |
| 2 | The single highest employee's total hours (workload fairness, only when `equal_workload` exists) | `W2` |
| 3 | Σ `severity × amount` over every soft rule violation (`not_preferred_shift`, `alternating_shift_pair`, soft `competence_required`/`business_line_preference`, soft caps costed per hour/shift over) | `W3` |

Move types, each only ever proposed when it keeps every hard
constraint satisfied:

- **Fill**: assign an eligible employee to a currently-open spot.
- **Relocate**: move one employee's assignment to a different open,
  eligible spot.
- **Substitute**: replace the occupant of an already-*full* cell with
  a different eligible employee — added during implementation, once
  it was clear fill and swap together still couldn't rebalance a
  fully-staffed cycle where one employee holds nothing at all: fill
  needs an open cell (there isn't one), swap needs both sides to
  already hold something to trade (an unassigned employee holds
  nothing to offer). Substitute is the direct fix — it's also, in
  practice, the workhorse move for workload rebalancing generally,
  more often than swap.
- **Swap**: exchange two employees between their two assignments,
  when each is eligible for the other's cell.

Bare removal (drop an assignment, leave the spot open) is not a
standalone move — it would always cost `W1`, which nothing in tier 2
or 3 can outweigh by construction, so it's only ever useful as half of
a relocate, never accepted on its own.

**Move generation is exhaustive, not targeted or sampled**: every
pass enumerates every legal fill/relocate/substitute/swap over the
*entire* current state, not a random subset. The original plan here
was a separate "targeted" generator biased toward the current
maximum-hours employee, reasoned as necessary because a *sampled*
search could easily miss the specific move that unloads them. Once
substitute existed and full enumeration was the actual implementation
choice, that reasoning no longer applied: exhaustive generation
already contains every such move by construction, so a separate
targeting mechanism would only have duplicated candidates already in
the pool. First-improvement acceptance over a randomized shuffle of
the full set still finds an improving move whenever one exists.

**Stopping**: a fixed iteration budget (default 2,000) or a wall-clock
budget (default 10s), whichever comes first, or earlier if a full pass
over candidate moves finds no improving one ("local optimum reached").
Move order is randomized each pass (seeded via `mt_srand`, for
reproducible tests); the first improving move found is accepted
(first-improvement, not best-improvement — cheaper per iteration,
standard for this class of search).

**Unfulfilled reasons**: computed once, after both construction and
optimization settle — not by construction alone, since a relocate can
shift *which* cell ends up open without changing how many do (it
always trades a fill for a fill, net zero coverage change). A still-
open spot is classified `no_eligible_employee` (no candidate ever
cleared hard eligibility for that cell, across either phase) or
`hard_cap_reached` (one did at some point — including one that was
generated as a move but not chosen — but every one is blocked by a
hard cap or overlap
given the final assignment state).

### Applying the result

`HeuristicPlanGenerator`, in one transaction:

1. Loads every current non-locked `ShiftAssignment` in the cycle (not
   fixed, not in a published `(workcenter, week)` pair) — call this
   the previous movable set.
2. Diffs it against the `PlanSolution`'s `assignments`: rows only in
   the previous set are deleted; rows only in the solution are
   created; rows in both are left untouched (id, `fixed` flag
   unchanged).
3. Records a `PlanGenerationRun` (new table `plan_generation_runs`):
   `cycle_start`, `status` (`pending` → `running` → `done`/`failed`),
   `changes` (json: `[{ type: 'added'|'removed', employee_id,
   workcenter_id, shift_id, date }]`), `unfulfilled` (json, the
   solution's list verbatim), `error` (nullable text, set on
   `failed`), timestamps.

Only one `pending`/`running` run per `cycle_start` is allowed — a
second Generate click on the same cycle while one is in flight is
rejected (`409`). A `failed` run (an unexpected exception; the
heuristic itself never "can't connect" the way a network call could)
leaves the cycle untouched; a manager can click Generate again, which
starts a fresh run row.

### `/planning` UI

- **Trigger**: `POST /planning/cycles/{cycleStart}/generate`, admin
  gated, `cycleStart` date-constrained like `weekStart` elsewhere.
  Dispatches `GeneratePlan` and creates the `pending` run row
  immediately (so a concurrent second click is rejected even before
  the queue worker picks the job up).
- **In progress**: while the viewed cycle has a `pending`/`running`
  run, the Generate button is replaced with a disabled "Generating…"
  state and the page polls (`router.reload` on an interval) until the
  run resolves. A `failed` run shows its error and offers Generate
  again.
- **Change summary**: once a run is `done`, a dismissible panel above
  the week cards lists what changed — counts (added/removed,
  "moved" is not tracked as a distinct type; an employee appearing in
  both an added and a removed row that day reads as a move) and an
  expandable per-line list (employee, workcenter, shift, date). This
  is what makes "the manager reviews the draft" mean something now
  that a rerun can silently rearrange manual work.
- **Unfulfilled reasons**: an empty spot cell in `ShiftWeekTable`
  that the most recent run for its cycle left unfulfilled shows a
  small indicator (icon + tooltip) with the reason, instead of a
  plain "Open" — becomes stale the moment a manager fills it by hand,
  which is fine, it's advisory.

## Key decisions

- **PHP heuristic in-process, not a separate Python/OR-Tools
  service — reversed mid-design.** The original plan (predating this
  feature, baked into `doc/concept.md` from project inception) assumed
  a real constraint solver was necessary. That was only actually
  tested once `features/planning-rules/` had fully pinned down the
  constraint shape (eligibility, overlap, capped hours/shifts, a
  minimax fairness tier, severity-weighted soft preferences,
  alternating pairs) — at which point a second language, a second
  deployable service, a wire contract, and a separate CI/test
  toolchain, all for one queued job in an otherwise 100% PHP/JS
  codebase, stopped looking worth it. Reconsidered specifically
  because: the plan is advisory, never auto-published, which lowers
  the bar from "provably optimal" to "a good enough draft" a manager
  fixes up; the `PlanGenerator` interface was already the swap point
  needed to make this reversible later; and prior direct experience
  (a working PHP-only match-planner using greedy construction +
  hill-climbing) gave real evidence the technique holds up for a
  similarly-shaped problem at this scale.
- **Greedy construction, then hill-climbing — not a from-scratch
  random start.** Starting close to feasible and workload-balanced
  means the optimization phase spends its iteration budget improving
  fairness and preferences, not still searching for basic coverage.
- **Tiered weights (`W1 ≫ W2 ≫ W3`) collapse three lexicographic
  tiers into one scalar**, because hill-climbing's accept-if-improves
  rule needs a single number to compare, and a weighted sum with a
  large enough gap between tiers behaves identically to strict
  lexicographic ordering in practice — no combination of tier-3 gains
  can ever justify a tier-2 loss.
- **Substitute is a 4th move type, added once fill/relocate/swap
  proved insufficient.** A fully-staffed cycle where one employee
  holds nothing at all can't be rebalanced by fill (no open cell) or
  swap (the empty-handed employee has nothing to trade); substitute —
  replace one cell's occupant with someone else, no compensating
  assignment either way — is the actual fix, and turned out to be the
  main mechanism for workload rebalancing generally.
- **Exhaustive move generation, not a separate targeted generator.**
  The original plan called for explicitly generating moves that
  unload the current maximum-hours employee, reasoning that plain
  randomized search stalls against a minimax objective (many states
  score identically). That reasoning applies to *sampled* search;
  since the actual implementation enumerates every legal move each
  pass rather than sampling, a move unloading the maximum-hours
  employee is already in the candidate set whenever one exists — a
  separate targeting mechanism would only duplicate it.
- **No bare removal move.** Removing an assignment without
  immediately refilling the spot always costs a full `W1`, which
  nothing lower can outweigh — allowing it as a standalone move would
  just waste search budget on moves hill-climbing would always reject.
- **Full re-optimizer, not a gap-filler.** Unaffected by the language
  reversal — the planner may move or remove any assignment except one
  that's fixed or published. This is why change visibility exists.
- **One Generate action per cycle, every workcenter.** Never scoped to
  a subset — `equal_workload`'s cross-workcenter fairness pool would
  silently break otherwise.
- **Mixed-publish cycles solve what they can.** An unpublished week in
  an otherwise-published cycle still gets optimized; the published
  week's hours count toward fairness as locked context. The
  alternative (refuse the whole cycle) would block generating week 2
  once a manager published week 1 early.
- **Direct writes to `shift_assignments`, no staging/accept step.**
  Reuses `/planning` entirely for review — no new draft data model or
  screen. The tradeoff (no one-click "discard this whole run") is
  accepted; a manager reviews via the change summary and edits/removes
  what's wrong the same way they already fix anything else on
  `/planning`.
- **Still a queued job**, even with no network call — the iteration
  budget alone (up to ~10s) is too slow for a synchronous web request.
- **`PlanProblem`/`PlanSolution` value objects kept even with no wire
  boundary to cross.** They're what `HeuristicPlanGenerator` builds
  and returns internally; keeping them as a clean seam (rather than
  inlining everything into one method) is what preserves the
  interface's reversibility if a real solver ever replaces the
  heuristic.
- **Two unfulfilled-reason codes, not a granular taxonomy.**
  `no_eligible_employee` vs. `hard_cap_reached` covers the two
  fundamentally different fixes available to a manager. A more
  detailed breakdown is deferred until real usage shows it's needed.
- **Soft caps cost per unit over, not a flat per-violation cost.**
  Exceeding `max_hours_per_week` by 5 hours should cost more than
  exceeding it by 1 — `planning-rules` left this mapping to phase 5
  deliberately; this is that decision.

## Non-goals

- A staging/accept flow for generated assignments — direct writes
  only (see Key decisions).
- Per-cell highlighting of what a run changed on `/planning`'s week
  grid — the change summary panel is the only surface for that in
  v1.
- A finer-grained unfulfilled-reason taxonomy than the two codes
  above.
- Notifying anyone (email, in-app) when a run completes — the manager
  checks `/planning` themselves.
- Scoping a Generate run to specific workcenters.
- Any change to `SchedulingEligibility`, the manual assignment
  endpoints, or their validation — the planner reads the same
  eligibility rules but writes through the same `ShiftAssignment`
  model manual assignment already uses; nothing about manual
  assignment changes.
- Run history UI beyond "the most recent run for the viewed cycle" —
  older `plan_generation_runs` rows are kept (never deleted) but
  nothing surfaces them yet.
- A separate service, container, or language runtime for the planner —
  explicitly reversed; see Key decisions. Revisiting that call stays
  possible later, exactly because `PlanGenerator` is an interface, but
  isn't planned.
- Employee self-scheduling, swap requests, or any planner behavior
  beyond producing one draft per cycle on request.
