# Workcenters — Spec

Roadmap phase 3 continuation. The roadmap left shift coverage as "needs a
design session" (`doc/roadmap.md:15`) after shift definitions shipped.
This feature adds the workcenter record itself. `doc/concept.md:66-67`
and `doc/features/shift-definitions/spec.md:116-117,142` flag the
workcenter concept as deferred.

## Revision note

The first shipped version of this feature also held shift attachment and
per-day open-spot capacity, edited from an expandable row on this tab.
The user asked to split "define" from "assign": this tab now only
maintains the workcenter record (a plain named list, like Business
Lines). Shift attachment and capacity moved to their own page and
feature, `doc/features/workcenter-shift-assignments/spec.md`. The
`description` field is dropped in the same pass — one field, the name,
is enough for a workcenter.

This is the first of three features. It stops at the workcenter record
— no shift, no employee, touches this feature.
`workcenter-shift-assignments` links workcenters to shifts and sets
their open-spot capacity. A later `scheduling` feature adds employee
assignment against those spots, and a further one adds rule-based
automatic planning.

## Problem

A manager cannot group shifts by where they run. Shifts are flat, global
templates with no concept of a place.

## Solution

### Workcenter record

A new `workcenters` table:

| column | notes |
| --- | --- |
| `id` | |
| `name` | required, case-insensitive unique, max 50 |
| `position` | integer, sets the manual order |
| `archived_at` | nullable timestamp |
| timestamps | |

A `Workcenter` model with a position-ordered default scope (archived
workcenters sort after active ones), a `shifts()` `belongsToMany` (used
by `workcenter-shift-assignments`, not edited here), and a `toPayload()`
returning `{ id, name, position, archived_at }`.

A Workcenter has no link to a Business Line. `doc/concept.md` and
`business-lines/spec.md` keep Business Lines as the org/FTE-target unit.
A Workcenter is where shifts and headcount live instead. The two may
connect later through a planning rule (for example, a preference for
employees from a matching Business Line). That rule is not part of this
feature.

### Settings page — new "Workcenters" tab

`/settings` stays admin-only. The tabbed card gains a **Workcenters**
tab after **Shifts**, built on the explicit-save model
`settings-explicit-save/spec.md` already shipped for every other tab:
local edits only, one Save/Cancel pair per tab, no autosave, no confirm
on a local change.

`WorkcenterList.vue`, in the shape of `BusinessLineList.vue`: a name
`TextInput` and a drag handle for reordering, backed by
`useDragReorder`. Editing a field, adding a row, removing a row, or
dragging a row only changes local state. A row's bin button is
available only while that workcenter has no shift attached (from
`workcenter-shift-assignments`) — checked against a `shifts` array in
the row's payload. Once it has at least one shift, the bin button is
replaced by an **Archived** checkbox instead — reversible, and edited
like any other field. Archived rows sort after active ones.

**Save** diffs local state against the last-committed state and fires
one request per change, the same `Promise.allSettled` shape the other
tabs use:

- `DELETE /settings/workcenters/{id}` for a removed row (only reachable
  when that row had no shift attached, so the server-side reject on a
  non-empty workcenter is a defense-in-depth path, not a normal one).
- `PUT /settings/workcenters/{id}` for a row whose name or archived flag
  changed. `archived` in the request body maps to setting or clearing
  `archived_at`.
- `POST /settings/workcenters` for a new row.
- `PUT /settings/workcenters/reorder` with the full ordered id list, if
  the order changed.

On success every request's result reseeds committed state from the
reloaded `workcenters` prop, matching how `saveBusinessLines` already
works. Cancel discards local state back to committed, no confirmation.

`WorkcenterController` mirrors `BusinessLineController` (`store`,
`update`, `destroy`, `reorder`). `SettingsController@index` adds
`workcenters` as
`{ id, name, position, archived_at, shifts: [{ id, name }] }` in
position order. A workcenter's attached-shift count, for the
bin/archive gate, is `shifts.length`; the array itself comes from
`workcenter_shift` and is read-only here.

## Key decisions

- **No Business Line link.** The user confirmed workcenters are an
  independent grouping. A future rule may use Business Line as a soft
  preference signal, but that is planning-time logic, not a schema
  relationship.
- **One field: the name.** The user asked to merge or drop the
  description rather than carry a second free-text field with no
  defined use yet. A name is enough to identify a workcenter; a
  description can come back later if a real need shows up.
- **Define here, assign elsewhere.** The user asked for a page
  boundary, not just a UI section, between maintaining the workcenter
  list (here) and relating workcenters to shifts with capacity
  (`workcenter-shift-assignments`). The two are different tasks at
  different cadences: naming a workcenter is rare, tuning its shift
  coverage is closer to routine.
- **Soft-archive, not hard delete, once a workcenter has a shift.**
  Assignment rows reference the workcenter. Archiving keeps that history
  intact for the `scheduling` feature and any later reporting. A
  workcenter with no shift attached yet can still be deleted outright,
  matching how an unused row elsewhere in Settings behaves.
- **Archiving is a plain field, not a confirmed action.** It is fully
  reversible, so it follows the same no-confirm, edit-until-Save rule
  `settings-explicit-save/spec.md` already set for every other field in
  Settings, rather than a one-off confirm dialog.
- **Admin-only**, matching Shifts and Business Lines today. No
  manager-scoped permission model exists in the app. This feature does
  not introduce one.
- **This feature stops before shifts and employees.** Shift attachment,
  capacity, assignment, manual and automatic planning, and the "fixed"
  flag are later features. Keeping the split means this spec's plan
  lands independently and does not block on decisions still to be
  detailed.

## Non-goals

- Shift attachment and open-spot capacity — `workcenter-shift-assignments`.
- Employee assignment to a workcenter/shift/date, manual planning UI,
  and the "fixed" assignment flag — the `scheduling` feature.
- Rule-based automatic planning, hard/soft rule definitions, and the
  OR-Tools `/solve` contract — a later, separate feature.
- Competence-to-workcenter gating (`competences/spec.md` flags this as
  future work; still deferred here).
- Manager-level (non-admin) access to workcenter management.
- A description field. Dropped; may return if a real need shows up.
