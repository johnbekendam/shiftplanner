# Availability defaults — Spec

## Problem

New shifts and new employees currently show missing availability as available. This can schedule people for shifts before they have set their availability. Existing employee behavior must stay unchanged during the migration.

## Solution

Keep sparse availability storage. Backfill every missing weekday and shift combination for existing employees as an explicit `available` row. Preserve all existing rows and values. After the migration, a missing row means `null` (not set) and is treated as unavailable. New employees and newly added shifts remain sparse, so their availability starts as null. Store an explicit `available` row when a user selects Available.

## Key decisions

- Use sparse rows instead of creating null rows for every combination.
- Backfill only missing combinations that exist when the migration runs.
- Do not change existing availability rows or their values.
- Treat missing availability as not set and unavailable for scheduling.
- Store `available`, `not_preferred`, and `unavailable` as explicit row levels after a user makes a choice.
- Apply the same null default to manager-created employees, self-signup employees, and new shifts.

## Non-goals

- Changing existing explicit availability levels.
- Changing shift visibility, weekly hours, holidays, or availability questions.
- Adding weekend availability.
