# What's New — Spec

A grilling session with the user settled this design before this document
was written. A second session replaced the popup dialog with a badge and
a page, and removed employees from the scope.

## Problem

Users do not know when the application changes. A changed page can
confuse them, and a new feature can stay unused. The application has no
record of the changes that matter to users, and no place to show them.

## Solution

### Entries

Each release that users notice gets one Markdown file in
`resources/whats-new/`, for example `2026-10-01-demand.md`. The front
matter holds:

- `date` — the release date, `YYYY-MM-DD`.
- `title` — a short title.
- `audience` — `admin`, `manager`, or both.

The body is Markdown. The server renders it with `MarkdownRenderer`.
The text is in English only.

An admin sees the entries for `admin` and `manager`, because an admin
has all the screens of a manager. A manager sees the `manager` entries.

### Seen state

`users.whats_new_seen_at` is a nullable date. An entry is unseen when
its date is after this value, or when the value is empty. A new user
gets the date of the newest entry, so they see no older changes as new.

### Badge

The sidebar has a "What's new" item at the bottom. It shows a badge
with the number of unseen entries. Without unseen entries, the badge
does not show. Without any entries, the item does not show.

### Page

The item opens `/whats-new`, a page in `AppLayout` with one card:

- Left: a list of all entries of the user, newest first, with the title
  and the date. An entry that was unseen when the page opened shows a
  "New" label.
- Right: the selected entry, with Previous and Next buttons.

The page opens on the newest entry. `?entry=<id>` selects an entry. On
narrow screens the list stacks above the entry.

### Redirect

The dashboard is the entry point of the app: `/` and the login go there.
A user with unseen entries who opens the dashboard goes to `/whats-new`
first. A link to another page opens that page, and the badge shows the
unseen entries there.

### Seen state on the page

A visit to the page marks all entries as seen. The server stores the
date of the newest entry before it sends the page, so the badge clears
at once.

### Process

`AGENTS.md` has a rule: a change that users notice adds a What's new
entry. The first entries describe the Demand page and the rename of
Roster to Schedule.

## Key decisions

- **Entries are files in the repository.** They ship with the code that
  they describe. They need no admin screen and no table.
- **An audience per entry.** Nobody reads about screens that they do not
  use.
- **A badge and a redirect, not a popup.** The redirect on the entry
  point makes sure that the user sees a change once. The badge shows it
  everywhere else. Neither covers the work of the user.
- **A redirect only on the entry point.** A link to a specific page must
  open that page.
- **A page with a list.** The history has room to grow. The list and
  Previous/Next make old entries easy to find. A link can point to one
  entry.
- **A visit marks all entries as seen.** One date per user is enough,
  and the "New" label still shows what was new during that visit.
- **Seen state in the database.** "Once" means once per user, not once
  per browser.
- **New users start current.** A new user has no use for the history of
  changes before they joined.

## Non-goals

- Employees and the personal page. A later feature can add them.
- An automatic popup.
- Seen state per entry.
- An admin screen to write or edit entries.
- Translations of the entries.
- A "What's new" email.
