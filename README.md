# strava-data

Personal Strava dashboard and map renderer, designed to run as a Docker Compose
stack on a home server.

- Single-user OAuth login with Strava
- CLI commands sync activities into a local PostgreSQL/PostGIS database
- Web dashboard with totals, year/month/sport breakdowns, and global filters
  (year, month, sport type, date range)
- Map renderer that overlays all matching activity polylines on a PNG, with
  two background modes: **OSM tiles** or **plain colour (no tiles)**
- Filters for the map: center + radius, date range, sport types, colour,
  opacity, trace width, image size

## Stack

| Service | Image | Notes |
|---|---|---|
| `app`   | `php:8.3-fpm-bookworm` (custom) + Imagick + pdo_pgsql + redis | Symfony 7 |
| `nginx` | `nginx:1.27-alpine` | Reverse proxy on port 80 |
| `db`    | `postgis/postgis:16-3.4` | PostgreSQL 16 with PostGIS |
| `redis` | `redis:7-alpine` | Cache + Symfony Messenger transport |
| `cron`  | same image as `app` | Hourly `app:strava:sync` |

The Dockerfile is multi-stage (`prod` and `dev` targets). The `compose.override.yaml`
mounts the source tree and switches to the `dev` target for local hacking.

## Quick start

1. **Create a Strava API application** at <https://www.strava.com/settings/api>.
   - Authorization Callback Domain: the public hostname of your deployment
     (e.g. `strava.mydomain.tld`), or `localhost` for local dev.
2. Copy and edit the local env file:
   ```bash
   cp .env .env.local
   # Then set STRAVA_CLIENT_ID, STRAVA_CLIENT_SECRET, APP_SECRET, etc.
   ```
3. Boot the stack:
   ```bash
   docker compose up -d --build
   ```
4. Visit `http://localhost:8080` and click **Connect with Strava**.
5. Trigger the initial backfill:
   ```bash
   docker compose exec app php bin/console app:strava:full-sync -v
   ```
   The hourly cron sidecar keeps things up to date afterwards (or run
   `app:strava:sync` manually).

## CLI

| Command | Purpose |
|---|---|
| `app:strava:full-sync` | Pull the entire activity history. |
| `app:strava:sync` | Incremental sync since the latest known activity. |
| `app:strava:map` | Render a PNG of matching traces. |

Example map renders:

```bash
# With OSM tile background
docker compose exec app php bin/console app:strava:map \
    --center=48.8566,2.3522 --radius=20 \
    --background=tiles --color="#fc4c02" --opacity=0.7 \
    --size=2048x2048 --out=/tmp/paris-tiles.png

# Without tiles (plain black background)
docker compose exec app php bin/console app:strava:map \
    --center=48.8566,2.3522 --radius=20 \
    --background=blank --bg-color="#000000" --color="#ffffff" \
    --from=2024-01-01 --to=2024-12-31 \
    --out=/tmp/paris-blank.png
```

## OSM tile usage policy

When `--background=tiles` is used the renderer downloads tiles from the URL
configured in `OSM_TILE_URL_TEMPLATE` (default: `tile.openstreetmap.org`). Tiles
are cached aggressively under `var/tiles/` and the fetcher throttles itself to
two requests per second. **Configure `OSM_TILE_USER_AGENT` with a string that
identifies your deployment** before pointing it at the public OSM tile server.
See <https://operations.osmfoundation.org/policies/tiles/>.

## Development

```bash
# Run with source mounted and xdebug enabled
docker compose up -d --build

# Install / update dependencies
docker compose exec app composer install

# Run the test suite (requires the db service)
docker compose exec app vendor/bin/phpunit

# Static analysis
docker compose exec app vendor/bin/phpstan analyse

# Code style
docker compose exec app vendor/bin/php-cs-fixer fix
```

## CI / CD

- `.github/workflows/ci.yaml` runs PHP-CS-Fixer (dry-run), PHPStan level 8,
  YAML/Twig/container lints, and PHPUnit against a PostGIS + Redis service set.
- `.github/workflows/release.yaml` builds a multi-arch (linux/amd64 +
  linux/arm64) image and pushes it to **`ghcr.io/kgaut/strava-data`** on every
  push to `develop` (tag `develop`), on tag pushes (`vX.Y.Z` + `latest`), and
  per-commit (`sha-<short>`).

## License

MIT — see `LICENSE`.
