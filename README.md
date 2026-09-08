# ShiftPlanner

Browser-based shift scheduling tool for shift-based teams. Managers keep
employee records current, define departmental coverage needs, and generate
fair schedules automatically. Runs on the Prodrive intranet only.

- `doc/concept.md` — what ShiftPlanner is and why.
- `doc/roadmap.md` — build order and phase status.
- `doc/features/<name>/` — per-feature `spec.md` and `plan.md`.
- `AGENTS.md` — development rules carried from the TeamApps template.

## Stack

Laravel 12, Inertia, Vue 3, Tailwind 4. Local dev runs on SQLite;
PostgreSQL is the deployment target, wired in at roadmap phase 2
(`docker-compose.yml` already carries the service). Schedule generation
runs in a Laravel queue job that calls a separate Python OR-Tools worker
(roadmap phase 5). Scaffolded from a snapshot of the internal
`TeamApps/template`; the snapshot has since diverged and is not re-synced.

## Local setup

1. `composer install && npm install`
2. `cp .env.example .env && php artisan key:generate`
3. `touch database/database.sqlite && php artisan migrate --seed`
4. `composer run dev` — serves the app, the queue worker, logs, and Vite.

Manager auth is not built yet (roadmap phase 2). For local dev, `.env`
sets `AUTH_AUTO_LOGIN=true` with a seeded `SEED_USER_*`, so every request
is logged in as that user. The auto-login middleware is gated to
`APP_ENV=local` + `APP_DEBUG=true`, so it never fires in tests or
production. `/` redirects to `/employees`; the sidebar switches between
Employees and Theme Builder.

## Checks

- `php artisan test` — PHPUnit feature and unit suite.
- `npm run test` — Vitest component suite.
- `./vendor/bin/pint` — PHP formatting.
- `npm run build` — production asset build.
