# VPS Deployment

This describes how ShiftPlanner is deployed to a VPS by pulling code from
git directly on the server. It is one of two deploy paths; the other is
the self-contained container bundle in `container-deployment.md`. Pick one
per environment — do not run both against the same database.

This path assumes the VPS is set up by a shared `vps-setup` bootstrap
(Ubuntu, Caddy, PHP-FPM, MariaDB, Supervisor, ufw, fail2ban) and that this
app's Unix user, PHP-FPM pool, MariaDB database, Supervisor queue worker,
scheduler cron entry, git deploy key, and `/srv/apps/shiftplanner/`
directory already exist on the server (provisioned once via that repo's
`provision-app.sh shiftplanner <domain> <git-repo>`).

There is no build or zip step on your machine and no upload endpoint. Each
app runs as its own dedicated Unix user with its own PHP-FPM pool,
isolated from every other app on the box.

## Repository requirements

- `composer.lock` and `package-lock.json` must be committed — the deploy
  script installs dependencies and builds assets on the server from these.
- `vendor/`, `node_modules/`, and `public/build/` must be gitignored —
  they are generated on the server during each update, not committed.
- `.env` and the runtime contents of `storage/` must be gitignored — these
  are provisioned once on the server and never touched by `git reset
  --hard`.
- Deploys track the `main` branch.

### State that `git reset --hard` overwrites

`update.sh` hard-resets to `origin/main` on every deploy. Anything tracked
in git is reverted to the committed version, so operator state must live
outside tracked files:

- `storage/app/private/logo.json` is currently **tracked**. A custom logo
  set on the server through the Theme Builder is reverted on the next
  deploy. Gitignore this file before using this path in anger, or accept
  that the shipped default logo wins on every deploy.
- Custom logo images (`public/images/logo-custom.*`) are untracked and
  survive because `update.sh` runs `git reset --hard`, not `git clean`.
  Do not add a `git clean` step.
- `.env` and `storage/logs/`, `storage/framework/cache|sessions|views`
  are safe — nested `.gitignore` files keep their contents untracked.

## Database

This path uses MariaDB (or MySQL), provisioned per-app by `vps-setup`. The
server `.env` sets:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=shiftplanner
DB_USERNAME=shiftplanner
DB_PASSWORD=<from provisioning>
```

The container path (`container-deployment.md`) uses PostgreSQL instead.
The migrations run on both engines; keep a single engine per environment.

## update.sh

`/srv/apps/shiftplanner/update.sh` is generated once at provisioning time
with a marked **APPLICATION-SPECIFIC** section for this project's build
steps. Re-running the provisioning script never overwrites an existing
`update.sh`, so it is safe to hand-edit.

This project's APPLICATION-SPECIFIC section (kept in `deploy/build.sh`):

```bash
composer install --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build
"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache
```

The rest of the script (git fetch/checkout, maintenance mode, queue worker
stop/start) is generic and should not need touching per-project.

When `deploy/build.sh` changes in the repo, copy the new steps into the
server's `update.sh` by hand before the next deploy.

## First deploy

1. Add the deploy key (printed at provisioning time) as a **read-only**
   Deploy Key on this repo's GitHub settings page.
2. Fill in the server `.env`: `APP_KEY` (`php artisan key:generate`),
   `APP_URL`, the `DB_*` values above, `MAIL_MAILER` and the `GRAPH_*`
   values if Microsoft Graph mail is used, and `SESSION_DRIVER=database`,
   `QUEUE_CONNECTION=database`, `CACHE_STORE=database`.
3. Fill in `update.sh`'s APPLICATION-SPECIFIC section if not done already
   (see above).
4. Run the initial checkout and build over SSH:

   ```bash
   ssh <vps-alias>
   sudo -u shiftplanner /srv/apps/shiftplanner/update.sh
   ```

5. Create the first admin user. ShiftPlanner has no production database
   seeder — `DatabaseSeeder` also loads demo employees — so create the
   account directly. From `/srv/apps/shiftplanner`, as the app user:

   ```bash
   sudo -u shiftplanner php artisan tinker
   ```

   ```php
   App\Models\User::updateOrCreate(
       ['email' => 'admin@example.com'],
       ['name' => 'Admin', 'role' => App\Models\User::ROLE_ADMIN, 'password' => 'change-me-now'],
   );
   ```

   Then sign in and set a real password on `/account`, or use the "email me
   a code" option on the login screen.

ShiftPlanner serves uploaded files from `public/images/` and stores no
files on a linked `storage/app/public` disk, so `php artisan storage:link`
is not needed on this path.

## Subsequent deploys

Push to `main`, then re-run the same command over SSH (or wire it into
CI — a GitHub Actions job that SSHes in and runs `update.sh`). There is no
webhook-triggered auto-deploy.

```bash
ssh <vps-alias> sudo -u shiftplanner /srv/apps/shiftplanner/update.sh
```

Each run: enters Laravel maintenance mode, stops the queue worker, fetches
and hard-resets to `origin/main`, runs the APPLICATION-SPECIFIC build
steps above, restarts the queue worker, then exits maintenance mode.

## Rollback

There is no release history on disk — git itself is the rollback
mechanism. On the server, as the app's Unix user:

```bash
cd /srv/apps/shiftplanner
git log --oneline -10          # find the commit to roll back to
git reset --hard <sha>
```

Then re-run the relevant APPLICATION-SPECIFIC steps for that commit
(`composer install`, `npm ci && npm run build`, the cache commands) and
restart the queue worker if you stopped it manually. A rolled-back deploy
does not undo migrations — roll the schema back by hand first if the newer
commit added migrations.
