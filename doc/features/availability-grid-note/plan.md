# Availability Grid Note — Plan

Status: done — 7/7

## Steps

- [x] 1. **Migration + model.** `2026_09_10_000001_add_shift_schedule_note_to_planning_settings`
  adds a nullable `shift_schedule_note` text column. `PlanningSettings`
  gains it in `$fillable` and a `scheduleNoteHtml(?string $name = null)`
  that mirrors `shiftNoteHtml()`.

- [x] 2. **Controller + routes + settings payload.**
  `ShiftController@updateScheduleNote` (validate `nullable|string|max:20000`,
  blank → `null`). Route `PUT /settings/shifts/schedule-note` behind
  `admin`, before `PUT /settings/shifts/{shift}`.
  `SettingsController@index` adds `scheduleNote`.

- [x] 3. **Feature test — settings side.** `tests/Feature/ScheduleNoteTest.php`:
  save/clear, whitespace → `null`, non-admin `403`, over-long fails,
  `/settings` exposes `scheduleNote`, model render, both availability
  payloads. 12 pass.

- [x] 4. **Settings editor UI.** `resources/js/components/ScheduleNoteForm.vue`
  (like `ShiftNoteForm.vue`). Wired into `Settings/Index.vue` Shifts panel
  after `<ShiftList>` + `<CardSeparator />`.

- [x] 5. **Availability page payload + display.** `EmployeeController@edit`
  and `PersonalPageController@show` add `scheduleNoteHtml`.
  `Personal/Show.vue` and `Employees/Form.vue` render
  `<ShiftNote v-if="scheduleNoteHtml" :html="scheduleNoteHtml" />` below
  `<AvailabilityGrid>`.

- [x] 6. **i18n + JS test.** `shifts.schedule_note_*` and
  `shifts.flash.schedule_note_saved` in `en.json`.
  `tests/js/ScheduleNoteForm.test.js`, 3 pass.

- [x] 7. **Full check.** `php artisan test` (343 pass; 4 pre-existing
  `GraphTransportTest` failures from a local `Composer\CaBundle` issue,
  unrelated). `npm run test` (310 pass; 2 pre-existing failures on `main`
  in `SettingsIndex`/`AppLayoutNav`, unrelated). `npm run build` clean.
  Roadmap phase 4 entry updated.
