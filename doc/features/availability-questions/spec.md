# Availability Questions — Spec

Roadmap phase 4. Three linked changes to the Availability tab: the
recurring grid drops the weekend, its heading is renamed, and a
manager-defined list of yes/no questions is added.

## Problem

The recurring availability grid has seven weekday columns. The team runs
shifts on weekdays only, so Saturday and Sunday are always empty noise.

Weekend and edge-week cover is still a real question, but at a coarser
grain than the per-shift grid: "can we call you in for a weekend?",
"are you reachable in week 53?". There is nowhere to record a plain
yes/no answer like that, and no way for a manager to add such a question
without a code change.

The grid heading "Weekly pattern" does not say what the grid holds now
that each row is a named shift.

## Solution

### 1. Weekday-only recurring grid

- `AvailabilityGrid.vue` iterates weekdays `1`–`5` (Monday–Friday). The
  cell button, the three-state cycle, the colours, and the legend do not
  change.
- Both per-cell routes tighten the `weekday` segment from `[1-7]` to
  `[1-5]`:
  - `PUT /employees/{employee}/availability/{weekday}/{shift}`
  - `PUT /personal/{token}/availability/{weekday}/{shift}`
  A weekend write is now a 404.
- A data migration deletes every `recurring_availabilities` row with
  `weekday` 6 or 7. The rows are synthetic; there is no down path.
- `availability.weekday.6` and `availability.weekday.7` are removed from
  `en.json`.

Nothing else reads weekday 6/7. The dashboard already scores a weekend
as zero FTE on its own.

### 2. Heading rename

`availability.grid.heading` changes from "Weekly pattern" to "Shifts".
The key name is unchanged.

### 3. Configurable questions

#### Data

New table `availability_questions`:

| column | notes |
| --- | --- |
| `id` | |
| `text` | required, case-insensitive unique, max 255 |
| `position` | integer, manual order |
| timestamps | |

New pivot `availability_question_employee`:

| column | notes |
| --- | --- |
| `availability_question_id` | foreign key, cascade on delete |
| `employee_id` | foreign key, cascade on delete |
| timestamps | |

Unique on the pair. A row means the employee answered **yes**. No row
means **no**. There is no "not answered" state.

`AvailabilityQuestion` model: `$fillable` is `text` and `position`; a
default scope orders by `position`; `employees()` is a `belongsToMany`;
`toPayload()` returns `{ id, text }`. `Employee` gains
`availabilityQuestions()`.

#### Settings — new "Questions" tab

`/settings` stays admin-only. The tabbed card gains a **Questions** tab
after **Information** and before **Competences**. It renders the shared
`OrderedNameList` on `/settings/questions` with the `questions` i18n
prefix — the same add / rename / reorder / delete list that Competences
and Business lines use. The question text is the list's single field.

`QuestionController` mirrors `CompetenceController`:

- `store` — `text` required, string, max 255, case-insensitive unique;
  `position` is the current max plus one.
- `update` — rename, unique ignoring self.
- `destroy` — delete the row; the pivot cascades.
- `move` — body `direction` in `up|down`; swap `position` with the
  neighbour; a no-op past either end.

The request field is `name` (what `OrderedNameList` sends); the
controller writes it to the `text` column.

Routes behind `admin`: `POST /settings/questions`,
`PUT /settings/questions/{question}`,
`DELETE /settings/questions/{question}`,
`PUT /settings/questions/{question}/move`.

`SettingsController@index` adds `questions` as
`{ id, name, position, holder_count }` in position order, where
`holder_count` is the count of employees who answered yes. The delete
confirm names that count.

#### Availability tab — the checklist

A new `QuestionChecklist.vue`, shown on the **Availability** tab of both
`Employees/Form.vue` and `Personal/Show.vue`, between the shift grid and
the Holidays section, divided from each by a `CardSeparator`. It has its
own `<h3>` heading (`questions.heading`).

- Props: `items` (`{ id, text }`), `answeredIds`, `endpoint`.
- One `CheckboxInput` per question, labelled with the text, checked when
  the id is in `answeredIds`.
- A toggle sends `PUT ${endpoint}/${id}` with `{ answer: <bool> }`,
  `preserveScroll` and `preserveState`, no success banner — the same
  feel as a grid cell.
- When no question is configured the whole section (heading and
  separators included) does not render. The parent guards with
  `v-if`.
- On the manager create page, where there is no employee yet, the
  section follows the existing "save the employee first" treatment,
  like Holidays.

Endpoints:

- Manager: `PUT /employees/{employee}/questions/{question}` —
  `EmployeeQuestionController@update`.
- Employee: `PUT /personal/{token}/questions/{question}` —
  `PersonalQuestionController@update`.

Both take `answer` (boolean, required). `true` upserts the pivot row;
`false` detaches it. A shared `SetsQuestionAnswer` concern holds the
logic, mirroring `SetsRecurringAvailability`. `{question}` is
model-bound; an unknown id is a 404.

`EmployeeController@edit` and `PersonalPageController@show` payloads gain
`questions` (`{ id, text }`, position order) and `questionAnswers` (the
answered ids).

## Key decisions

- **Weekday-only, no config.** The team has no weekend shift record.
  Rather than a settings toggle for which days the grid shows, the grid
  is simply Monday–Friday. A weekend need is a question instead.
- **Delete the weekend rows.** The data is synthetic and the rows are
  now unreachable from the UI and the routes. Leaving them would only
  mislead a later reader of the table.
- **Questions store yes only.** A checkbox is binary and the questions
  are opt-in ("can we contact you…"). A present pivot row is yes, its
  absence is no — the same shape the grid uses for `available`. No
  nullable answer, no backfill when a question is added.
- **Reuse `OrderedNameList`.** The Settings list is add / rename /
  reorder / delete over one text field — exactly Competences. The only
  seam is the `name` request field mapping to the `text` column.
- **One `PUT` with an `answer` flag.** The toggle is one route that
  takes the new state, not a `PUT`/`DELETE` pair. The grid cell already
  works this way with its `level`.
- **Section hidden when empty.** A manager who has defined no questions
  sees nothing extra on the Availability tab — no empty heading.
- **Free text, no built-in questions.** "Weekend", "week 53" and the
  rest are whatever the manager types. Nothing is seeded and no question
  has special behaviour.
- **New `QuestionChecklist`, not `TagChecklist`.** `TagChecklist` writes
  with a `PUT`/`DELETE` pair and shows an empty message. The question
  checklist takes an `answer` flag and renders nothing when empty.

## Non-goals

- A "not answered" state, or any view of who has not responded.
- Question types other than yes/no — no free-text or multiple choice.
- Per-question visibility rules (per Business Line, per employee).
- Making the grid's weekday set configurable.
- Any planner or `/solve` use of the answers. This feature only stores
  them.
- Restoring deleted weekend availability rows on migration rollback.
