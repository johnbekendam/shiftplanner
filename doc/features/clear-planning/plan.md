# Clear Planning — Plan

Status: done — 1/1

Spec: `spec.md`. Small enough for one step.

- [x] 1. **Rename, auto-fix, Clear Planning button.**
  `ShiftAssignmentController::store()`: `'fixed' => false` becomes
  `'fixed' => true`. `en.json`: `planning.generate_period` renamed to
  `planning.generate` and reworded from "Generate :start – :end" to
  plain "Generate Planning" — the date range was dropped from the
  label during review; new `planning.clear`, `planning.confirm.clear`,
  `planning.flash.cleared` keys.

  `PublishedWeek::lockedPairs(Carbon $start, Carbon $end, Collection
  $workcenterIds): Collection` — the shared published-pairs lookup,
  extracted from `HeuristicPlanGenerator::lockPredicate()`, which is
  refactored to call it (behavior unchanged, existing tests still
  cover it). New `PlanClearController@destroy`
  (`DELETE /planning/clear`, `planning.clear`): resolves the period's
  full date range from `PlanningCycle::allWithinPeriod()`, 422s the
  same way Generate does if the period isn't configured, 409s if any
  cycle in the period has an active run, else deletes every
  active-workcenter assignment in range that's neither `fixed` nor in
  a `lockedPairs` published week.

  `Scheduling.vue`: a Clear Planning button next to Generate Planning,
  `confirm()`-gated, disabled under the same `generationStatus.active`
  condition as Generate, posts the delete and lets Inertia's `back()`
  refresh `weekCells`/`coverage` as usual.

  Feature tests: `ShiftAssignmentTest` (or wherever assignment
  creation is already covered) updated for `fixed: true` by default.
  New `PlanClearControllerTest.php` (guest/manager blocked, no period
  configured, deletes only unfixed+unpublished in range, leaves fixed/
  published/out-of-range/archived-workcenter assignments untouched,
  409 while a cycle is active, doesn't touch `plan_generation_runs`).
  `HeuristicPlanGeneratorTest`/`HillClimbOptimizerTest` re-run
  unchanged against the refactored `lockPredicate` (no new tests
  needed there — behavior doesn't change, only where the published-
  pairs lookup lives). New `PublishedWeekLockedPairsTest.php` (or
  folded into an existing `PublishedWeek`-adjacent test file) for the
  extracted method directly. Vitest: `Scheduling.test.js` extended for
  the button's confirm/disable/delete-call behavior; existing
  `ShiftAssignmentController`-adjacent Vitest (assign-flow) updated
  anywhere it asserted on the old default.

  New `PublishedWeekLockedPairsTest.php` (5) and `PlanClearControllerTest.php`
  (11) built and confirmed red before implementation, per TDD. Full PHP
  suite green (693 passed, was 677). Full JS suite green (645 passed,
  was 641). Pint clean. `npm run build` green.

**Revised after this step shipped**: the Generate Planning button
previously acted immediately with no confirmation, and Clear Planning
used a native `confirm()`. Both now open a `ConfirmDialog` (the
Card-based modal already used elsewhere, e.g. `Employees/Form.vue`)
before acting — Generate Planning's explains what the run will do and
states the period's date range (now that the range is no longer in
the button's own label); Clear Planning's explains what gets deleted.
`en.json`: `planning.confirm.clear` removed; new
`planning.generate_dialog.title/body/period` and
`planning.clear_dialog.title/body` keys. `Scheduling.vue`: both
buttons now open their dialog on click instead of acting directly; the
write only fires from the dialog's `confirm` handler.
`Scheduling.test.js` rewritten for the dialog flow (open-on-click,
dialog content incl. period range, cancel leaves state unchanged,
confirm posts/deletes and closes) — required `DOMWrapper(document.body)`
for the teleported dialog content, and disambiguating the two
simultaneous `ConfirmDialog` instances by `title` prop rather than
template order. Full JS suite green (648 passed, was 645). PHP suite
unaffected (frontend-only change). Pint clean, `npm run build` green.

## Not done / deferred

- Everything under the spec's non-goals.
- Manual browser pass — AGENTS.md leaves UI verification to the user.
