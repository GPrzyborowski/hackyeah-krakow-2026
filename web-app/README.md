# hackyeah-krakow-2026

Laravel 13 + Inertia 3 + Vue (with **SSR**), running on [Laravel Sail](https://laravel.com/docs/sail) (Docker).
You **don't** need PHP, Composer, MySQL or Node installed locally — only Docker.

## Stack

| Service  | Address                 |
|----------|-------------------------|
| App      | http://localhost        |
| Vite     | http://localhost:5173   |
| Mailpit  | http://localhost:8025   |
| MySQL    | `localhost:3306` (user `sail`, password `password`, db `laravel`) |
| Redis    | `localhost:6379`        |

## Requirements

Step-by-step guides for your system:

- **Windows:** [docs/setup-windows.md](docs/setup-windows.md) — Docker Desktop + WSL 2 (Ubuntu). Sail doesn't run in PowerShell/Git Bash, and the project must live in WSL (`~/code`), not on `C:\`.
- **Linux / macOS:** [docs/setup-linux-macos.md](docs/setup-linux-macos.md) — Docker Desktop (macOS) or Docker Engine + Compose plugin (Linux).

**Mobile developers:** after the setup below, see [docs/mobile-local-dev.md](docs/mobile-local-dev.md) — connecting
an emulator / phone to the local API, test accounts, resetting data. API reference: [docs/api/README.md](docs/api/README.md).

## First-time setup

```bash
git clone https://github.com/GPrzyborowski/hackyeah-krakow-2026.git
cd hackyeah-krakow-2026/web-app
./setup.sh
```

This creates `.env` + app key, installs Composer dependencies in a container, builds and starts the containers, runs migrations, installs npm packages and builds the frontend (client + SSR bundle).

## Daily use

Tip: add an alias to your `~/.bashrc` / `~/.zshrc`:

```bash
alias sail='sh $([ -f sail ] && echo sail || echo vendor/bin/sail)'
```

```bash
sail up -d            # start containers
sail npm run dev      # Vite with hot reload — SSR works automatically through Vite
sail stop             # stop containers

sail artisan migrate  # any artisan command
sail composer require vendor/package
sail npm install some-package
sail test             # run tests
sail shell            # shell inside the app container
sail mysql            # MySQL CLI
```

After pulling changes that touch `composer.json`, `package.json` or migrations:

```bash
sail composer install && sail npm install && sail artisan migrate
```

If the Docker image changed (e.g. `compose.yaml` or PHP version): `sail build --no-cache && sail up -d`.

## SSR (server-side rendering)

- **Development:** just run `sail npm run dev`. Laravel sends pages to the Vite dev server (`/__inertia_ssr`), so pages are server-rendered with hot reload. No extra process needed.
- **Production-like check** (without Vite):

  ```bash
  sail npm run build:ssr          # builds public/build + bootstrap/ssr
  sail artisan inertia:start-ssr  # starts the Node SSR server (port 13714 in the container)
  sail artisan inertia:check-ssr  # health check
  ```

  If the SSR server isn't running, pages still work — they fall back to client-side rendering.
- Pages that must not be server-rendered can be excluded — see `config/inertia.php`.

## AI: Claude Code + Laravel Boost

The project ships with [Laravel Boost](https://laravel.com/docs/boost) configured for [Claude Code](https://claude.com/claude-code):

- `CLAUDE.md` — project guidelines (versions, Sail, Inertia/Vue, Tailwind, testing).
- `.claude/skills/` — Boost skills (Inertia Vue, Fortify, Wayfinder, Tailwind, testing, …).
- `.mcp.json` — Boost MCP server (`vendor/bin/sail artisan boost:mcp`): DB schema/queries, logs, last error, version-aware docs search.

Just run `claude` in the project folder (containers must be running) and approve the `laravel-boost` MCP server on first start.
After updating packages, refresh the guidelines with `sail artisan boost:update`.

## Troubleshooting

- **Port already in use** (80, 3306, …): override in your `.env`, e.g. `APP_PORT=8080`, `FORWARD_DB_PORT=33060`.
- **Reset the database:** `sail artisan migrate:fresh --seed`
- **Start completely fresh:** `sail down -v && ./setup.sh` (deletes DB data).
