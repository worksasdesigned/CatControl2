#!/usr/bin/env bash
set -euo pipefail

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

# Optional: install PHPMailer if not present
if [[ ! -f vendor/autoload.php ]]; then
  if command -v composer >/dev/null 2>&1; then
    echo "Installing PHPMailer via Composer (optional)..."
    composer require phpmailer/phpmailer:^6.9 --no-dev --no-interaction || true
  fi
fi

exec "$@"