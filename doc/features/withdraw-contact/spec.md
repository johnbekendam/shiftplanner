# Withdraw Contact

## Problem

An employee can delete their own record with the Withdraw button on the personal page. This removes the employee's data and planning without any check by a planner.

## Solution

An employee can no longer withdraw on the personal page. The Withdraw button stays. It now opens a card that tells the employee to contact the business line responsible.

- **Card content:** the name and email address of the business line responsible of the employee. The email address is a `mailto:` link.
- **Fallback:** when there is no name to show, the card tells the employee to contact their planner. This happens when the employee has no business line, the business line has no responsible person, or that person is not active.
- **Server:** remove the `DELETE /personal/{token}` route and `PersonalPageController::destroy`. A direct request gets a 405 response, and the employee record stays.
- **Data:** `PersonalPageController::show` sends a new `businessLineResponsible` prop with `name` and `email`, or `null`.

## Key Decisions

- Remove the withdraw endpoint. A hidden button alone would not stop a direct request.
- Keep the button and its position. The employee still finds the action, and the card explains what to do.
- Skip an inactive responsible user. An employee cannot get an answer from a deactivated account.
- Remove the unused withdraw confirmation dialog and its texts.
- A planner can still delete an employee from the employee pages. This does not change.

## Non-goals

- A request form or an email from the card to the responsible person.
- A change to the employee deletion for planners.
- A workcenter contact in the card.
