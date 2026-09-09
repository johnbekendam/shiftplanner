# Employee Details Layout — Spec

Let an employee set their own business line on the personal page, reorder
the shared detail fields, move weekly hours to the Availability tab, and
auto-save the Details tab.

## Problem

The personal page shows an employee their name, email and weekly hours,
but not their business line — only a manager can set it. The detail
fields also sit in an order that puts weekly hours between email and
business line, and a manager who edits the Details tab can forget to
press Save.

## Solution

### Shared field order — `EmployeeFields.vue`

The component drops the weekly-hours field. It renders, in order:

1. First name and Last name on one row (a two-column grid).
2. Email.
3. Business line. The component still hides this field when no business
   line exists.

`readonlyIdentity` still makes first name, last name and email
read-only. `disabled` now only affects the business-line select.

### Weekly hours — `WeeklyHoursField.vue` (new)

A small component: a labeled select with the 20–48 step-4 options plus
the below-minimum "0" option (the list that lived in `EmployeeFields`).
It renders at the top of the Availability tab on both the admin form and
the personal page.

- Admin edit form and personal page: it auto-saves on change, with no
  Save button, next to the grid / questions / holidays that already
  auto-save.
- Admin create: not shown. A new employee takes the column default (20).

### Admin form — `Employees/Form.vue`

**Create** shows only the Details fields in a single card — no tabs, no
other panels — with a Create button. `EmployeeController@store`
redirects to `/employees/{id}/edit`.

**Edit** keeps the tabbed layout. The Details tab keeps its Save button
and also auto-saves:

- when a field commits (a text field on blur / Enter / Tab, a select on
  change), and
- when the user switches away from the Details tab with unsaved changes.

`EmployeeController@update` redirects to `/employees/{id}/edit` so the
page and tab stay put after any save.

### Personal page — `Personal/Show.vue` and `PersonalPageController`

The Details tab keeps its Save button (shown only when editable) and
auto-saves the business line on change and on leaving the tab. The
Availability tab gains the weekly-hours field.

`PersonalPageController@show` adds `business_line_id` to the `employee`
payload and a `businessLines` list (`{ id, abbreviation }`).
`Personal/Show.vue` passes `businessLines` to `EmployeeFields` and adds
`business_line_id` to the form.

`PersonalPageController@update` validates `business_line_id`
(`nullable`, `integer`, `exists:business_lines,id`) next to the existing
`weekly_hours` rule and persists both. The route stays behind the
`employee.changes` middleware, so the business line follows the same
change-lock as every other personal-page control.

## Key decisions

- **Business line follows the change-lock.** It is editable on the
  personal page only while `allow_employee_changes` is on, like weekly
  hours, availability, holidays and competences. No new route or
  bypass.
- **One `PUT /personal/{token}` for both fields.** The Details and
  Availability tabs each submit the whole form. `weekly_hours` stays
  `required`; `business_line_id` is optional and nullable. No per-field
  personal endpoints.
- **Weekly hours leaves `EmployeeFields`.** It now lives only on the
  Availability tab, so a shared standalone component fits both callers
  and keeps the option list in one place.
- **Create is a single card.** With auto-save on the edit Details tab,
  a tabbed create screen with nothing to save into is noise. Create
  collects the four detail fields, then hands off to the edit page.
- **Save no longer returns to the list.** `store` and `update` redirect
  to `/employees/{id}/edit`. Auto-save that navigated away on every
  field commit would be unusable.
- **Keep the explicit Save button on both Details tabs.** The user
  asked for the button as reassurance and auto-save as the safety net.
- **Auto-save on tab switch, not on route leave.** Leaving the Details
  tab flushes a dirty form. The page does not intercept full navigation
  away.

## Non-goals

- The Employees list table is unchanged — it still shows Name, Business
  line and Weekly hours columns.
- No change to how the availability grid, holidays, questions or
  competences save.
- No auto-save on the admin create screen.
- `users` and the account pages are untouched.
- No new business-line management UI.
