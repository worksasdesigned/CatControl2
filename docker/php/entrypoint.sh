#!/usr/bin/env bash
set -euo pipefail

# Configure Apache DocumentRoot to project root if env is set
if [[ -n "${APACHE_DOCUMENT_ROOT:-}" ]]; then
	sed -ri -e "s#DocumentRoot /var/www/html#DocumentRoot ${APACHE_DOCUMENT_ROOT}#g" /etc/apache2/sites-available/000-default.conf
	sed -ri -e "/<Directory ${APACHE_DOCUMENT_ROOT//\//\/}>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/" /etc/apache2/apache2.conf || true
fi

# Wait for the database to be ready
if [[ -n "${DB_HOST:-}" ]]; then
	echo "Waiting for database ${DB_HOST}:3306 ..."
	for i in {1..60}; do
		if nc -z "${DB_HOST}" 3306 >/dev/null 2>&1; then
			echo "Database is up."
			break
		fi
		sleep 1
	done
fi

# Prepare application writable directories
mkdir -p uploads uploads/kittens uploads/profiles uploads/backgrounds config
chown -R www-data:www-data uploads config
chmod -R 775 uploads config || true

# Ensure PDO MySQL extension is available (it should be in image)
php -m | grep -qi pdo_mysql || {
	echo "ERROR: pdo_mysql extension is not loaded. Please rebuild the image." >&2
	exit 1
}

exec "$@"