# strava-data

Personal Strava dashboard and map renderer, designed to run as a Docker Compose
stack on a home server.

- **No web auth** — the app is meant to live behind your reverse proxy / VPN.
  Strava API access uses a long-lived refresh token configured once in `.env`.
- CLI commands sync activities into a local PostgreSQL/PostGIS database
- Web dashboard with totals, year/month/sport breakdowns, and global filters
  (year, month, sport type, date range)
- Interactive Leaflet map (`/map/live`) with all activity traces overlaid on OSM
- **Wandrer-style coverage map (`/coverage`)**: imports OSM road / path data
  for a chosen department, matches every Strava trace against the network, and
  shows % covered per commune + per department, plus the "new ground" you broke
  on each ride
- Records / heatmap / distributions pages, with day-of-week + hour filters
- PNG renderer (`/map` and CLI) that overlays all matching activity polylines,
  with two background modes: **OSM tiles** or **plain colour (no tiles)**
- Filters for the PNG renderer: center + radius, date range, sport types,
  colour, opacity, trace width, image size

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

### 1. Create a Strava API application

At <https://www.strava.com/settings/api>:
- Authorization Callback Domain: `localhost` (the value is required even
  though we won't go through web OAuth on the server)
- Note your **Client ID** and **Client Secret**

### 2. Mint a long-lived refresh token (one-shot)

Strava requires the athlete to consent once. Do it manually:

```bash
# 1. Open this URL in your browser (replace CLIENT_ID):
#    https://www.strava.com/oauth/authorize?client_id=CLIENT_ID&redirect_uri=http://localhost&response_type=code&scope=read,activity:read_all,profile:read_all
#
# 2. After clicking "Authorize" Strava redirects to http://localhost/?state=&code=CODE&scope=...
#    Copy the `code` value from the URL.
#
# 3. Exchange the code for a refresh_token (replace CLIENT_ID, CLIENT_SECRET, CODE):
curl -X POST https://www.strava.com/oauth/token \
    -d client_id=CLIENT_ID \
    -d client_secret=CLIENT_SECRET \
    -d code=CODE \
    -d grant_type=authorization_code
```

The response contains `"refresh_token": "..."`. Save that string —
it doesn't expire unless you revoke the authorization in Strava settings.

### 3. Configure the app

```bash
cp .env .env.local
# Edit .env.local and set:
#   APP_SECRET=<random 32+ chars>
#   STRAVA_CLIENT_ID=<from step 1>
#   STRAVA_CLIENT_SECRET=<from step 1>
#   STRAVA_REFRESH_TOKEN=<from step 2>
```

### 4. Boot the stack and sync

```bash
docker compose up -d --build
docker compose exec app php bin/console app:strava:full-sync -v
```

That's it — visit `http://localhost:8080`. No login screen. The hourly
cron sidecar keeps activities up to date afterwards.

## CLI

| Command | Purpose |
|---|---|
| `app:strava:full-sync` | Pull the entire activity history. |
| `app:strava:sync` | Incremental sync since the latest known activity. |
| `app:strava:map` | Render a PNG of matching traces. |
| `app:roads:import-admin --osm-relation=<id>` | Import a department + its communes from OSM. |
| `app:roads:import-roads --osm-relation=<id>` | Import every highway way in the department. |
| `app:roads:match [--all\|--activity=<id>]` | Match activities against the road network. |

## Coverage feature (Wandrer-style)

To enable the `/coverage` page you need to import a slice of OSM data once.

1. Find your department's **OSM relation id** on
   <https://www.openstreetmap.org> — search the department name, then click
   the "relation" entry. The URL ends in `/relation/<id>`.
2. Run the imports (takes ~10-30 minutes for a typical French department):
   ```bash
   docker compose exec app php bin/console app:roads:import-admin --osm-relation=<id>
   docker compose exec app php bin/console app:roads:import-roads --osm-relation=<id>
   docker compose exec app php bin/console app:roads:match --all
   ```
   `import-admin` pulls polygons from Nominatim (throttled to 1 req/s),
   `import-roads` pulls every highway way from Overpass, `match` walks all
   your activities and records first-visits in `road_visit`. Subsequent
   `app:strava:sync` runs match new activities automatically.
3. Visit `http://localhost:8080/coverage` — sidebar lists communes /
   departments by % covered, map highlights visited roads in orange.

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
