# Publish Planning — Spec

Roadmap phase 6 ("Publish and employee schedule view"), built ahead of
phase 5 (the auto-planner doesn't exist yet — see Key decisions). Builds
on `doc/features/scheduling-week-drilldown/`.

## Problem

A manager can build a week's roster on `/scheduling`, but nothing
distinguishes a settled week from one still being worked out, and an
employee has no way to see their own assignments at all. Everything on
`/scheduling` is draft by default, visible only to admins.

## Solution

### Data

New `published_weeks` table: `id`, `week_start` (date, unique — the
Monday), timestamps. A row's existence means that week is published;
there is no boolean column. New `PublishedWeek` model.

New `Employee::shiftAssignments()` `hasMany` relationship (didn't
exist).

### Publishing

Publish/unpublish is a single action per Monday–Sunday week, covering
every active workcenter's assignments that week at once — matches how
the calendar itself already aggregates across every workcenter (it was
never scoped to one).

- `POST /scheduling/weeks/{week_start}/publish` — creates the
  `PublishedWeek` row (`firstOrCreate`, so re-publishing an already
  published week is a no-op, not an error).
- `DELETE /scheduling/weeks/{week_start}/publish` — deletes it.
- Both admin-gated, `date`-constrained (`\d{4}-\d{2}-\d{2}`) like the
  existing spot-override routes, immediate, no confirmation dialog, no
  precondition (a manager can publish an empty or partially-staffed
  week and keep adjusting it after — the calendar's existing
  green/warning coloring is informational only, never a gate). Any
  week is publishable — past, current, or future.
- Publishing/unpublishing never touches `ShiftAssignment` rows or locks
  editing. A manager keeps assigning, removing, freezing, and adjusting
  spot counts on a published week exactly like an unpublished one.

### `/scheduling` page changes

`SchedulingController@index` gains:

- `weekPublished` (bool) — whether the currently-selected week
  (`weekStart`) is published. Drives a Publish/Unpublish button shown
  in a small header above the week's workcenter cards, always visible
  once a week is selected (i.e. whenever the empty-schedule state
  isn't showing).
- `publishedDays` (`{ [day]: true }`) — every day-of-month in the
  visible month whose containing week is published. Computed by
  collecting the distinct Monday for each day 1..daysInMonth and
  checking which of those Mondays have a `PublishedWeek` row.

`Calendar.vue` (shared, generic component) gains a new capability: a
colored left-border bar down an entire published week's row. Its
day-grid currently renders as one flat CSS grid with no per-row
wrapper element, so this requires restructuring the grid into explicit
per-week row groups (each a `grid grid-cols-7` chunk of 7 buttons,
wrapped in a container that can carry a left border) — not just a new
prop on the existing markup. New prop: `weekMarkerDays: Object` (day
→ color-family name, reusing the same badge-color-family system
`dayStates` already uses); a row's border shows if any of its actual
day buttons are marked. `Scheduling.vue` passes `publishedDays` through
with the `'custom'` family (the same family already used elsewhere in
this app for a one-off accent that isn't success/warning/error).

### Employee Planning tabs

Two new "Planning" tabs, matching the app's existing tabbed
Settings/Details/Availability/Competences pattern
(`resources/js/components/ui/Tabs.vue`), both read-only (no form
section, no `hasError`):

- **`Personal/Show.vue`** (the employee's own token-linked page) —
  `PersonalPageController::show` gains `plannedShifts`: this
  employee's own `ShiftAssignment` rows, **published weeks only**,
  grouped under each week's date-range heading (e.g. "Sep 14 – Sep
  20, 2026"), each row showing date, workcenter name, shift name and
  time. An unpublished week's assignments never reach this page or
  its props, matching `doc/concept.md`'s existing rule ("employees
  view their own published assignments").
- **`Employees/Form.vue`** (the admin's employee editor) —
  `EmployeeController::edit` gains `plannedShifts`: the same shape,
  but **every** assignment for that employee (draft included), each
  week's heading carrying a small "published" marker. The admin
  already sees everything on `/scheduling`; restricting this
  convenience tab to published-only would be an odd, inconsistent
  exception.

Both lists are new UI — no existing component groups items under
repeated date-range headings, so this is new markup, not a reused
pattern.

## Key decisions

- **Whole week, every workcenter, one flag.** Matches the calendar's
  own aggregate-across-workcenters shape; avoids a "partially
  published" week that the month calendar (not workcenter-scoped)
  couldn't represent cleanly anyway.
- **Publishing never gates editing.** The user was explicit: manual
  changes stay allowed on published weeks. `published_weeks` is purely
  a visibility flag (and, later, a planner guard) — never a lock.
- **No enforcement code for the auto-planner.** Phase 5 (`PlanGenerator`,
  the OR-Tools `/solve` service) doesn't exist yet — there is nothing
  to guard against today. This spec records the invariant a future
  planner must respect ("never write to or overwrite assignments in a
  week that has a `published_weeks` row") as a note for that future
  work, not as code written now.
- **No precondition, no confirmation, no date restriction on
  publish/unpublish.** Matches this page's existing fully-immediate,
  ungated editing convention — nothing else on `/scheduling` blocks an
  action on completeness, asks to confirm, or restricts which dates
  are eligible.
- **Both employee-facing pages get a Planning tab, with different
  scope.** The employee's own page is published-only (the existing
  product vision). The admin's editor shows everything, since the
  admin already has full visibility elsewhere — a published-only
  admin tab would just be a less useful, inconsistent restriction.
- **Calendar's week-marker reuses the badge-color-family system**
  (`success`/`warning`/`error`/`custom`/`standard`/`muted`) rather
  than inventing a new color, consistent with how `dayStates` already
  works.
- **List UI for the employee-facing tabs, not a calendar.** A handful
  of shifts per week doesn't warrant a full calendar grid; a grouped
  list is simpler to build and easier to scan.

## Non-goals

- Any auto-planner code — Phase 5 is still unbuilt.
- Per-workcenter publish granularity.
- A precondition (e.g. full staffing) to publish.
- A confirmation step on unpublish.
- Restricting which weeks (past/future) can be published.
- Notifying an employee when their week is published (mailbox
  integration stays out of scope here).
