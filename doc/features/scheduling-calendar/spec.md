# Scheduling Calendar — Spec

First step of a multi-step rework of `/scheduling` (see
`doc/features/scheduling/spec.md`) into a calendar-first page. This step
replaces the page's landing view with a month calendar and a
workcenter/shift filter; later steps add day drill-down back to
per-cell editing.

## Problem

The current `/scheduling` page shows one workcenter's week at a time. A
manager has no way to see, at a glance, which days across a month (or
across workcenters) still have open spots. They have to click through
every workcenter and week to find gaps.

## Solution

### Page — `/scheduling`

Replaces the existing week-grid landing view. Same route, same nav
entry, same admin-only gate.

- **Filter panel** (above the calendar): two always-visible inline
  checkbox lists, reusing the existing `CheckboxInput`/`TagChecklist`
  pattern — no new popover component.
  - **Workcenters** — every active workcenter, all checked by default.
  - **Shifts** — every `Shift` definition (global, not per-workcenter),
    all checked by default.
- **Calendar** — the existing `resources/js/components/ui/Calendar.vue`,
  first use of this component in the app. One month at a time, using
  its built-in prev/next/reset navigation.

### Coloring

A `(workcenter, shift)` pair is **relevant** to a day when: the
workcenter is checked, the shift is checked, that shift is attached to
that workcenter, and `Workcenter::spotsFor(shift, date) > 0`.

Per day, across every relevant pair:

| State | Condition | Calendar color |
| --- | --- | --- |
| Fully staffed | every relevant pair has assigned ≥ spots | `success` (green) |
| Open spots | at least one relevant pair is short | `warning` |
| Nothing scheduled | no pair is relevant (nothing checked runs that day) | `muted` |

A legend (`Calendar`'s `legenda` prop) labels the three colors.

### Data flow

- `SchedulingController@index` now returns, for the visible month: all
  active workcenters (`{ id, name }`), all shifts (`{ id, name }`), and
  a coverage payload for every `(workcenter, shift, date)` triple in
  the month where the shift is attached to the workcenter with
  spots > 0 that date — `{ workcenter_id, shift_id, date, spots,
  assigned }`. Built with bulk queries (capacities, date overrides,
  assignment counts loaded once for the month and joined in memory),
  not a `spotsFor()` call per cell — that would be
  `workcenters × shifts × days` queries.
- Query params: `year`, `month` (default current month).
- **Filter toggles recompute colors client-side only** — the month
  payload already has everything needed; no reload on checkbox change.
- **Month navigation reloads** — `Calendar`'s `change` event (on
  prev/next/reset) triggers a new Inertia visit with the new
  `year`/`month`.
- **Day click is a no-op** this step.

### What's removed this step

The week-grid UI (shift rows × day columns, spot-count editing,
assignee add/remove/pin) is removed from `Scheduling.vue`. Its backend
— `ShiftAssignmentController`, `ScheduleSpotController`,
`EligibleEmployeeController`, `SchedulingEligibility` — is untouched
and stays fully tested, ready for a later step to wire a day click into
an editing view (either that old grid, scoped to one day, or a new
one — not decided yet).

## Key decisions

- **Replaces `/scheduling` now, not a new route.** The calendar is the
  intended long-term landing view; adding it at a side URL would just
  mean redirecting later for no benefit now.
- **Editing capability is temporarily lost.** Removing the week grid
  before drill-down exists means a manager can't assign/unassign or
  edit spot counts via this page until a later step. Accepted
  deliberately — the alternative (bolting the old single-workcenter
  grid underneath a multi-workcenter calendar) mixes two incompatible
  scopes on one page and would need reworking anyway once drill-down
  lands.
- **Shifts are the global `Shift` list, not per-workcenter.** Matches
  how the domain models them (`Shift` is workcenter-agnostic; the
  `workcenter_shift` pivot is what attaches one to a workcenter).
- **AND-aggregation across relevant pairs.** A broad filter (many
  workcenters/shifts checked) should surface any gap, not average it
  away — a day is green only when nothing checked is short.
- **Zero-relevant days are muted, not green.** Keeps green meaning
  "checked and confirmed staffed," not "vacuously true because nothing
  was checked runs that day."
- **Client-side filter recompute.** The month's full coverage data is
  small enough to ship once; recomputing colors from checkbox state in
  the browser makes the filter feel instant, matching "the colors
  should match the selection criteria" as a live, responsive read.
- **Bulk-loaded coverage, not per-cell `spotsFor()`.** A month across
  several workcenters and shifts is hundreds of cells; `spotsFor()`
  issues two queries per call, which doesn't scale to a month view.
- **No filter persistence.** Resets to everything-checked on each page
  load. Simplest default for this step; can be revisited if managers
  want their filter remembered.
- **Admin-only**, matching every other page in this area.

## Non-goals

- Day drill-down / clicking a day to edit assignments — a later step.
- Any change to the assignment, spot-override, or eligible-employee
  write endpoints — they're untouched, just temporarily unused by the
  page.
- Per-user filter persistence.
- A popover/dropdown filter menu — no such component exists yet; using
  one would need discussion first per `AGENTS.md`.
- Competence-to-workcenter gating, automatic planning — still out of
  scope per `doc/features/scheduling/spec.md`.
