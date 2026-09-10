# Default weekly hours — Spec

## Problem

New employees are created with a weekly-hours value of 20 by default. The product requirement is to start a new employee at 32 hours so staffing plans reflect the standard baseline immediately.

## Solution

Use 32 as the default weekly-hours value whenever a new employee record is created through the manager form or the import flow. The minimum allowed value remains 20; the default is a higher baseline only.

## Key decisions

- Keep the fixed weekly-hours selection list unchanged. Only the default value changes.
- Maintain the existing minimum validation at 20 while raising the default to 32.
- Apply the default in both the UI form and the persistence layer so create flows stay consistent.
- Leave the explicit minimum/option logic alone unless the default is shown in the form or import path.

## Non-goals

- Changing the allowed weekly-hours option set.
- Raising the minimum weekly-hours constraint from 20.
- Changing the employee-hours business logic beyond the default-value starting point.
