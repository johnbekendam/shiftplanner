# Availability Questions — Plan

Status: done — 5/5

Spec: `spec.md`. Roadmap phase 4. The Settings CRUD mirrors
`CompetenceController` (including `move`); the checklist mirrors
`TagChecklist` but writes one `PUT` with an `answer` flag.

- [x] 1. **Backend: weekday-only grid and the weekend cleanup.** Change
  the `weekday` route constraint from `[1-7]` to `[1-5]` on
  `PUT /employees/{employee}/availability/{weekday}/{shift}` and
  `PUT /personal/{token}/availability/{weekday}/{shift}`. New migration
  deletes `recurring_availabilities` rows with `weekday` in `(6, 7)`
  (no down path). Update `RecurringAvailabilityTest`: weekday `6` and
  `7` now assert `assertNotFound()` alongside the existing `0` and `8`.
  Full PHP suite green.

- [x] 2. **Backend: question records, Settings CRUD, and the toggle.**
  Migration `availability_questions` (`text` string, `position` integer,
  timestamps) and pivot `availability_question_employee`
  (`availability_question_id`, `employee_id`, timestamps, unique pair,
  both `cascadeOnDelete`). `AvailabilityQuestion` model (`$fillable`
  `text` + `position`; order-by-`position` global scope; `employees()`
  belongsToMany; `toPayload()` → `{ id, text }`). `Employee::availabilityQuestions()`.
  `AvailabilityQuestionFactory` (text a short question, position a
  sequence). `QuestionController` (`store`/`update`/`destroy`/`move`
  like `CompetenceController`; request field `name` written to `text`;
  `text` required, string, max 255, case-insensitive unique; `position`
  = max + 1). Concern `SetsQuestionAnswer::setAnswer(Request, Employee,
  AvailabilityQuestion)` — validate `answer` boolean required; `true`
  `syncWithoutDetaching`, `false` `detach`. `EmployeeQuestionController@update`
  and `PersonalQuestionController@update` (resolve token or 404).
  `SettingsController@index` adds `questions` as
  `{ id, name, position, holder_count }` (`withCount('employees')`),
  position order. Routes: behind `admin`
  `POST /settings/questions`, `PUT /settings/questions/{question}`,
  `DELETE /settings/questions/{question}`,
  `PUT /settings/questions/{question}/move`; behind `auth`
  `PUT /employees/{employee}/questions/{question}`; token-only
  `PUT /personal/{token}/questions/{question}`. `{question}` model-bound.
  Feature tests `QuestionConfigTest` (guest and non-admin blocked; index
  payload shape, order, `holder_count`; create appends; blank, too-long,
  duplicate-case rejected; rename; rename to duplicate rejected; delete
  removes the question and its pivot rows; `move` up and down swap;
  `move` past an end is a no-op) and `EmployeeQuestionTest` (guest
  blocked on the manager route; manager answers yes then no; a second
  yes keeps one pivot row; employee answers by token; bad token 404;
  unknown question 404; `edit` and `show` payloads carry `questions` and
  `questionAnswers`). Full PHP suite green.

- [x] 3. **Backend: availability-tab payloads.** `EmployeeController@edit`
  and `PersonalPageController@show` add `questions`
  (`AvailabilityQuestion::all()->map->toPayload()`, position order) and
  `questionAnswers` (`$employee->availabilityQuestions->pluck('id')`).
  Covered by the `EmployeeQuestionTest` payload assertions from step 2;
  no new file. Full PHP suite green.

- [x] 4. **Frontend: grid weekdays, heading, Settings tab, checklist.**
  `AvailabilityGrid.vue`: `WEEKDAYS = [1, 2, 3, 4, 5]`. `en.json`:
  remove `availability.weekday.6` and `.7`; set
  `availability.grid.heading` to "Shifts"; add `settings.tab.questions`
  ("Questions") and the `questions.*` set (`name` "Question", `add`
  "Add question", `add_placeholder` "New question", `move_up`,
  `move_down`, `delete`, `delete_confirm` with `:count`, `list_empty`,
  `error.text_taken`, `heading` "Questions",
  `flash.added`/`renamed`/`deleted`). `Settings/Index.vue`: a
  `questions` tab after `information`, before `competences`, rendering
  `OrderedNameList` on `/settings/questions` with `i18n-prefix="questions"`.
  New `resources/js/components/QuestionChecklist.vue` (props `items`,
  `answeredIds`, `endpoint`): one `CheckboxInput` per question, checked
  from `answeredIds`, a toggle sends `router.put(`${endpoint}/${id}`,
  { answer }, { preserveScroll, preserveState })`. `Employees/Form.vue`
  and `Personal/Show.vue`: add `questions` and `questionAnswers` props;
  render an `<h3>` (`questions.heading`) + `QuestionChecklist` between
  the grid section and the Holidays section, each side a `CardSeparator`,
  wrapped in `v-if="questions.length"` (and inside the existing
  `isEdit` guard on the manager create page). Vitest: update
  `AvailabilityGrid.test.js` (10 cells for two shifts; no Sat/Sun keys);
  new `QuestionChecklist.test.js` (a checkbox per question with the
  right checked state; toggle on writes `{ answer: true }`; toggle off
  writes `{ answer: false }`; both `preserveScroll`/`preserveState`);
  update `SettingsIndex.test.js` (the Questions tab mounts
  `OrderedNameList` on its endpoint and prefix); update
  `EmployeesForm.test.js` and `PersonalShow.test.js` (the section shows
  between grid and holidays when `questions` is non-empty, points the
  checklist at the right endpoint, and is absent when `questions` is
  empty; the manager create page still shows save-first).
  `npm run test` and `npm run build` green.

- [x] 5. **Docs and full checks.** `doc/roadmap.md` — the phase 4 row
  and section note that the grid is weekday-only and that configurable
  yes/no questions shipped. `doc/concept.md` — the recurring grid line
  says five weekdays; a Questions entry under Core Data and on the
  employee record; the Settings list in the Managers section.
  Set this `plan.md` header to `5/5`. Run Pint, `php artisan test`,
  `npm run test`, `npm run build`, and `php artisan migrate` on the dev
  database — all green.

## Not done / deferred

- Everything under the spec's "Non-goals": a not-answered state,
  non-boolean question types, per-scope visibility, a configurable
  weekday set, planner use of the answers, and a migration down path for
  the deleted weekend rows.
- Manual browser pass — AGENTS.md leaves UI verification to the user.
