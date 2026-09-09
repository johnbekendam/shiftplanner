# Employee Import — Plan

Status: done — 4/4

Spec: `spec.md`. Admin-only `/import` page: a two-column CSV (name, email)
upserted onto `employees` by email, all-or-nothing.

- [x] 1. **Failing tests.**
  `tests/Feature/EmployeeImportTest.php` (16 cases): guest redirect;
  manager 403; admin GET renders `EmployeeImport`; create; default
  weekly hours; update-by-email; idempotent re-run; whitespace trim;
  semicolon delimiter; invalid email → 422 + nothing written; wrong
  column count (1 and 3) → 422; in-file duplicate (case-insensitive) →
  422; header-only → 422; non-CSV → 422; missing file → 422.
  `tests/js/EmployeeImport.test.js` (5 cases): drop zone renders; picked
  file POSTs multipart to `/import`; success summary; row-error list;
  drag-and-drop path.
  `tests/js/AppLayoutNav.test.js`: `Import` shown to admin (`/import` in
  the admin block), hidden from a manager.
  Confirmed red — route 404 / missing page component.

- [x] 2. **Backend.**
  `routes/web.php` admin group: `GET`/`POST /import` →
  `EmployeeImportController` (`import.index` / `import.store`).
  `EmployeeImportController`: `index()` renders `EmployeeImport`;
  `store()` validates `file` (`mimes:csv,txt`, `max:2048`) → `422
  {errors:[…]}` on failure; `parse()` strips a UTF-8 BOM, detects `,`
  vs `;` from the header line, drops row 1, skips blank lines,
  `str_getcsv` per row, collects errors (column count, name, email,
  in-file dupe), and on an empty result with no errors reports
  `import.error.empty`; any error → `422 {errors}`; otherwise upsert by
  `email` (create new, update name when it differs) → `{created, updated}`.
  `en.json`: `nav.import` + `import.*` keys.

- [x] 3. **Frontend.**
  `resources/js/pages/EmployeeImport.vue`: `AppLayout` + `Head` + `Card`;
  a dashed drop zone `<div>` (`@dragover`/`@dragleave`/`@drop.prevent`,
  keyboard-activatable) composing `FileInput` (`accept=".csv,text/csv"`)
  for click-to-browse; `axios.post('/import', FormData)`; result panel
  shows the summary or the row-error list; token-driven colours only.
  `AppLayout.vue`: `{ label: __('nav.import'), href: '/import', icon:
  'upload' }` first in the admin block.

- [x] 4. **Full suite + docs.**
  `php artisan test` 294 → 310 pass. `npm test` 265 → 274 pass.
  `vendor/bin/pint --dirty` clean. `npm run build` green.

## Not done / deferred

- Everything under the spec's Non-goals: no other columns, no
  delete/deactivate of absent employees, no partial import, no
  error-report or template download, no queued processing, no manager
  access, no import history, no user-account creation, `.csv` only.
