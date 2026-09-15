# Read-Only Competences - Spec

## Problem

Some competences need manager approval. Employees must see these
competences but must not add or remove them from their personal page.
Existing competences must stay editable by employees.

## Solution

Each competence gets a `read_only` flag. The flag defaults to `false`.
This keeps all existing competences editable by employees.

The Settings competences tab shows a read-only checkbox for each
competence. An administrator can change this flag with the competence
name and order.

Manager employee forms show editable competences first. They show
read-only competences below a separator. Managers can add or remove
either type of competence.

Personal pages show editable competences first. They show read-only
competences below a separator as disabled checkboxes. The server rejects
personal add and remove requests for a read-only competence.

## Key Decisions

- **Default is editable.** The database migration sets `read_only` to
  `false`. Existing competences keep their current employee behavior.
- **Managers retain control.** Manager employee routes do not restrict
  read-only competences. Managers can maintain all employee competence
  assignments.
- **Employees can see status.** The personal page shows all read-only
  competences as disabled checkboxes. Employees can see assigned and
  unassigned competences.
- **The server enforces the rule.** The personal controller rejects
  requests for a read-only competence. A modified browser request cannot
  change an assignment.
- **Each list is grouped.** The UI separates employee-editable and
  read-only competences. Both groups keep the configured competence
  order.

## Non-goals

- Change manager authorization or the existing settings authorization.
- Add an approval workflow for employee-editable competences.
- Add competence descriptions, categories, or expiry dates.
- Change planning eligibility rules.