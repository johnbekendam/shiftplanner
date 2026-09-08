# Competences — Plan

Status: in progress — 2/5

Spec: `spec.md`. Roadmap phase 3.5.

- [x] 1. **Backend: competence records, config page, CRUD and reorder.**
  Migration `competences` (`name` string unique, `position` integer,
  timestamps). `Competence` model (`$fillable` name + position,
  `employees()` belongsToMany, a `newQuery`/`booted` default order by
  `position`). `CompetenceFactory` (name a job-skill phrase, position a
  sequence). `SettingsController@index` renders `Settings/Index` with
  `competences` as `{ id, name, position, holder_count }` in position
  order. `CompetenceController`: `store` (name required, string, max 100,
  case-insensitive unique; position = current max + 1), `update` (rename,
  unique ignoring self), `destroy` (delete row; pivot cascades), `move`
  (body `direction` in `up|down`; swap `position` with the neighbour;
  no-op past an end). Routes behind `auth`: `GET /settings`,
  `POST /settings/competences`, `PUT /settings/competences/{competence}`,
  `DELETE /settings/competences/{competence}`,
  `PUT /settings/competences/{competence}/move`. Feature tests: guest
  redirected from `/settings` and the writes; index payload shape and
  `holder_count`; create appends at the end; blank name and duplicate
  name (different case) rejected; rename works; rename to a duplicate
  rejected; delete removes the competence and its pivot rows; `move` up
  and down swap two rows; `move` past the first or last row changes
  nothing. Full PHP suite green.

- [x] 2. **Backend: employee competence links and toggle endpoints.**
  (The `competence_employee` migration, `Employee::competences()`, and
  `Competence::employees()` already landed in step 1 for `holder_count`.)
  Concern `TogglesCompetence` with an
  idempotent attach and detach (`syncWithoutDetaching` / `detach`).
  `EmployeeCompetenceController` (`update` attaches, `destroy` detaches,
  both `abort` on an unknown competence). `PersonalCompetenceController`
  (resolve the token or 404, then the same). Routes:
  `PUT` and `DELETE /employees/{employee}/competences/{competence}` behind
  `auth`; `PUT` and `DELETE /personal/{token}/competences/{competence}`
  token-only. `EmployeeController@edit` and `PersonalPageController@show`
  payloads gain `competences` (`{ id, name }`, position order) and
  `competenceIds`. Feature tests: guest blocked on the manager route;
  manager attaches then detaches; a second attach keeps one pivot row;
  employee attaches and detaches by token; a bad token is 404; an unknown
  competence is 404; the `edit` and `show` payloads carry `competences`
  and `competenceIds`. Full PHP suite green.

- [ ] 3. **Frontend: Settings page and the Competences config list.**
  `resources/js/pages/Settings/Index.vue` — `AppLayout`, one `Card` whose
  header is `Tabs` (single tab `competences`), tab held in a `ref`.
  `resources/js/components/CompetenceList.vue` — props `competences`,
  `endpoint`. Each row: an inline `TextInput` for the name that sends
  `router.put(`${endpoint}/${id}`, { name })` on blur or `Enter`; an up
  and a down icon `ButtonSecondary` that send
  `router.put(`${endpoint}/${id}/move`, { direction })`, the up hidden on
  the first row and the down on the last; a `ButtonDanger` that runs a
  confirm step naming `holder_count`, then
  `router.delete(`${endpoint}/${id}`)`. An add-row form posts
  `{ name }` to `endpoint`. Empty-list message. All writes use
  `preserveScroll` + `preserveState`. Sidebar: add a `Settings`
  `NavLink` (`cog` icon) in `AppLayout.vue`. `en.json`: `nav.settings`,
  `settings.*` (page title, tab label), `competences.*` (column header,
  add label and placeholder, rename error, move up/down labels, delete
  label, delete confirm with a `:count` placeholder, empty). Vitest:
  `CompetenceList` renders a row per competence, submits an add to the
  endpoint, sends a rename `put` on blur, sends `move` with the
  direction, hides the up control on the first row and the down on the
  last, and runs the confirm before the delete `delete`; `Settings/Index`
  renders the tab and mounts `CompetenceList` on the endpoint;
  `AppLayout` nav lists `Settings`. Front-end suite green.

- [ ] 4. **Frontend: the Competences tab on both employee surfaces.**
  `resources/js/components/CompetenceChecklist.vue` — props `competences`,
  `selectedIds`, `endpoint`. Renders a `CheckboxInput` per competence in
  the given order, checked when the id is in `selectedIds`. A toggle to
  on sends `router.put(`${endpoint}/${id}`)`, a toggle to off sends
  `router.delete(`${endpoint}/${id}`)`, both with `preserveScroll` +
  `preserveState`. Empty-list message. `Employees/Form.vue`: add a third
  `competences` tab; the panel renders `CompetenceChecklist` on
  `/employees/${employee.id}/competences` when editing, else the
  save-first message. `Personal/Show.vue`: the same third tab on
  `/personal/${token}/competences`. `en.json`: `competences.tab`,
  `competences.checklist_empty`, reuse `availability.holidays.save_first`
  or add `competences.save_first`. Vitest: `CompetenceChecklist` renders
  a checkbox per competence with the right checked state, sends `put` on
  a toggle to on and `delete` on a toggle to off; `Employees/Form` shows
  three tabs, points the checklist at the employee endpoint, and shows
  the save-first message on the create page; `Personal/Show` shows three
  tabs and points the checklist at the token endpoint. Front-end suite
  green.

- [ ] 5. **Docs and full checks.** `doc/roadmap.md` — add the phase 3.5
  `Competences` row (State: done, `features/competences/`). `doc/
  concept.md` — the employee record carries a competence set; the manager
  Core Data section gains competences. Set this `plan.md` header to
  `5/5`. Run Pint, `php artisan test`, `npm run test`, `npm run build` —
  all green.

## Not done / deferred

- Everything under the spec's "Non-goals", planning included.
- Manual browser pass — AGENTS.md leaves UI verification to the user.
