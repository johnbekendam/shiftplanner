# Settings Explicit Save — Plan

Status: in progress — 3/6

Spec: `spec.md`.

- [x] 1. **Shared building blocks: `TabSaveBar`, `useDragReorder`, and
  the bulk-reorder backend.** New `resources/js/components/ui/
  TabSaveBar.vue`: a `CardSeparator` plus a Cancel/Save row, matching
  the button styling already shipped on Personal/Show and
  Employees/Form. Props `dirty`, `saving`, `justSaved`; emits `save`
  and `cancel`. New `resources/js/composables/useDragReorder.js`:
  given a `Ref<Array>`, returns `{ onDragStart(index), onDrop(index) }`
  that splice-reorders the array on drop, HTML5 native drag events
  only, no library.

  Backend: `BusinessLineController::reorder`, `QuestionController::
  reorder`, `CompetenceController::reorder` — each validates `ids` as
  an array whose sorted values exactly match the resource's current
  full id set (a closure rule, so a mismatch surfaces as
  `assertSessionHasErrors('ids')`), then sets each row's `position` to
  its array index in one pass. New routes `PUT /settings/
  business-lines/reorder`, `PUT /settings/questions/reorder`,
  `PUT /settings/competences/reorder`. The old one-step `move` methods,
  routes, and tests stay untouched here — each list component still
  calls `move` until its own conversion step (2 and 4) switches it to
  drag handles and removes it. Adding `reorder` without touching `move`
  keeps the app fully working between commits.

  Tests: `TabSaveBar.test.js` — shows Cancel/Save, Save disabled when
  not dirty, emits `save`/`cancel` on click, button text reflects
  `saving`/`justSaved`. `useDragReorder.test.js` — `onDragStart` then
  `onDrop` at a different index reorders the array; dropping on the
  same index is a no-op. PHP feature tests per controller: reordering
  a full valid id set updates positions to match; a partial or
  mismatched id set is a validation error; guest/manager/admin access
  matches today's `move` tests. Full PHP and JS suites, `npm run
  build`, green.

- [x] 2. **Business Lines tab.** `BusinessLineList.vue`: drop
  `saveStatus` and the `watch(rows, …)` autosave; add/edit/delete
  change local state only; replace the up/down buttons with a drag
  handle per row via `useDragReorder`. Remove
  `BusinessLineController::move`, its route, and its tests — nothing
  calls it anymore once this component no longer does. Emits `update:items` with the
  full current local list on every change (add, edit, delete, or
  reorder), mirroring the emit contract already used by `HolidayList`
  on the other two pages. `Settings/Index.vue` tracks this tab's
  dirty state (any local edit, add, delete, or reorder versus the
  last-saved snapshot) and a `save()` that fires one `PUT` per edited
  row, one `POST` per added row, one `DELETE` per removed row, and (if
  the order changed) one `PUT .../reorder` with the full id order —
  all via `Promise.allSettled`, matching the per-item pattern already
  used for the other two pages. `TabSaveBar` sits below the tab,
  wired to that dirty/save/cancel.

  Tests: rewrite `BusinessLineList.test.js` around the new emit
  contract (add/edit/delete/drag-reorder change local state, no
  network call) and the drag handle. Rewrite the Business Lines
  portion of `SettingsIndex.test.js`: editing/adding/deleting/
  reordering enables Save; clicking Save fires the expected request
  set including `PUT .../reorder` when order changed; Cancel reverts
  to last-saved without saving. Full PHP and JS suites, `npm run
  build`, green.

- [x] 3. **Shifts tab.** `ShiftList.vue`: same conversion as
  `BusinessLineList` minus reordering (it has none) — drop
  `saveStatus` and autosave, add/edit/delete local only, emit
  `update:items`. `ScheduleNoteForm.vue` drops its own `useForm` and
  submit button, emitting `update:note` instead, seeded once from its
  `note` prop. `Settings/Index.vue`'s Shifts tab tracks one combined
  dirty state (shift-list changes or note changes) and one `save()`
  that persists whichever changed — the shift list via the same
  per-item pattern as step 2, the note via a single `PUT /settings/
  shifts/schedule-note`. One `TabSaveBar` below both.

  Tests: rewrite `ShiftList.test.js` and `ScheduleNoteForm.test.js`
  around their new emit contracts. Rewrite the Shifts portion of
  `SettingsIndex.test.js`: editing the list alone, the note alone, or
  both enables Save; Save persists exactly what changed; Cancel
  reverts both. Full PHP and JS suites, `npm run build`, green.

- [ ] 4. **Questions and Competences tabs.** `OrderedNameList.vue`:
  same conversion as `BusinessLineList` — drop `saveStatus` and
  autosave, add/edit/delete local only, drag handle via
  `useDragReorder` replacing the up/down buttons, emits
  `update:items`. One component change wires both tabs, since
  `Settings/Index.vue` already mounts it twice with different
  `endpoint`/`i18n-prefix`. Remove `QuestionController::move` and
  `CompetenceController::move`, their routes, and their tests — the
  `reorder` endpoints from step 1 take over for both.
  `Settings/Index.vue` tracks each tab's dirty/save/cancel
  independently — they are two separate resources even though they
  share a component.

  Tests: rewrite `OrderedNameList.test.js` around the new emit
  contract and drag handle (parameterized or duplicated for both
  `i18n-prefix` variants already covered today). Rewrite the
  Questions and Competences portions of `SettingsIndex.test.js`,
  mirroring step 2's Save/Cancel assertions for each tab
  independently. Full PHP and JS suites, `npm run build`, green.

- [ ] 5. **General and Information tabs, restyled.**
  `PeriodSettingsForm.vue` and `ShiftNoteForm.vue` drop their own
  submit button, exposing `form.isDirty`/`form.processing` (or
  equivalent) up to `Settings/Index.vue` so it can drive a
  `TabSaveBar` in the same position as every other tab. Their own
  `useForm`, validation, and `PUT` endpoints stay exactly as they are
  today — only the button markup and its position move.

  Tests: rewrite `PeriodSettingsForm.test.js` and
  `ShiftNoteForm.test.js` for the new prop/emit surface instead of an
  internal submit button. Rewrite the General and Information portions
  of `SettingsIndex.test.js`: editing a field enables that tab's Save;
  Save submits; Cancel reverts via `form.reset()`. Full PHP and JS
  suites, `npm run build`, green.

- [ ] 6. **Leave-guard and cleanup.** `Settings/Index.vue` wires
  `useUnsavedChangesGuard` to "any of the six tabs' dirty computeds is
  true." Delete `resources/js/composables/useSaveStatus.js`,
  `resources/js/components/ui/SaveStatusBadge.vue`, and their Vitest
  specs — confirm no remaining import first. Update
  `doc/features/explicit-save-consolidation/spec.md`'s revision note
  to point at this feature now that Settings is done. Set this
  `plan.md` header to `6/6`. Full PHP and JS suites, `npm run build`,
  green.
