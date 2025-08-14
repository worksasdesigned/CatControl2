#!/usr/bin/env bash
set -euo pipefail

# Warten bis DB-Port erreichbar ist (falls DB_HOST gesetzt)
if [[ -n "${DB_HOST:-}" ]]; then
	for i in {1..60}; do
		if nc -z "$DB_HOST" 3306 >/dev/null 2>&1; then
			break
		fi
		sleep 1
	done
fi

# Schreibverzeichnisse vorbereiten
mkdir -p /var/www/html/uploads/kittens /var/www/html/uploads/profiles /var/www/html/uploads/backgrounds /var/www/html/config
chown -R www-data:www-data /var/www/html/uploads /var/www/html/config || true
chmod -R 775 /var/www/html/uploads /var/www/html/config || true

exec "$@"