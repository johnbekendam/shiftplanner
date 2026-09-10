#!/usr/bin/env bash
#
# Build a release image and stage the full server bundle in dist/.
#
# Usage: ./scripts/build-image.sh 1.0.0
#
# After a run, dist/ holds everything the server needs:
#   shiftplanner-<version>.tar  the release image
#   docker-compose.yml          the production stack
#   .env.example                a fresh env template, IMAGE_TAG pre-filled
#   .env                        a working copy (created once, never overwritten)
#
# The image is built for linux/amd64 so it runs on the deployment server
# even when this machine is Apple Silicon. Override with PLATFORM, e.g.
# PLATFORM=linux/arm64 ./scripts/build-image.sh 1.0.0

set -euo pipefail

version="${1:-}"
platform="${PLATFORM:-linux/amd64}"

if [ -z "$version" ]; then
    echo "Usage: $0 <version>" >&2
    exit 1
fi

if ! printf '%s' "$version" | grep -Eq '^[0-9A-Za-z][0-9A-Za-z._-]*$'; then
    echo "Invalid version '$version'. Use letters, digits, dot, underscore, or hyphen." >&2
    exit 1
fi

if ! command -v docker >/dev/null 2>&1; then
    echo "Docker is not available in PATH." >&2
    exit 1
fi

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repo_root="$(cd "$script_dir/.." && pwd)"
artifact_directory="$repo_root/dist"
image_name="shiftplanner:$version"
artifact_path="$artifact_directory/shiftplanner-$version.tar"

mkdir -p "$artifact_directory"

docker build --platform "$platform" --tag "$image_name" "$repo_root"
docker image save --output "$artifact_path" "$image_name"

# Stage the rest of the server bundle next to the image.
cp "$repo_root/docker-compose.yml" "$artifact_directory/docker-compose.yml"

staged_template="$artifact_directory/.env.example"
sed "s|^IMAGE_TAG=.*|IMAGE_TAG=$version|" "$repo_root/.env.production.example" > "$staged_template"

if [ -f "$artifact_directory/.env" ]; then
    echo "Kept existing $artifact_directory/.env (template refreshed as .env.example)"
else
    cp "$staged_template" "$artifact_directory/.env"
    echo "Wrote $artifact_directory/.env - set APP_KEY, APP_URL, and DB_PASSWORD before deploying"
fi

echo
echo "Server bundle in $artifact_directory:"
echo "  $(basename "$artifact_path")"
echo "  docker-compose.yml"
echo "  .env.example"
echo "  .env"
