# Server migration

## Problem

ShiftPlanner will move to a new server. The app's data lives in two Docker
volumes on the old host: the `postgres` database and the `app-storage`
volume (`storage/app` — theme tokens, the mailbox logo). No script backs
up or restores either volume today.

## Solution

Add two scripts that run on the server itself. Together they back up and
restore both volumes as one archive.

- `scripts/backup.sh` stops the `app` and `queue` containers, runs
  `pg_dump` in custom format, and tars the dump with the `storage/app`
  contents into one timestamped file. It then restarts the containers.
- `scripts/restore.sh` unpacks that archive. It runs `pg_restore` for the
  database, then copies the storage files into the target server's
  volumes. It refuses to run against a database that already has tables
  unless the operator passes `--force`.

A cutover runbook, `doc/features/server-migration/runbook.md`, lists the
full migration sequence: back up the old server, copy the archive and the
`APP_KEY` to the new server, deploy the app image there with the existing
release process, restore, verify the result, repoint DNS, then decommission
the old server.

## Key decisions

- **Reusable scripts, not a one-time runbook.** The data footprint is
  small and the scripts are cheap to build. The same scripts serve this
  migration and any later ad-hoc backup or disaster-recovery need.
- **One combined archive.** A single timestamped tar holds both the
  database dump and the storage files. The operator copies one file and
  runs one restore command.
- **Stop the app before the dump.** `backup.sh` stops `app` and `queue`
  and leaves `postgres` running. This gives a consistent snapshot. It
  then restarts the containers. A short downtime window during a planned
  migration is an acceptable trade for not reasoning about concurrent
  writes.
- **No network path assumed between servers.** Each script runs locally
  on its own server. The operator carries the archive between them by
  hand (scp, file share, USB). This matches how the release script's
  image tar already moves to a server today.
- **Custom-format `pg_dump` (`-Fc`).** This format compresses the dump and
  restores with `pg_restore`. It is the standard choice over plain SQL.
- **Restore refuses to overwrite by default.** If the target database
  already has tables, `restore.sh` stops unless the operator passes
  `--force`. This guards against restoring onto the wrong host or
  running the script twice by mistake.
- **`APP_KEY` transfer is a manual runbook step, not part of the backup
  archive.** Users already hold signed links (personal-page tokens,
  invites) signed with this key, so the new server must use the same
  value. The operator copies it by hand between `.env` files instead of
  storing it in a script or archive.
- **Linux and bash only.** Both scripts run on the server, which always
  runs Linux. This feature needs no PowerShell variant, unlike the
  release script that also runs on a developer's machine.
- **Tested against real Postgres, not SQLite.** `pg_dump` and
  `pg_restore` have no SQLite equivalent. An automated test starts the
  already-defined, currently idle `postgres` compose service, seeds known
  data, runs backup and restore into a second database, compares the
  result, then stops the service.

## Non-goals

- No scheduled or automatic backups. This ships manual scripts only.
- No direct network transfer between servers, such as rsync or an SSH
  pull. The archive moves by hand.
- No change to the existing release process for the app image itself.
  This feature covers data only.
- No S3 or other remote storage backend. `storage/app` stays on local
  disk.
