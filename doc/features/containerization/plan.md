Status: deferred — 6/7

The container release work is deferred until the end of the project,
after the product phases are complete. The Docker files and deployment
documentation remain in place. The image build is the remaining task.

- [x] Add the production Apache/PHP Docker image.
- [x] Add the versioned image release script.
- [x] Add app and queue services to Docker Compose.
- [x] Exclude local and generated files from image builds.
- [x] Document deployment configuration and release commands.
- [x] Run static checks. `npm run build` passes. The repository-wide Pint
	check still reports existing formatting failures.
- [ ] Build the Docker image after the product phases are complete.
