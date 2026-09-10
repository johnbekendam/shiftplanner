# Container Deployment

## Build A Release

Run this command in the repository root.

```powershell
.\scripts\build-image.ps1 1.0.0
```

The script creates `dist/shiftplanner-1.0.0.tar`. It replaces an artifact with the same version.

## Prepare The Server

Copy these files to one server directory.

- `dist/shiftplanner-1.0.0.tar`
- `docker-compose.yml`
- `.env`

Set these values in the server `.env` file.

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=http://server-name:8080
APP_KEY=base64:replace-with-a-generated-key
IMAGE_TAG=1.0.0
DB_DATABASE=shiftplanner
DB_USERNAME=shiftplanner
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