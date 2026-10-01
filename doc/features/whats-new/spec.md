# What's New — Spec

A grilling session with the user settled this design before this document
was written.

## Problem

Users do not know when the application changes. A changed page can
confuse them, and a new feature can stay unused. The application has no
record of the changes that matter to users, and no place to show them.

## Solution

### Entries

Each release that users notice gets one Markdown file in
`resources/whats-new/`, for example `2026-10-01-demand-page.md`. The
front matter holds:

- `date` — the release date, `YYYY-MM-DD`.
- `title` — a short title.
- `audience` — one or more of `admin`, `manager` and `employee`.

The body is Markdown. The server renders it with `MarkdownRenderer`.
The text is in English only.

An admin sees the entries for `admin` and `manager`, because an admin
has all the screens of a manager. A manager sees the `manager` entries.
An employee sees the `employee` entries.

### Seen state

A migration adds a nullable `whats_new_seen_at` date to `users` and to
`employees`. An entry is unseen when its date is after this value, or
when the value is empty. A new user or a new employee gets the date of
the newest entry, so they see no older changes.

### Presentation

A page with unseen entries for the person opens a dialog. The dialog
lists these entries, newest first. A close of the dialog marks them as
seen: the server stores the date of the newest entry.

- Admins and managers see the dialog on every `AppLayout` page.
- Employees see the dialog on their personal page. The token route marks
  the entries as seen, also when employee changes are locked.

A "What's new" link opens the same dialog again, with all entries for
the person. Admins and managers find the link at the bottom of the
sidebar. Employees find it on the personal page. The link does not show
when the person has no entries.

### Process

`AGENTS.md` gets a rule: a change that users notice adds a What's new
entry. The first entries describe the Demand page, the move of the date
slots from Planning to Demand, and the rename of Roster to Schedule.

## Key decisions

- **Entries are files in the repository.** They ship with the code that
  they describe. They need no admin screen and no table.
- **An audience per entry.** Nobody reads about screens that they do not
  use.
- **Seen state in the database.** "Once" means once per person, not
  once per browser.
- **New people start current.** A new person has no use for the history
  of changes before they joined.
- **A dialog, with a link to open it again.** The dialog makes sure
  people see the change. The link keeps the history available.

## Non-goals

- An admin screen to write or edit entries.
- Translations of the entries.
- A "What's new" email.
- Seen state per entry. One date per person is enough.
