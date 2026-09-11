Status: complete — 6/6

- [x] Add failing tests in `tests/Feature/ShiftNoteTest.php` for the `:---`
      spacer marker: a single marker renders one `data-note-spacer`
      element and strips `:---` from the output; two consecutive markers
      render two spacer elements (stacking).
- [x] Add a failing test in `tests/js/ShiftNote.test.js` asserting a
      `[data-note-spacer]` element in the supplied HTML renders inside the
      component.
- [x] Implement the `:---` marker in `app/Services/MarkdownRenderer.php`,
      following the existing `extractButtons` preprocessing pattern, and
      bump `[&_p]:my-1` to `[&_p]:my-2` plus add spacer styling in
      `resources/js/components/ShiftNote.vue`.
- [x] Update `shifts.note_hint` and `shifts.schedule_note_hint` in
      `resources/lang/en.json` to mention `:---`.
- [x] Run the focused tests and fix regressions (`php artisan test
      --filter=ShiftNoteTest`, `npm run test -- ShiftNote`).
- [x] Run the full test suite (`php artisan test`, `npm run test`) and fix
      any regressions. Two failures in each suite (`GraphTransportTest`,
      `SettingsIndex.test.js`) are pre-existing, confirmed unrelated by
      reproducing them on a clean `git stash`.
