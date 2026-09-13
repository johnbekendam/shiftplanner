# Explicit Save Consolidation — Spec

Replaces autosave and mixed save patterns on two employee-facing pages
with one footer Save button per page.

## Revision note

The original scope covered three pages: `Personal/Show.vue`,
`Employees/Form.vue`, and `Settings/Index.vue`. `Settings/Index.vue`
was dropped after `Personal/Show.vue` and `Employees/Form.vue`
shipped, since its list components' step-by-step reordering (a
one-step "move up/down" endpoint) looked like it would need a
replayed swap sequence to defer to a batched Save — real complexity
for a page only admins use, occasionally, for a task that was never
the source of the original complaint.

`Settings/Index.vue` shipped after all, in a later feature:
`doc/features/settings-explicit-save/spec.md`. It resolved the
reorder problem differently than assumed here — a new bulk-reorder
endpoint taking the final order directly, paired with drag-and-drop
handles, rather than replaying one-step moves — and settled on one
Save/Cancel pair per tab instead of one page-wide pair, since each
tab's data is an independent resource with no cross-tab relationship.
`useSaveStatus` and `SaveStatusBadge`, mentioned below as staying in
place for Settings, are now retired everywhere.

## Problem

Employees reported they were not sure their changes on the personal
page were saved. They expected a submit action at the end. Today the
personal page mixes an explicit Save button (Details tab) with silent
autosave on every click (Availability, Holidays, Questions,
Competences tabs). The only feedback for an autosave is a small badge
in the corner of the card, `SaveStatusBadge`, that fades out after 1.5
seconds. `Employees/Form.vue` shares the same autosaving components,
so it carries the identical mix.

## Solution

`Personal/Show.vue` and `Employees/Form.vue` each move to one save
mechanism: edit freely across every tab, then click one Save button
to persist everything at once.

### One footer button per page

Each page's `Card` gets a `footer` slot holding a single
`ButtonPrimary`. The button:

- Sits in the same place regardless of which tab is active.
- Is disabled until something on any tab is dirty, not only the
  active tab.
- Reads `Save` / `Saving…` / `Saved`, with a check-circle icon on
  success, the same pattern `Employees/Form.vue`'s Details tab already
  uses.
- Is disabled outright on `Personal/Show.vue` when `editable` is
  false, regardless of dirty state, same as today.

### No more autosave

Every control that used to fire a network request on interaction now
only changes local state:

- `AvailabilityGrid`, `TagChecklist`, `QuestionChecklist`: a click
  changes the local selection. Nothing saves until the footer button
  is clicked.
- `HolidayList`: add and delete both change a local array. No row
  shows a "pending" mark. The footer button's enabled state is the
  only unsaved-changes signal.
- The `business_line_id` and `weekly_hours` watchers that called
  `save()` on change are removed.
- The existing inline Save buttons on the Details tab of
  `Personal/Show.vue` and `Employees/Form.vue` are removed. Their
  fields join the same dirty-tracking and the same footer button, so
  each page ends with exactly one save mechanism, not two.

`useSaveStatus` and `SaveStatusBadge` drop out of both pages and every
converted component. The composable and component files themselves
stay in the codebase: `Settings/Index.vue` still uses both.
used, since there is no autosave left to report on.

### Saving: one request per dirty resource

Each tab still has its own backend endpoint (availability, holidays,
questions, competences, details). A Save click fires one request per
resource that is dirty, independent
of the others. A resource that succeeds clears its own dirty flag. A
resource that fails keeps its dirty flag, so a second Save click
retries only what is still outstanding. No new combined endpoint, and
no change to any resource's validation rules.

### Finding a failed tab

A tab whose resource failed to save gets a small error-dot marker on
its tab label, visible even while another tab is active. This is the
only way to know which tab needs attention when the failure is not on
the tab currently in view.

### Leaving with unsaved changes

Two guards, both keyed off the same page-wide dirty state:

- A `beforeunload` listener warns on closing the tab or reloading.
- An Inertia `router.on('before', ...)` listener asks "leave anyway?"
  before a link navigation away from the page.

Switching between tabs on the same page is not a navigation, since it
is a `ref` toggling which panel shows with `v-show`. Local state
persists across tab switches already, so no guard is needed there.

## Key decisions

- **Independent per-resource requests, not one combined endpoint.**
  The resources are already separate tables behind separate
  controllers. A combined endpoint would need a new transaction
  boundary spanning models that share none today, for a guarantee
  (all-or-nothing) the feedback problem does not actually need.
  Independent requests keep each resource's existing validation and
  make a partial failure easy to retry: only the failed piece stays
  dirty.
- **Consolidate the already-explicit Save buttons too.** Leaving
  Details/General/Information on their own inline button would still
  leave two save mechanisms on one page, the exact inconsistency this
  feature exists to remove.
- **No pending-row styling in the list components.** The footer
  button's enabled state already tells the user there are unsaved
  changes. A second, per-row "unsaved" mark would duplicate that
  signal for no added clarity.
- **Tab switching needs no leave-guard.** It never triggers an Inertia
  visit or a page unload, only a local `v-show` toggle. Dirty state
  from an inactive tab is not at risk from switching tabs.
- **Error markers on tabs, not a footer message.** A footer message
  can scroll out of view on a long tab. A dot on the tab label stays
  visible from anywhere on the page and points straight at the tab
  that needs another look.

## Non-goals

- `ThemeBuilder.vue`, `Mailbox.vue`, `Account/Show.vue` — already
  fully explicit-button-driven, untouched by this feature.
- `Settings/Index.vue` — dropped from scope, see the Revision note.
- Any new combined or atomic backend endpoint.
- Any change to a resource's validation rules.
- Any change to the `editable`/locked-by-manager concept on
  `Personal/Show.vue` beyond keeping Save disabled while locked.
