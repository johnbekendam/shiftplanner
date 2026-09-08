# Competences — Spec

Roadmap phase 3.5. Built ahead of phases 2 and 3.

## Problem

A work centre will need specific skills. The planner must only assign an
employee to a work centre when the employee has the skills that the work
centre requires. The data model has no concept of a skill today.

This feature adds that concept. It lets a manager maintain a list of
competences, and it lets a manager or an employee mark which competences
each employee holds. It does not touch planning.

## Solution

### Competence records

A new `competences` table:

| column | notes |
| --- | --- |
| `id` | |
| `name` | required, unique (case-insensitive) |
| `position` | integer, sets the manual order |
| timestamps | |

A competence has a name only. No description, no active flag.

### Employee competences

A new `competence_employee` pivot table:

| column | notes |
| --- | --- |
| `competence_id` | foreign key, cascade on competence delete |
| `employee_id` | foreign key, cascade on employee delete |

A checked box is a row. An unchecked box is the absence of a row. The
pivot is unique on (`competence_id`, `employee_id`).

`Employee::competences()` and `Competence::employees()` are
`belongsToMany`. `Competence` orders by `position`.

### Settings page

The sidebar gets a `Settings` item. It links to `GET /settings`, one
Inertia page.

The page is a single `Card`. The card header is the shared `Tabs`
component, one tab per configuration category. The only tab now is
`Competences`. The active tab is client state. There is no per-tab URL.

### Competences tab

A row list with an add-row form below it, in the style of `HolidayList`.

Each row shows:

- The name as an inline text field. An edit that loses focus, or an
  `Enter` key, sends `PUT /settings/competences/{competence}` with the
  new name and reloads. The server checks uniqueness again.
- An **up** and a **down** button. Each sends
  `PUT /settings/competences/{competence}/move` with a direction. The
  server swaps the position with the neighbour and reloads. The first
  row has no up button. The last row has no down button.
- A delete button. It opens a confirm dialog that names the holder count
  ("3 employees have this competence. Delete it?"). On confirm it sends
  `DELETE /settings/competences/{competence}`. The competence row and its
  pivot rows are removed.

The add-row form sends `POST /settings/competences` with a name. The new
competence takes `position` = current maximum + 1.

An empty list shows a short message.

The `/settings` payload carries `competences` as
`{ id, name, position, holder_count }`, in `position` order.

All `/settings` routes sit behind `auth`.

### Employee competence checkmarks

Both the manager and the employee maintain the checkmarks. This matches
holidays and the availability grid. One shared set. Last write wins.

The manager editor (`Employees/Form.vue`) and the personal page
(`Personal/Show.vue`) each get a third tab: `Competences`, after
`Details` and `Availability`.

The tab renders every competence as a checkbox, in `position` order. A
click writes at once, with no Save button, and keeps the open tab:

- Manager: `PUT /employees/{employee}/competences/{competence}` adds the
  link. `DELETE` on the same path removes it.
- Employee: `PUT /personal/{token}/competences/{competence}` and the
  matching `DELETE`.

On the employee create page the tab asks the user to save the employee
first, the same as the availability tab.

If no competences exist, the tab shows a short message.

The `edit` and `show` payloads gain `competences` (every competence, as
`{ id, name }`) and `competenceIds` (the ids the employee holds).

## Key decisions

- **Name only.** A competence is a label the manager checks against a
  work centre. A description or a retire flag is a later, non-breaking
  migration if the need appears.
- **Delete asks, then cascades.** There is no active flag, so a
  competence can leave the list. The confirm dialog names the holder
  count so the manager deletes with the facts. The pivot rows cascade on
  the foreign key. This matches the prototype's trust-the-manager tone.
- **Manual order.** A manager groups related competences. The order is
  the same on the config tab and on every checkmark list. `position`
  moves by neighbour swap through up and down buttons. No drag library
  enters the project.
- **Inline rename.** The checkmarks attach to the competence, so a
  delete-and-re-add rename would drop every employee's mark. An inline
  field renames in place and re-checks uniqueness.
- **Both surfaces write.** A manager can maintain any employee field, and
  the concept has the employee check their own. Holidays and the grid
  already work this way.
- **One `/settings` route, client tab state.** The `Details` and
  `Availability` tabs on the employee pages already hold their state on
  the client. The settings card follows that.
- **Settings landing page, not a sidebar group.** One sidebar item opens
  a page whose card holds a tab per category. Departments and day
  schedules join as tabs later without new navigation.

## Non-goals

- Planning. The `/solve` contract only notes that a work-centre
  competence filter will read this data later.
- A description, a category, or a retire or archive state on a
  competence.
- Proficiency levels or an expiry date on an employee's competence.
- A manager approval step for an employee's self-marked competence.
- Deep links or breadcrumbs to a specific settings tab.
- A drag-to-reorder interaction.
- Bulk assignment of a competence to many employees at once.
