# Manual Planning Hard Caps

## Problem

Manual assignment currently checks capacity and scheduling eligibility, but it can place an employee beyond an existing hard planning cap. This lets the manual schedule contradict the rules used by automatic planning. The employee picker also presents employees who cannot legally receive the assignment under those caps.

## Solution

Apply the existing hard `max_hours_per_week` and `max_shifts_per_day` planning rules to manual planning.

- The eligible-employee endpoint excludes an employee when the proposed assignment would exceed either enabled hard cap.
- The manual assignment endpoint rejects a direct request that would exceed either enabled hard cap, with a validation error on `employee_id`.
- Both checks use the target planning cycle and count assignments across all workcenters.

## Key decisions

- Only rules whose mode is `hard` block manual assignments. Soft rules do not affect the picker or assignment endpoint.
- Max-hours checks use the existing 14-day cycle anchored by the Monday of the week containing `PlanningSettings.period_start`.
- Max-shifts checks apply to the target date.
- Every existing assignment counts toward manual cap usage, including assignments that are unfixed or unpublished. This is intentionally stricter than the automatic planner's locked-assignment accounting.
- The existing cap definitions and values are reused. No new cap types, settings, or database fields are added.
- Server-side validation is authoritative because direct requests can bypass the picker.

## Non-goals / scope boundaries

- Do not add new cap types, per-employee overrides, configurable overage, or new planning-rule UI.
- Do not change soft-rule scoring or automatic plan generation behavior.
- Do not change shift capacity, availability, holiday, overlap, or workcenter eligibility behavior.
- Do not add reassignment, drag-and-drop, or broader manual-planning workflows.
