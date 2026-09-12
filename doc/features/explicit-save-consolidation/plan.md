# Explicit Save Consolidation — Plan

Status: done (reduced scope) — 2/2

Spec: `spec.md`. Steps 3 and 4 (Settings/Index and the useSaveStatus /
SaveStatusBadge cleanup) are dropped — see the spec's Revision note.

- [x] 1. **Shared building blocks: tab error markers and a leave-guard.**
  `Tabs.vue` gains an optional per-tab `hasError` flag: each entry in
  the `tabs` prop array may carry `hasError: true`, rendered as a
  small dot next to that tab's label. No existing caller passes it, so
  every current use of `Tabs.vue` is unaffected. New composable
  `useUnsavedChangesGuard(isDirty)` (`resources/js/composables/`):
  while `isDirty()` is true, it adds a `beforeunload` listener that
  asks to confirm, and an Inertia `router.on('before', ...)` listener
  that shows a `window.confirm('leave anyway?')`-style prompt and
  cancels the visit when declined. Neither is wired into a page yet.
  Tests: `Tabs.vue` renders a dot for a tab with `hasError: true` and
  not for one without. `useUnsavedChangesGuard`: registers/removes the
  `beforeunload` listener as `isDirty()` flips, and the router-before
  callback cancels a visit only when `isDirty()` is true and the
  confirm is declined. Full JS suite and `npm run build` green.

- [x] 2. **Personal/Show.vue and Employees/Form.vue move to one footer
  Save.** These two pages share `AvailabilityGrid`, `HolidayList`,
  `QuestionChecklist`, and `TagChecklist`, so they convert together.

  Component changes:
  - `AvailabilityGrid`: `choose()` only updates local `cells` and
    emits `update:availability`. Drops the `router.put`, the
    `saveStatus` calls, and the error-rollback (nothing round-trips
    until Save, so nothing to roll back).
  - `HolidayList`: keeps `draft` as today. The holiday list itself
    becomes a local array (`rows`, seeded from `holidays` prop): `add()`
    appends a locally-keyed draft row instead of posting, `remove()`
    marks a row deleted locally instead of deleting. Emits
    `update:holidays` with the full pending list so the parent can
    read it back for its own dirty check.
  - `QuestionChecklist` and `TagChecklist`: gain local
    id-set state seeded from `answeredIds`/`selectedIds`, toggle that
    set instead of PUT/DELETE, and emit `update:answered-ids` /
    `update:selected-ids`.
  - Drop the `saveStatus` prop from all four (no more
    `SaveStatusBadge` usage in either page).

  Page changes (both pages, mirrored):
  - Remove the Details-tab inline Save button, the
    `watch(...).../save()` autosave hooks (business_line_id on
    Personal, all four fields on Employees/Form), the tab-away flush
    watcher, and `onWeeklyHoursChange`'s immediate `save()` call.
  - Add a `Card`/`CenteredLayout` footer (check `CenteredLayout.vue`
    for the right slot before wiring Personal/Show's) holding one
    `ButtonPrimary`: disabled when nothing is dirty (and, on
    Personal/Show, disabled outright when `!editable`), reading
    Save/Saving…/Saved with the existing check-circle convention.
  - A page-level dirty/save registry: one entry per resource (details,
    availability, holidays, questions, competences), each exposing an
    `isDirty` computed and a `save()` that issues whatever per-item
    requests that resource's local diff needs (e.g. one `router.put`
    per changed availability cell). Clicking the footer button runs
    every dirty resource's `save()` independently. A resource clears
    its dirty flag only on success, and marks its tab `hasError` on
    failure.
  - Wire `useUnsavedChangesGuard` to "any resource dirty."

  Tests: rewrite the relevant Vitest specs for `AvailabilityGrid`,
  `HolidayList`, `QuestionChecklist`, `TagChecklist` around
  emit-on-interaction instead of an immediate request. Rewrite
  `PersonalShow.test.js` and the `Employees/Form` spec: editing any
  tab enables the footer button and does not call `router.*`
  immediately. Clicking Save fires the expected request(s) per dirty
  resource. A failed request keeps the button enabled and marks that
  tab. `editable: false` keeps the footer button disabled regardless
  of edits. Backend feature tests for the affected controllers are
  unchanged (same endpoints, same validation) — only the client-side
  timing of the calls changes. Full PHP and JS suites, `npm run
  build`, green.

## Dropped

Step 3 (Settings/Index.vue) and step 4 (retiring `useSaveStatus` and
`SaveStatusBadge`) are dropped by choice, not by discovery of a
blocker. See the spec's Revision note for the reasoning. Settings/Index
keeps its current per-row autosave and `SaveStatusBadge`, unchanged.
