# CLAUDE.md

A small customer request app: a **Laravel 13 API** with a **Next.js frontend**.
See `README.md` for the challenge tasks.

## Layout

- `apps/api`: Laravel API (PHP 8.3, Eloquent, MariaDB). Routes are in `routes/`, logic is in `app/Services`.
- `apps/web`: Next.js + React + TypeScript + TanStack Query + Tailwind. Pages are in `src/app`, the API client is in `src/lib/api.ts`.
- `.github/workflows/ci.yml`: the CI pipeline.
- `docker-compose.yml`: runs db, api and web together.

## Run and check

```bash
docker compose up -d                        # web :3000, api :3001, db :3306
docker compose exec api php artisan test    # API tests
docker compose exec web npm run typecheck   # frontend types
docker compose logs -f api                  # shows SQL queries
```

## Rules

- Keep changes small and focused. Preserve existing behaviour unless a task asks for a change.
- Add or update a test for every behaviour you change.
- Run the tests and the typecheck before you say something works. Report real results.
- Don't add paid AI services or API keys. The keyword classifier must keep working locally.
- Record decisions in `DECISIONS.md` and AI checkpoints in `AI_USAGE.md`.

## Agents

- **planner** (Opus) investigates and writes a plan. It doesn't edit code.
- **builder** (Sonnet) implements an agreed plan and verifies it.
