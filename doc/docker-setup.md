# Docker Setup With GitHub

GitHub Actions builds the release image and pushes it to the GitHub Container Registry (GHCR). The Docker server pulls the image from GHCR. This is the only way to build a release image.

This setup is only for servers that use Docker. Local development and the VPS servers run without Docker (see [vps-deployment.md](vps-deployment.md)).

Image address: `ghcr.io/johnbekendam/shiftplanner`

## 1. Compose File

In `docker/docker-compose.yml`, the `app` and `queue` services use this image.

```yaml
image: "ghcr.io/johnbekendam/shiftplanner:${IMAGE_TAG:?Set IMAGE_TAG in .env}"
```

`IMAGE_TAG` in `.env` selects the version. Compose stops with an error if `IMAGE_TAG` has no value.

## 2. GitHub Workflow

`.github/workflows/docker.yml` builds the image from `docker/Dockerfile` for `linux/amd64` and pushes it to GHCR. The workflow starts when you push a tag that starts with `v`. You can also start it by hand from the **Actions** tab.

`GITHUB_TOKEN` is a token that GitHub gives to each workflow run. You do not create a secret.

The workflow file must be on the commit that you tag.

## 3. Release a Version

1. Switch to `main` and pull the latest changes.
2. Create a version tag.

    ```bash
    git tag v1.0.0
    git push origin v1.0.0
    ```

3. Open the **Actions** tab on GitHub. Wait until the **Docker image** run is green.
4. Open the **Packages** section of the repository. Make sure that the `shiftplanner` package shows these tags: `1.0.0`, `latest`, and `sha-<commit>`.

The image tag has no `v`. Tag `v1.0.0` gives image tag `1.0.0`.

The first run takes some minutes. Later runs use the build cache and are faster.

## 4. Give the Server Access to the Image

A private repository gives a private image. The server must log in to GHCR once. If the repository is public, skip this section.

1. On GitHub, go to **Settings → Developer settings → Personal access tokens → Tokens (classic)**.
2. Create a token with only the `read:packages` scope. Fine-grained tokens do not work with GHCR.
3. Copy the token.
4. On the server, run this command. Enter the token as the password.

    ```bash
    docker login ghcr.io -u johnbekendam
    ```

Docker keeps the login in `~/.docker/config.json`. Set an expiry date on the token and replace the token before it expires.

## 5. Prepare the Server

Do these steps one time.

1. Make a directory on the server, for example `/opt/shiftplanner`.
2. Copy `docker/docker-compose.yml` from the repository into that directory.
3. Copy `.env.production.example` into that directory as `.env`.
4. In `.env`, set these values.

    ```dotenv
    IMAGE_TAG=1.0.0
    APP_URL=http://server-name:8080
    DB_PASSWORD=replace-with-a-strong-password
    ```

5. Generate the application key.

    ```bash
    docker run --rm ghcr.io/johnbekendam/shiftplanner:1.0.0 php artisan key:generate --show
    ```

6. Put the output in `.env` as the `APP_KEY` value.

## 6. Deploy a Version

Run these commands in the server directory.

```bash
docker compose pull
docker compose up -d postgres
docker compose run --rm app php artisan migrate --force
docker compose up -d
```

## 7. Update to a New Version

1. Release the new version (section 3).
2. On the server, set `IMAGE_TAG` in `.env` to the new version.
3. Run the commands in section 6 again.

To go back to an earlier version, set `IMAGE_TAG` to that version and run section 6 again. A rollback does not undo database migrations.

Use a fixed version in `IMAGE_TAG`, not `latest`. A fixed version shows which release runs on the server.

## Notes

- The workflow builds for `linux/amd64`. Most servers use this architecture. For an ARM server, change `platforms` to `linux/arm64`.
- Private repositories get a limited number of free Actions minutes and package storage each month. This project uses a small part of the limit.
- To remove old images, open the package on GitHub and delete old versions.
- The `postgres-data` volume stores the database. The `app-storage` volume stores the application files. Do not run `docker compose down --volumes`. This command deletes both volumes.
