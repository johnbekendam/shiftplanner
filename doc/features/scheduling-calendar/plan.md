# Scheduling Calendar — Plan

Status: done — 4/4

Spec: `spec.md`. Bulk-load coverage data for the month once (capacities,
overrides, assignment counts), never call `Workcenter::spotsFor()` in a
per-cell loop.

- [x] 1. **Backend: month coverage payload.** Rewrote
  `SchedulingController@index`: query params `year`, `month` (default
  current month, normalized). Renders `Inertia::render('Scheduling', [...])`
  with `workcenters` (active, `{ id, name }`), `shifts` (all,
  `{ id, name }`), `year`, `month`, and `coverage` — one entry per
  `(workcenter, shift, date)` triple in the month where the shift is
  attached to the workcenter with spots > 0 that date:
  `{ workcenter_id, shift_id, date, spots, assigned }`. Computed with
  bulk queries: `workcenter_shift` attachments, `workcenter_shift_capacities`
  and `workcenter_shift_date_overrides` for the active workcenters
  loaded once, assignment counts via one grouped query over
  `shift_assignments` for the month, joined in memory (override wins
  over weekday default, matching `Workcenter::spotsFor()`'s rule,
  skipping the per-call query cost). `SchedulingIndexTest` rewritten:
  guest and manager blocked; payload shape (workcenters, shifts, year,
  month, coverage); archived workcenters excluded; a coverage entry
  only appears where spots > 0; a date override wins over the weekday
  capacity; assigned count matches actual assignments and ignores
  assignments outside the visible month; defaults to the current
  month; an explicit `year`/`month` is honored. Full PHP suite green
  (489 passed; 4 pre-existing, unrelated `GraphTransportTest` failures
  from a missing `composer/ca-bundle` dependency). Pint clean.

- [x] 2. **Frontend: page skeleton — filter + calendar.** Rewrote
  `resources/js/pages/Scheduling.vue`: removed the week-grid table and
  `SchedulingCell` usage. A filter `Card` above the calendar holds two
  inline `CheckboxInput` lists (workcenters, shifts), matching the
  `TagChecklist` visual pattern directly rather than reusing that
  component (its internal seeded-`ref` model doesn't fit two
  independent, page-owned selection lists) — both fully checked by
  default. Renders `Calendar` with `year`/`month` from props,
  `enable-day-selection`/`enable-week-day-selection` both `false`
  (day click stays a no-op this step); its `change` event only
  navigates (`router.get('/scheduling', { year, month })`) when the
  emitted year/month actually differs from the current props — guards
  against the component's own on-mount `change` emit and any future
  day-click emit causing a spurious reload. `en.json`:
  `scheduling.filter_workcenters`, `filter_shifts`, three
  `legend_*` keys; removed the old week-grid-only keys
  (`select_workcenter`, `prev_week`, `next_week`, `this_week`,
  `no_shifts`) since nothing references them anymore.

- [x] 3. **Frontend: coloring logic.** Client-side computed
  (`dayStates`): for each day in the visible month, filters `coverage`
  entries to checked workcenter ids and checked shift ids; `success` if
  every matching entry has `assigned >= spots`, `warning` if any is
  short, `muted` if no entries match — fed into `Calendar`'s
  `dayStates` prop, with a `legenda` labeling all three. `Scheduling.test.js`
  rewritten end-to-end (mounts the real `Calendar`, not a stub): the
  two checklists render all-checked by default; the calendar renders
  the given year/month; a fully-covered day is green, a short day is
  warning, a day with no relevant coverage is muted; unchecking a
  workcenter recolors the days that depended only on it, with no
  navigation; next-month navigates with `{ year, month }`; no
  navigation fires on mount; the no-active-workcenters empty state
  still renders. `npm run test` (470 passed) and `npm run build`
  green.

- [x] 4. **Docs and full checks.** `doc/features/scheduling/spec.md` —
  added a pointer under "Page — `/scheduling`" noting the page's
  landing view is now the calendar described here, and that the
  week-grid UI moved out pending a later drill-down step; the old
  content stays as the historical record of what shipped. `doc/roadmap.md`
  — phase 3's section notes this feature is in progress (step 1
  shipped). `php artisan test` (489 passed, 4 pre-existing unrelated
  failures), `npm run test` (470 passed), `npm run build`: all green.

## Not done / deferred

- Day drill-down into per-cell editing (assign/remove/pin, spot-count
  override) — a later step, per spec non-goals.
- Filter persistence across page loads.
