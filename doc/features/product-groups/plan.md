# Product Groups — Plan

Status: in progress — 4/6

Spec: `spec.md`. Roadmap phase 3.6. Mirrors competences (phase 3.5).

- [x] 1. **Refactor: shared `OrderedNameList` and `TagChecklist`.** Rename
  `resources/js/components/CompetenceList.vue` to `OrderedNameList.vue`
  and `CompetenceChecklist.vue` to `TagChecklist.vue`. `OrderedNameList`
  takes an `i18nPrefix` prop (string) and builds its label keys from it
  (`${prefix}.name`, `.add`, `.add_placeholder`, `.move_up`,
  `.move_down`, `.delete`, `.delete_confirm`, `.list_empty`); `items`
  replaces `competences`. `TagChecklist` takes `items`, `selectedIds`,
  `endpoint`, `emptyKey`. Behaviour is unchanged. Update
  `Settings/Index.vue`, `Employees/Form.vue`, `Personal/Show.vue` to the
  new names, passing `i18nPrefix="competences"` and
  `emptyKey="competences.checklist_empty"`. Rename the tests to
  `OrderedNameList.test.js` and `TagChecklist.test.js` and adapt them;
  fix imports in `SettingsIndex.test.js`, `EmployeesForm.test.js`,
  `PersonalShow.test.js`. `npm run test` and `npm run build` green. No
  back-end change.

- [x] 2. **Backend: product group records, Settings data, CRUD and
  reorder.** Migrations `product_groups` (`name` string unique,
  `position` unsigned int, timestamps) and `employee_product_group`
  (`product_group_id` + `employee_id` FKs cascade, composite primary
  key). `ProductGroup` model (`$fillable` name + position, `position`
  cast, `booted` order-by-position global scope, `employees()`
  belongsToMany, `toPayload()`). `Employee::productGroups()`
  belongsToMany. `ProductGroupFactory`. `ProductGroupController` with
  `store` (name required, string, max 100, case-insensitive unique;
  `position` = max + 1), `update` (rename, unique ignoring self),
  `destroy`, `move` (`direction` in `up|down`, neighbour swap, no-op past
  an end) — parallel to `CompetenceController`. `SettingsController@index`
  adds `productGroups` as `{ id, name, position, holder_count }` in
  position order. Routes behind `auth`: `POST /settings/product-groups`,
  `PUT /settings/product-groups/{productGroup}/move`,
  `PUT /settings/product-groups/{productGroup}`,
  `DELETE /settings/product-groups/{productGroup}`. Feature test
  `ProductGroupConfigTest` mirroring `CompetenceConfigTest` (guest
  blocked; index payload and `holder_count`; create appends; blank and
  duplicate-case name rejected; rename; rename-to-duplicate rejected;
  delete cascades; move up and down; move past an end is a no-op; bad
  direction rejected). Full PHP suite green.

- [x] 3. **Backend: employee preference links and toggle endpoints.**
  `TogglesProductGroup` concern with an idempotent attach
  (`syncWithoutDetaching`) and detach. `EmployeeProductGroupController`
  (`update` attaches, `destroy` detaches) behind `auth`.
  `PersonalProductGroupController` (resolve the token or 404, then the
  same). Routes: `PUT` and
  `DELETE /employees/{employee}/product-groups/{productGroup}` behind
  `auth`; `PUT` and
  `DELETE /personal/{token}/product-groups/{productGroup}` token-only.
  `EmployeeController@edit` and `PersonalPageController@show` payloads
  gain `productGroups` (`{ id, name }`, position order) and
  `productGroupIds`. Feature test `EmployeeProductGroupTest` mirroring
  `EmployeeCompetenceTest` (guest blocked; manager attach then detach;
  double attach keeps one row; detach of an unheld group is a no-op;
  unknown group 404; employee toggle by token; bad token 404; `edit` and
  `show` payloads carry both keys). Full PHP suite green.

- [x] 4. **Frontend: the Settings Product groups tab.** `Settings/Index.vue`
  gains a second tab `product_groups` after `competences`, rendering
  `OrderedNameList` with `i18nPrefix="product_groups"` and
  `endpoint="/settings/product-groups"`; it takes a `productGroups` prop.
  `en.json`: `settings.tab.product_groups` and the `product_groups.*` key
  set mirroring `competences.*` (name, add, add_placeholder, move_up,
  move_down, delete, delete_confirm with `:count`, list_empty,
  checklist_empty, save_first, error.name_taken, flash.added/renamed/
  deleted). Vitest `SettingsIndex.test.js`: both tabs render; each tab
  mounts an `OrderedNameList` on its endpoint. `npm run test` and
  `npm run build` green.

- [ ] 5. **Frontend: the Profile tab with two sections.** On
  `Personal/Show.vue` and `Employees/Form.vue` rename the third tab from
  `competences` to `profile`: value `profile`, label `__('profile.tab')`,
  `data-testid="panel-profile"`. The panel body is a Competences section
  (`__('profile.competences_heading')` + `TagChecklist` on the competence
  endpoint) then a `CardSeparator` then a Preferred product groups
  section (`__('profile.product_groups_heading')` + `TagChecklist` on the
  product group endpoint). The manager editor shows the save-first
  message for the whole panel when not editing. Add `productGroups` and
  `productGroupIds` props to both pages; pass `productGroupIds` as
  `selectedIds`. `en.json`: `profile.tab` = "Profile",
  `profile.competences_heading`, `profile.product_groups_heading`; drop
  the now-unused `competences.tab`. Vitest: update `EmployeesForm.test.js`
  and `PersonalShow.test.js` for the Profile tab, both checklists, their
  endpoints, and the create-page save-first message. `npm run test` and
  `npm run build` green.

- [ ] 6. **Docs and full checks.** `doc/roadmap.md` — add the phase 3.6
  `Product groups` row and section. `doc/concept.md` — a Product Groups
  entry under Core Data and a preferred-product-groups note on the
  employee record. Set this `plan.md` header to `6/6`. Run Pint,
  `php artisan test`, `npm run test`, `npm run build`, and
  `php artisan migrate` on the dev database — all green.

## Not done / deferred

- Everything under the spec's "Non-goals", planning included.
- Manual browser pass — AGENTS.md leaves UI verification to the user.
