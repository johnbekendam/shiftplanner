# Publish Per Workcenter — Plan

Status: done — 4/4

Spec: `spec.md`.

- [x] 1. **Data + publish endpoints.** New migration
  (`2026_09_18_000001_add_workcenter_id_to_published_weeks_table.php`):
  truncate `published_weeks`, add `workcenter_id` (foreign key,
  cascade), drop the old unique on `week_start`, add a unique index on
  (`week_start`, `workcenter_id`). `PublishedWeek`: `$fillable` gains
  `workcenter_id`, added `belongsTo(Workcenter::class)`.
  `PublishedWeekController::store`/`destroy` take a `Workcenter` route
  param and scope the `firstOrCreate`/delete to both `week_start` and
  `workcenter_id`. Routes are now
  `POST /planning/weeks/{weekStart}/workcenters/{workcenter}/publish`
  and the matching `DELETE`, named
  `planning.weeks.workcenters.publish` /
  `planning.weeks.workcenters.unpublish`. `tests/Feature/
  PublishedWeekTest.php` rewritten for the new route shape plus two
  tests for independence between workcenters (publishing/unpublishing
  one doesn't affect another). 10 tests passing.

- [x] 2. **`/planning` page: per-card publish button, AND-aggregated
  calendar marker.** `SchedulingController@index` replaces
  `weekPublished`/`publishedDays` with `publishedWorkcenterWeeks`
  (`[{ workcenter_id, week_start }]` for the visible month).
  `Scheduling.vue`: removed the page-level Publish/Unpublish button;
  each visible `WorkcenterScheduleCard` now gets its own `weekStart`
  and `published` props and hosts its own Publish/Unpublish button
  (moved `togglePublish` down into the card, pointed at the new
  per-workcenter route). The calendar's `weekMarkerDays` is computed
  client-side: a week marks only when every workcenter *relevant* to
  it (checked, with checked-shift coverage that week — same test
  `dayStates` already applies per day) is published; a week with no
  relevant workcenter shows no marker, refined from the spec's first
  cut of "every checked workcenter" once it was clear that would leave
  the marker dark for unrelated workcenters with nothing scheduled
  that week. `spec.md` updated to match. Removed the now-unused
  `scheduling.published_label` language key. Updated
  `tests/Feature/SchedulingIndexTest.php`,
  `tests/js/Scheduling.test.js`, `tests/js/WorkcenterScheduleCard.test.js`
  for the new prop shape, per-card button, and relevance-scoped
  AND-aggregation. 22 PHP + 22 JS tests passing.

- [x] 3. **Planning tabs: per-assignment published flag.**
  `PlannedShifts::forEmployee()`: dropped the week-level `published`
  key, added `published` to each assignment (looked up by its own
  `workcenter_id` + week, via a `"{weekStart}:{workcenterId}"` lookup
  set built from the relevant `PublishedWeek` rows). `publishedOnly:
  true` now filters individual assignments rather than whole week
  groups; a week heading appears once at least one of its assignments
  qualifies. `PlannedShiftsList.vue`: moved the Published/Draft badge
  from the week heading to each assignment row. Updated
  `tests/js/PlannedShiftsList.test.js` (added a mixed-week case),
  `tests/Feature/PersonalPageTest.php` (added a same-week,
  different-workcenter-publish-state case),
  `tests/Feature/EmployeeAdminTest.php`, `tests/js/PersonalShow.test.js`,
  `tests/js/EmployeesForm.test.js`. 53 PHP + 71 JS tests passing.

- [x] 4. **Docs and full checks.** `doc/roadmap.md` — phase 6's
  section and status row updated: `published_weeks` is now per-(week,
  workcenter), narrowed by this feature as a phase-5 prerequisite; the
  calendar marker, per-card publish button, and per-assignment
  Planning-tab visibility all described. `doc/concept.md` didn't name
  whole-week publishing specifically, so no change needed there. Pint
  clean. Full PHP suite green (619 passed). Full JS suite green (613
  passed). `npm run build` green. `php artisan migrate` clean against
  a fresh database.

## Not done / deferred

- Bulk publish/unpublish across several workcenters.
- Any scheduling-engine / solver code — that follows this feature.
- Manual browser pass — AGENTS.md leaves UI verification to the user.
