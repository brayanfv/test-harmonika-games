# Backend Agent Instructions

- Use the existing Docker Compose services for PHP, Composer, Artisan and tests.
- Run backend commands with `docker compose exec backend ...` from the repository root.
- Do not install PHP, Composer, Laravel Installer or Laravel Boost on the host.
- Tests must remain isolated in SQLite `:memory:` and must never use the development PostgreSQL database.
- Do not run destructive migrations or remove Docker volumes unless the user explicitly requests it.
