# Containerization

## Problem

The application has no repeatable release build. The Compose file only starts PostgreSQL.

## Solution

Add a PowerShell release script. It builds a versioned Apache/PHP image with the application and compiled Vite assets. The script saves the image as a tar file for manual server transfer.

The server loads the tar file and starts the image with Compose. The stack publishes HTTP on port 8080. An upstream proxy handles TLS. Operators run migrations as an explicit release command.

## Key decisions

- The release script requires a version argument. It tags the image and tar file with that version.
- The image uses multi-stage builds. Composer installs production PHP packages. Node builds production assets.
- Apache serves the application from the production image.
- The `queue` service runs Laravel's queue worker and starts with the stack.
- PostgreSQL uses a named Docker volume.
- The server Compose file reads deployment values and the image version from `.env`.

## Non-goals

- This feature does not replace the host-based local development workflow.
- This feature does not provide TLS certificates or proxy configuration.
- This feature does not run database migrations automatically.
- This feature does not add the phase-5 scheduling service.
