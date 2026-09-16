Status: in progress — 1/4

- [x] 1. Reports route, controller, and missing-availability query
      Add `GET /reports` in the `admin` middleware group. A
      `ReportController@index` builds the missing-availability list for a
      given `shift` query param. It applies the `weekly_hours > 0` filter,
      the confirmed/unconfirmed rule, and the optional `business_line`
      filter. It returns an Inertia page with employees, shifts, and
      business lines as props. No Vue UI yet beyond a stub page.

      `tests/Feature/ReportsTest.php` covers:
      - An employee with no rows for the shift appears.
      - An employee with a row for the shift does not appear.
      - A `weekly_hours = 0` employee does not appear.
      - An unconfirmed employee does not appear by default, and appears
        when the toggle param is set.
      - The business-line filter narrows the list.
      - No `shift` param returns an empty list.

- [ ] 2. Reports page UI: tab container, controls, and table
      Build `resources/js/pages/Reports/Index.vue`. Use `Card` with
      `Tabs.vue` in the header, with one tab: "Missing availability". Add
      a shift `SelectInput`, a business-line `SelectInput`, and a
      confirmed-toggle `CheckboxInput`. Add a table with the four columns
      from the spec. Add the "Reports" nav entry in `AppLayout.vue` and
      the `nav.reports` key in `en.json`.

      `tests/js/Reports.test.js` covers:
      - The table renders one row per employee prop.
      - A change to the shift, business-line, or toggle control navigates
        with the matching query params.

- [ ] 3. Row selection and "Email selected" button
      Add a checkbox per row, a "select all" checkbox, and an "Email
      selected (N)" button. Disable the button at zero selections.

      `tests/js/Reports.test.js` gains:
      - Selecting rows updates the button's count and enabled state.
      - "Select all" toggles every visible row.
      - The button builds the `/mailbox?...` URL with one
        `employee_ids[]` entry per selected row.

- [ ] 4. Compose handoff: plural employee_ids[] support
      Extend `MailboxController::composePayload()` to read
      `employee_ids[]` as an array of integers. Merge it with the existing
      singular `employee` param into the preselected recipient set. Wire
      the button from step 3 to navigate to
      `/mailbox?tab=compose&type=custom&employee_ids[]=...`.

      `tests/Feature/MailboxTest.php` gains:
      - Composing with multiple `employee_ids[]` seeds `RecipientPicker`'s
        `employee_ids` with all of them.
      - The existing singular `employee` param still works unchanged.
