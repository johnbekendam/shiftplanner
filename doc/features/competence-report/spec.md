# Competence report

## Problem

Managers need a report that shows employees by competence coverage.
They need to find employees who have a selected competence.
They also need to find employees who miss a selected competence.

The report must make it easy to open the employee competence editor.

## Solution

Add a competence report tab to the existing Reports page.
The competence report is the first and default report tab.
The report uses one selected competence and a mode selector.
The default mode is missing selected competence.

The top controls read like a sentence.
The first select says whether the employee has or misses the selected competence.
The second select chooses the competence.

In has mode, an employee matches when they have the selected competence.
In missing mode, an employee matches when they miss the selected competence.

When no competence is selected, the report shows an empty prompt.
It does not show employee rows.

Each row shows the employee name and the selected competence name.

Clicking an employee opens the employee edit page on the Competences tab.

## Key decisions

- Use the existing Reports page. This keeps report behavior and navigation in one place.
- Make the competence report the first and default tab. This puts the new competence workflow first.
- Use a mode selector for has and missing reports. This supports both search cases in one screen.
- Default to missing mode. This makes the report useful for competence gaps first.
- Select only one competence at a time. This keeps the report sentence readable.
- Do not add business-line or confirmed filters in the first version. This keeps the first version small.
- Show no rows before the user selects a competence. This prevents an accidental all-employee report.
- Open the employee edit page on the Competences tab. This takes the user directly to the follow-up action.

## Non-goals / scope boundaries

- Do not add a separate report page.
- Do not change the Employees index filters.
- Do not add business-line or confirmed filters.
- Do not add any/all match mode selection.
- Do not add multi-competence selection.
- Do not add bulk email actions for this report.