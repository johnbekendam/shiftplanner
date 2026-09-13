# Settings Explicit Save — Spec

Brings `Settings/Index.vue` to the same explicit-save model already
shipped for `Personal/Show.vue` and `Employees/Form.vue`, with one
per-tab Save and Cancel pair instead of one page-wide pair.

## Problem

`Settings/Index.vue` still autosaves on every edit, add, delete, and
reorder, using the corner `SaveStatusBadge` for feedback. This is the
same inconsistency `doc/features/explicit-save-consolidation/spec.md`
fixed on the other two pages, and that spec's revision note dropped
Settings from scope rather than solve it. The user now wants it done.

## Solution

Each of the six tabs — General, Business Lines, Shifts, Questions,
Competences, Information — gets its own Save and Cancel pair below its
content. A tab's Save enables only when that tab is dirty. Cancel
resets that tab to its last-saved state immediately, with no
confirmation.
This differs from Personal/Show and Employees/Form, whose one save
covers every tab at once — here, each tab is independent, so no
cross-tab dirty registry or tab error-dot markers are needed.

### Shared building blocks

- **`TabSaveBar.vue`** — a `CardSeparator` plus a Cancel/Save button
  row, matching the style already shipped on the other two pages.
  Props: `dirty`, `saving`, `justSaved`. Emits `save` and `cancel`.
  Every tab uses one instance.
- **`useDragReorder(items)`** — a small composable wrapping HTML5
  drag-and-drop (`dragstart`/`dragover`/`drop`) into a plain array
  reorder. Used by `BusinessLineList` and `OrderedNameList`.

### No more autosave

`BusinessLineList`, `ShiftList`, and `OrderedNameList` drop their
`watch(...)`-driven autosave and the `saveStatus` prop. Add, edit, and
delete all change local state only. A locally removed row — whether
just added or already on the server — disappears from view
immediately, no confirm prompt, since nothing is destroyed until Save.

### Reordering: drag handles, one request per Save

`BusinessLineList` and `OrderedNameList` (Competences and Questions)
replace their up/down move buttons with a drag handle per row, backed
by `useDragReorder`. Dragging only reorders the local list. A new bulk
endpoint per resource takes over from the one-step move endpoint:

```
PUT /settings/business-lines/reorder   { ids: [5, 2, 8, 1] }
PUT /settings/questions/reorder        { ids: [...] }
PUT /settings/competences/reorder      { ids: [...] }
```

Each controller validates `ids` as the full, distinct set of that
resource's current row ids (not a subset), then sets each row's
`position` to its index in the array. The old one-step `move` routes,
controller methods, and tests are removed. `ShiftList` has no reorder
today and gains none here.

### Shifts tab covers two resources

The Shifts tab holds `ShiftList` and `ScheduleNoteForm`. One Save
checks and persists both: editing either enables the tab's Save, and
Save fires whichever of the two changed. `ScheduleNoteForm` drops its
own `useForm`/submit and exposes its note text to the page instead,
the same shape `PeriodSettingsForm`-style forms take when folded into
a shared Save.

### General and Information tabs

`PeriodSettingsForm` and `ShiftNoteForm` already work as local-form,
explicit-submit — the model this feature wants everywhere. They keep
their own `useForm`, but their submit button is replaced by the shared
`TabSaveBar`, so every tab looks and behaves the same way.

### Cleanup

Once every tab is converted, `useSaveStatus` and `SaveStatusBadge` are
deleted — Settings was their last remaining use anywhere in the app.

## Key decisions

- **Per-tab, not page-wide.** Each tab's data is already a distinct
  backend resource with no cross-tab relationship (unlike
  Personal/Show, where weekly hours and business line share one
  endpoint across two tabs). Per-tab buttons are simpler to build and
  to reason about: a save failure always shows on the tab already in
  view.
- **Drag handles over deferred one-step moves.** The other two pages
  defer per-item requests and replay them on Save. Reordering doesn't
  fit that: the existing move endpoint is a one-step neighbor swap, so
  replaying a multi-step local reorder needs a computed swap sequence.
  A new bulk-reorder endpoint that takes the final order directly is
  simpler and matches "one Save, one request" better than a sequence
  of swaps would.
- **No confirm dialog on Cancel.** Cancel here discards in-progress
  edits, the same non-destructive action it is on the other two pages.
  `ConfirmDialog` stays reserved for Withdraw and Delete, which destroy
  a record outright.
- **No confirm prompt on a local row removal.** Nothing is deleted
  until Save runs, so the existing "this affects N employees" warning
  moves out of the click-to-remove step. It has nowhere left to attach
  once removal is instant and local.
- **Bulk reorder requires the full id set.** Accepting a partial list
  would leave unmentioned rows at stale positions. Requiring the
  complete set catches a stale client (e.g., another admin deleted a
  row mid-edit) as a validation error instead of silently corrupting
  order.

## Non-goals

- Any change to `ShiftNoteForm`'s or `PeriodSettingsForm`'s validation
  rules.
- Any change to how `ShiftList` orders shifts (unordered today, stays
  that way).
- A combined or atomic backend endpoint spanning more than one
  resource — each tab's Save still fires one request per resource that
  changed on it, same as the other two pages.
- Employees/Show, Personal/Show — already done, untouched here beyond
  the shared `TabSaveBar`/`useDragReorder` files they could later reuse
  but do not need to.
