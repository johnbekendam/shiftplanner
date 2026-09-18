# Clear Planning — Spec

Three related changes to `/planning`, built on `features/scheduling-engine/`:
a bulk-clear action for the whole planning period, a label rename, and
making manual assignment protect itself from the planner by default.

## Problem

`features/scheduling-engine/` made Generate a full re-optimizer over the
whole planning period: it can move or remove any assignment except one
marked fixed or one in a published week. Two things followed once that
landed:

1. A manager wanting to discard a round of draft assignments (hand-placed
   or solver-generated) and start over has no bulk action — only
   removing one assignment at a time.
2. A manual assignment defaults to `fixed: false`, so it's just as
   movable to a future Generate run as anything the solver placed
   itself — there's no way to say "this one I placed on purpose, leave
   it" without a separate Freeze click every time.

The Generate button's label ("Generate") also predates the period-wide
rework and no longer signals what it now covers.

## Solution

### Rename: Generate → Generate Planning

The button's label changes from "Generate :start – :end" to plain
"Generate Planning" — the date range was dropped from the label during
review (renamed key `planning.generate_period` → `planning.generate`,
now without placeholders). No behavior change; the button still covers
the same whole-period scope, it just no longer states the range in its
own text.

### Auto-fix on manual assignment

`ShiftAssignmentController::store()` creates new assignments with
`fixed: true` instead of `false`. Going forward only — no migration for
assignments that already exist. A manager can still unfreeze one
afterward with the existing Freeze/Unfreeze toggle
(`PUT /planning/assignments/{shiftAssignment}`), unchanged.

This makes manual placement mean something different from a solver
placement by default: hand-placed is protected until someone explicitly
frees it up; solver-placed stays disposable until a manager commits to
it (by publishing, or by freezing it directly). That distinction is
what Clear Planning (below) actually clears.

### Confirmation dialogs (revised after initial review)

Both Generate Planning and Clear Planning open a `ConfirmDialog`
(`resources/js/components/ui/ConfirmDialog.vue` — a Card-based modal
already used elsewhere in this app, e.g. `Employees/Form.vue`'s delete
flow) before doing anything, rather than acting immediately or falling
back to a native `confirm()`. Clicking either button no longer performs
the write directly; it opens that button's dialog, and the write only
happens once the manager confirms.

- **Generate Planning's dialog** explains what the run will do (create
  or update the draft for every unpublished week in the period; move
  or replace assignments except any fixed or already published) and
  states the period's date range explicitly — the range was dropped
  from the button's own label (see the rename above), so the dialog is
  now the one place a manager sees it before committing. Confirm
  button reuses the "Generate Planning" label; `variant="primary"`,
  since generating isn't destructive.
- **Clear Planning's dialog** explains what gets deleted and that it
  can't be undone. Confirm button reuses the "Clear Planning" label;
  `variant="danger"`.
- This replaces Clear Planning's originally-planned native `confirm()`
  step entirely — see Key decisions for why, and for what changed
  about Generate Planning's own confirmation model.

### Clear Planning button

A new button next to Generate Planning. Deletes every assignment in the
whole planning period that is **not** fixed and **not** in a published
`(workcenter, week)` — the same "locked" test `HeuristicPlanGenerator`
already applies, extracted into a shared lookup (see Key decisions) so
the two can never drift apart.

- **Scope**: the same effective date range Generate Planning operates
  on — every cycle in `PlanningCycle::allWithinPeriod()`, i.e. from the
  period's anchor through the end of the *last* cycle (which can run a
  few days past `period_end`, since a cycle is never truncated). Same
  active-workcenters-only scope as Generate. Never touches
  `plan_generation_runs` — that stays an append-only history, per
  `features/scheduling-engine/`'s own non-goals.
- **Confirmation**: the `ConfirmDialog` described above.
- **Synchronous**: a plain `DELETE`, not a queued job — deleting rows
  is fast; nothing here resembles the solver's own runtime.
- **Guarded against a concurrent Generate**: rejected (`409`, matching
  Generate's own concurrent-run guard) while any cycle in the period
  has an active run, and the button disables client-side under the
  same condition — avoids a rare race where a clear runs between a
  cycle's own load-state and write-state.
- No precondition on completeness, same as every other `/planning`
  write action.

### Route and controller

`DELETE /planning/clear` (`planning.clear`), admin-gated, alongside the
other `/planning` routes. New `PlanClearController@destroy` — separate
from `PlanGenerationController`, since generating and clearing are
unrelated actions that happen to be scope-symmetric, not variations of
the same one.

## Key decisions

- **Shared "is this assignment locked" lookup**, extracted onto
  `PublishedWeek` as `PublishedWeek::lockedPairs(Carbon $start, Carbon
  $end, Collection $workcenterIds): Collection` (a flipped `"{week_start}:
  {workcenter_id}"` set). Both `HeuristicPlanGenerator::lockPredicate()`
  and `PlanClearController` call it, each still applying its own
  `fixed` check inline — small enough not to be worth abstracting
  further, but the published-week half of the rule is a real business
  rule that must never drift between what Generate protects and what
  Clear deletes.
- **Clear mirrors Generate's period-wide scope**, not the viewed
  week/cycle — one consistent unit for "the plan," and the higher
  blast radius is exactly why confirmation (below) matters.
- **Confirm first**, breaking from `/planning`'s usual immediate,
  no-confirmation convention (publish/unpublish, remove one
  assignment) — a materially larger blast radius than any single-item
  action it would otherwise sit next to. Generate Planning gets the
  same treatment even though it isn't destructive, since re-running it
  can still move or replace a manager's own draft placements — the
  dialog is what tells them so before it happens.
- **`ConfirmDialog` over a native `confirm()`**, reversing the
  original plan. A plain `confirm()` can only show one line of text,
  which was enough for Clear Planning's original plain-text warning
  but not for Generate Planning's dialog, which needs to state the
  period range and explain what will happen — both need real markup.
  Once Generate needed a Card-based dialog, using the same component
  for Clear kept the two consistent rather than mixing a native and a
  custom dialog on one page. `ConfirmDialog.vue` was already the
  established pattern for this (`Employees/Form.vue`, `Mailbox.vue`,
  `EmployeeBackup.vue`, `Personal/Show.vue`), so this reuses it rather
  than building something new.
- **Testing note**: `ConfirmDialog` renders through `<Teleport to="body">`,
  so its content isn't reachable via a mounted child wrapper's own
  `.text()`/`.find()` — tests query `document.body` directly via
  `DOMWrapper`, matching the pattern already used in
  `ShiftWeekTable.test.js`. With two dialogs on the page at once,
  tests disambiguate by each `ConfirmDialog`'s `title` prop rather
  than relying on template order.
- **Auto-fix is forward-only, no backfill.** An existing unfixed
  assignment — manual or solver-placed — keeps its current state.
  Backfilling can't tell a deliberate existing manual placement from
  one nobody's looked at yet, and would lock things a manager may
  still expect Generate to be free to rearrange.
- **No change to the Freeze/Unfreeze toggle itself.** Auto-fix only
  changes the *default* a new manual assignment starts from; the
  existing toggle already covers reversing it either direction.

## Non-goals

- A precondition beyond the confirmation dialogs — e.g. an exact
  impact count (computing one ahead of time would need a new prop
  purely for the dialog copy).
- Clearing fixed or published assignments — Clear Planning only ever
  removes what Generate itself would already feel free to move.
- Touching `plan_generation_runs` history.
- Any change to how `/planning`'s other write actions (assign, remove,
  spot overrides, publish) behave.
- Retroactively fixing assignments created before this change.
