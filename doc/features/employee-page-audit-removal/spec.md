# Employee Page Audit Removal

## Problem

The employee page includes an audit history tab. This duplicates the global audit log and adds data and interface code to each employee page.

## Solution

Remove the audit history from the employee page. Keep audit event recording and the global audit log unchanged.

Remove the employee page tab, timeline component, audit payload, model relation, translations, and timeline-only tests. An old `?tab=audit` URL must open the default Settings tab.

Update the existing employee audit specification so it describes only the retained audit behavior.

## Key Decisions

- Remove all code that only supports the employee page audit history. This avoids unused payloads and components.
- Keep audit event storage and creation unchanged. The global audit log still needs this data.
- Keep the global audit page, filters, navigation, permissions, and tests unchanged.
- Let an unsupported `audit` tab value use the existing default tab behavior. No redirect or migration is necessary.
- Update the existing employee audit specification. Its requirements must match the current product.

## Non-Goals / Scope Boundaries

- Do not change which employee actions create audit events.
- Do not change audit event data, retention, or backup behavior.
- Do not change the global audit log interface, filters, access rules, or navigation.