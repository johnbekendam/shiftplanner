# Publish Per Workcenter — Spec

Prerequisite for roadmap phase 5 (`doc/features/scheduling-engine/`,
not yet written). The scheduling engine's solver is allowed to touch
any assignment except one marked `fixed` or one inside a published
week — but `features/publish-planning/` only publishes a whole week
across every workcenter at once. A manager who wants to lock in one
workcenter's finished roster while another workcenter in the same week
is still being generated/adjusted has no way to do that today. This
feature narrows publish state from "a week" to "a week for one
workcenter," so the solver (and everything else that reads publish
state) can protect exactly what's actually settled.

`features/publish-planning/spec.md` originally excluded this as a
non-goal ("avoids a 'partially published' week that the month calendar
... couldn't represent cleanly anyway"). That reasoning held until the
calendar had no honest way to show a mixed state; this feature resolves
it by requiring every checked workcenter to be published before a
week's calendar marker lights up (see Key decisions), rather than
avoiding the mixed state altogether.

## Problem

`published_weeks` has one row per Monday, no workcenter column.
Publishing is all-or-nothing per week: a manager can't publish one
workcenter's settled roster while leaving another workcenter in the
same week open for further changes. This blocks phase 5, where the
solver must treat "published" as a per-(workcenter, week) protection,
not a whole-week one.

## Solution

### Data

No real `published_weeks` rows exist yet (confirmed — only manual
planning has happened so far), so the migration path is a plain
schema change, not a data-preserving one. A new migration (the
original `create_published_weeks` migration already shipped and stays
untouched, per this project's own rule against editing a shipped
migration):

- Truncates `published_weeks`.
- Adds `workcenter_id`, foreign key, cascade on workcenter delete.
- Drops the old unique index on `week_start` alone; adds a unique
  index on (`week_start`, `workcenter_id`).

`PublishedWeek` model: `$fillable` gains `workcenter_id`; a new
`belongsTo(Workcenter::class)`.

### Publishing

Same immediate, no-confirmation, no-precondition, any-date model as
today — narrowed to one workcenter at a time.

- `POST /planning/weeks/{weekStart}/workcenters/{workcenter}/publish`
  — `firstOrCreate(['week_start' => $weekStart, 'workcenter_id' =>
  $workcenter->id])`.
- `DELETE /planning/weeks/{weekStart}/workcenters/{workcenter}/publish`
  — deletes that one row.
- Both admin-gated, `weekStart` date-constrained like today. Route
  names `planning.weeks.workcenters.publish` /
  `planning.weeks.workcenters.unpublish`.
- Still never touches `ShiftAssignment` rows or locks editing —
  publishing remains purely a visibility/protection flag.

### `/planning` page changes

`SchedulingController@index` replaces the single `weekPublished`
boolean and whole-month `publishedDays` map with raw per-workcenter
data, following the same "ship the month once, filter client-side"
approach `coverage` and `weekCells` already use:

- `publishedWorkcenterWeeks` — every (`workcenter_id`, `week_start`)
  pair published within the visible month, `[{ workcenter_id,
  week_start }]`.

`Scheduling.vue` computes, client-side, from `publishedWorkcenterWeeks`
and `checkedWorkcenterIds`:

- **Per-card publish state** — each visible `WorkcenterScheduleCard`
  looks up its own (workcenter, `weekStart`) pair and gets a
  Publish/Unpublish button in its header, replacing today's read-only
  "Published" badge. The page-level button above the cards is removed
  — there is no longer one action that means "publish this week."
- **Calendar week marker** — a week's left-border bar shows only when
  every workcenter *relevant* to that week (checked, with at least one
  checked-shift coverage cell that week — the same "relevant" test
  `dayStates` already applies per day) is published (AND aggregation,
  not OR). A week with no relevant workcenter at all shows no marker,
  same as a zero-relevant day stays muted rather than green. Matches
  the existing day-coloring convention in
  `features/scheduling-calendar/` ("a day is green only when nothing
  checked is short"). Scoping to *relevant* rather than literally every
  checked workcenter matters here: an unrelated workcenter with
  nothing scheduled that week has no publish state worth requiring,
  and requiring it anyway would leave the marker permanently dark for
  no reason. Recomputed client-side on filter toggle, matching the
  coverage colors' existing behavior.

### Employee Planning tabs

`PlannedShifts::forEmployee()` moves the `published` flag from the
week group down to each assignment, since a week can now mix published
and unpublished workcenters:

- Each assignment gains `published: bool` (looked up by its own
  `workcenter_id` + week).
- `publishedOnly: true` (the employee's own page) filters individual
  **assignments**, not whole weeks — an employee can see some of a
  week's assignments and not others, if only some of that week's
  workcenters are published. A week heading appears once at least one
  of its assignments qualifies.
- `publishedOnly: false` (the admin editor) keeps every assignment,
  each carrying its own `published` flag.

`PlannedShiftsList.vue`: the per-week heading's Published/Draft badge
moves to each assignment row instead (`showPublishedMarker` still
gates whether it renders at all, unchanged for the employee's own
page, which never shows it since everything present is already
published).

## Key decisions

- **`published_weeks` keeps its name.** Renaming to
  `published_workcenter_weeks` would touch every reference for no
  behavior change; the table already stores "a publish event," and a
  row now just carries one more scoping column, the same shape change
  `workcenter_shift_date_overrides` went through when overrides
  gained more specific scope.
- **Truncate, don't preserve.** Confirmed no real published data
  exists anywhere this has run. A data-preserving migration (expand
  each old whole-week row into one row per active workcenter) would
  be dead code for a migration path nothing has ever exercised.
- **AND-aggregation for the calendar's week marker.** Consistent with
  this page's existing day-coloring rule. The alternative (OR — mark
  a week if any checked workcenter is published) would make the bar
  claim "settled" for a week that's still half draft.
- **No page-level Publish button anymore.** With per-workcenter
  granularity, "publish the week" isn't a single action any more; the
  action now belongs on each workcenter's own card, matching where a
  manager already assigns and pins for that workcenter.
- **Per-assignment, not per-workcenter-group, on the Planning tabs.**
  `PlannedShifts` already returns a flat assignment list per week; a
  new intermediate workcenter grouping inside each week would be a
  bigger UI change for the same information a per-row badge already
  conveys.

## Non-goals

- Any scheduling-engine / solver code — this only prepares the
  publish data model and UI it depends on.
- Bulk publish/unpublish across several workcenters at once.
- Migrating or preserving existing publish data — none exists.
- Changing the month calendar's coverage (staffed/open/muted) coloring
  — untouched, this only changes the separate week-marker bar.
- A confirmation step, a staffing precondition, or a past/future date
  restriction on publish/unpublish — unchanged from
  `features/publish-planning/`.
