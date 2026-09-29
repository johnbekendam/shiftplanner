# Planning Filter Selection — Plan

Status: in progress — 2/3

## Steps

- [x] 1. **Store the hidden ids.** Add a nullable JSON column
  `planning_filter` to `users` and cast it to an array. Add
  `PUT /planning/filter`. It validates `hidden_workcenter_ids` and
  `hidden_shift_ids` as integer arrays, saves them for the current user,
  and returns 204. Test in `tests/Feature/PlanningFilterTest.php`.
- [x] 2. **Load and save the selection on the page.** `SchedulingController@index`
  sends `hiddenWorkcenterIds` and `hiddenShiftIds`. `Scheduling.vue` starts
  from these props and sends each checkbox change to `/planning/filter`.
  Remove the `sessionStorage` code. Test the prop in a feature test and the
  page in `tests/js/Scheduling.test.js`.
- [ ] 3. **"All" checkbox in each card header.** Add `scheduling.filter_all`
  to `en.json`. The checkbox is checked when all items show. A click
  hides all items or shows all items, and saves the result. Test in
  `tests/js/Scheduling.test.js`.
