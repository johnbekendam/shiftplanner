# Container Deployment

## Build A Release

Run this command in the repository root.

```powershell
.\scripts\build-image.ps1 1.0.0
```

On macOS or Linux, run the shell script instead.

```bash
./scripts/build-image.sh 1.0.0
```

The `.ps1` script writes `dist/shiftplanner-1.0.0.tar`. The `.sh` script writes the full server bundle to `dist/`:

- `shiftplanner-1.0.0.tar` — the release image
- `docker-compose.yml` — the production stack
- `.env.example` — a fresh template, `IMAGE_TAG` pre-filled
- `.env` — a working copy, created on the first run and never overwritten after

Each run replaces an artifact with the same version. The `.sh` script builds for `linux/amd64` by default so the image runs on the server from an Apple Silicon Mac. Override with the `PLATFORM` environment variable.

## Prepare The Server

Copy the whole `dist/` directory to one server directory.

Open `.env` and set at least these values.

```dotenv
APP_KEY=base64:replace-with-a-generated-key
APP_URL=http://server-name:8080
DB_PASSWORD=replace-with-a-strong-password
```

To generate an application key, run this command on a machine with the release image.

```powershell
docker run --rm shiftplanner:1.0.0 php artisan key:generate --show
```

## Deploy A Release

Run these commands in the server directory.

```powershell
docker image load --input shiftplanner-1.0.0.tar
docker compose up -d postgres
docker compose run --rm app php artisan migrate --force
docker compose up -d
```

The `postgres-data` volume stores database data. The `app-storage` volume stores application files. Do not run `docker compose down --volumes` unless you want to delete both volumes.