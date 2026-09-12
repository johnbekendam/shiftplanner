# Explicit Save Consolidation — Plan

Status: in progress — 1/4

Spec: `spec.md`.

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

- [ ] 2. **Personal/Show.vue and Employees/Form.vue move to one footer
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

- [ ] 3. **Settings/Index.vue moves to one footer Save.**
  `BusinessLineList`, `ShiftList`, `OrderedNameList` drop their
  `watch(rows/names, ...)`-driven autosave, their immediate
  move/delete/add requests, and the `saveStatus` prop, becoming local
  array/map state with the same emit-based contract as step 2's
  components (an `update:items`-style emit carrying the full pending
  list, including local-only rows for a pending add and omitting
  locally-deleted ones). `PeriodSettingsForm`, `ShiftNoteForm`, and
  `ScheduleNoteForm` drop their own `useForm`/submit button and instead
  expose their editable fields to the page (`v-model`-style) so the
  page's dirty registry covers them too.

  `Settings/Index.vue` gains the same footer-button-in-`Card`, dirty
  registry (one entry per: business lines, shifts, questions,
  competences, general/period, shift note, schedule note), and
  `useUnsavedChangesGuard` wiring as step 2, plus `hasError` tab
  markers on failure.

  Tests: rewrite `BusinessLineList`/`ShiftList`/`OrderedNameList` specs
  around the new emit contract (add/edit/reorder/delete change local
  state and emit, no immediate request). Rewrite
  `PeriodSettingsForm`/`ShiftNoteForm`/`ScheduleNoteForm` specs: they
  no longer submit themselves, they emit changes for the parent to
  save. Rewrite `SettingsIndex.test.js`: editing any tab enables the
  footer button. Save fires one request group per dirty resource. A
  failed group keeps its tab marked. Full PHP and JS suites, `npm run
  build`, green.

- [ ] 4. **Retire `useSaveStatus` and `SaveStatusBadge`.** Confirm no
  remaining `import` of either (steps 2 and 3 removed every call
  site). Delete `resources/js/composables/useSaveStatus.js`,
  `resources/js/components/ui/SaveStatusBadge.vue`, and their Vitest
  specs. `doc/features/employee-availability/spec.md` (and any other
  feature doc that describes the corner-badge autosave behavior) gets
  a short note pointing at this feature for the current save model.
  Set this `plan.md` header to `4/4`. Full PHP and JS suites, `npm run
  build`, green.
