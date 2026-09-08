# Product Groups — Spec

Roadmap phase 3.6. Built after competences (phase 3.5) and ahead of
phases 2 and 3. It reuses the competences pattern.

## Problem

An employee prefers to work on some product families and not others. The
planner will use that preference later. The data model has no product
group concept, and no place for an employee preference.

This feature adds a configurable product group list and a per-employee
set of preferred product groups. It does not touch planning.

## Solution

### Product group records

A new `product_groups` table, the same shape as `competences`:

| column | notes |
| --- | --- |
| `id` | |
| `name` | required, unique (case-insensitive), max 100 |
| `position` | integer, sets the manual order |
| timestamps | |

A `ProductGroup` model with a position-ordered default scope and a
`toPayload()` that returns `{ id, name }`.

### Employee preferences

A new `employee_product_group` pivot table:

| column | notes |
| --- | --- |
| `product_group_id` | foreign key, cascade on delete |
| `employee_id` | foreign key, cascade on delete |

A row means "this employee prefers this product group". There is no rank
and no other state. The pivot is the composite primary key.
`Employee::productGroups()` is a `belongsToMany`.

### Config — Settings page

The Settings page gets a second tab, **Product groups**, after
Competences. Both tabs render the same list component against different
endpoints.

`ProductGroupController` mirrors `CompetenceController`:

- `store` — name required, string, max 100, case-insensitive unique;
  `position` = current maximum + 1.
- `update` — rename, case-insensitive unique ignoring self.
- `destroy` — delete the row; the pivot cascades. The client asks for
  confirmation first and names the holder count.
- `move` — body `direction` in `up|down`; swap `position` with the
  neighbour; a no-op past an end.

Routes behind `auth`:

- `POST /settings/product-groups`
- `PUT /settings/product-groups/{productGroup}/move`
- `PUT /settings/product-groups/{productGroup}`
- `DELETE /settings/product-groups/{productGroup}`

`SettingsController@index` adds `productGroups` as
`{ id, name, position, holder_count }`, in position order.

### Employee preference endpoints

Both the manager and the employee set the preferences. One shared set,
last write wins, the same as competences. Each toggle writes at once,
with no Save button.

A `TogglesProductGroup` concern holds an idempotent attach and detach.
`EmployeeProductGroupController` (`update` attaches, `destroy` detaches)
sits behind `auth`. `PersonalProductGroupController` resolves the token
or 404s, then does the same.

- `PUT` and `DELETE /employees/{employee}/product-groups/{productGroup}`
- `PUT` and `DELETE /personal/{token}/product-groups/{productGroup}`

`EmployeeController@edit` and `PersonalPageController@show` payloads gain
`productGroups` (every group, `{ id, name }`) and `productGroupIds` (the
ids the employee prefers).

### UI — the Competences tab becomes the Profile tab

On both `Personal/Show.vue` and `Employees/Form.vue`, the third tab
(today "Competences") is renamed **Profile**
(`data-testid="panel-profile"`). It holds two sections:

1. **Competences** heading, then the competences checklist (existing
   competence endpoints).
2. A `CardSeparator`.
3. **Preferred product groups** heading, then the product groups
   checklist (product group endpoints).

On the manager create page the whole Profile panel shows the "save the
employee first" message, the same as the competences tab does today.

### Shared front-end components

The two competences components are generalised and used by both features:

- `CompetenceList.vue` → **`OrderedNameList.vue`**. Props: `items`,
  `endpoint`, and label keys (add label, add placeholder, name header,
  move up, move down, delete, delete confirm, empty). Behaviour is
  unchanged: inline rename on field commit, up and down controls in their
  own columns, delete behind a holder-count confirm, an add row, an empty
  state.
- `CompetenceChecklist.vue` → **`TagChecklist.vue`**. Props: `items`,
  `selectedIds`, `endpoint`, `emptyKey`.

The Settings page and the Profile tab pass the right endpoints and label
keys. The old component files are removed. Competences imports, i18n, and
tests move to the shared components.

## Key decisions

- **Mirror competences.** Same table shape, same controller shape, same
  reorder and delete behaviour. A preference and a skill store and edit
  the same way.
- **Preferred is a plain pivot.** A row is the whole meaning. No rank, no
  level. The `/solve` contract, when it comes, reads one id list.
- **Both surfaces write, write on toggle.** The manager maintains any
  employee field, and the employee sets their own preference. Each click
  writes, like the competences checklist.
- **One Profile tab, two sections.** Competences and product groups are
  both "about this person", short, and check-box shaped. A second tab per
  concept crowds the bar. The tab is renamed Profile; the two sections
  carry their own headings.
- **Generalise the two Vue components, keep the back end parallel.** The
  list and checklist are identical bar their labels, so they become one
  shared pair. The models and controllers are small and clearer kept
  separate than behind a generic base.
- **New color or component work:** none. The shared components reuse the
  tokens and `Input/*` pieces the competences components already use.

## Non-goals

- Planning. No objective term, weight, or `/solve` sketch for the
  preference. A later fairness session designs that.
- A rank or weight on an employee's preferred product group.
- A description, category, or archive state on a product group.
- A manager approval step for an employee's preference.
- A shared back-end base class or trait across competences and product
  groups.
- Deep links to a specific Settings or Profile tab.
