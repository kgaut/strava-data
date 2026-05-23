#!/usr/bin/env bash
set -euo pipefail

# Install crontab and run cron in foreground.
install -m 0644 /etc/cron.d/strava-sync /etc/cron.d/strava-sync
crontab -u www-data /etc/cron.d/strava-sync
exec cron -f -L 15
