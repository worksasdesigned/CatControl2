#!/usr/bin/env bash
set -e

mkdir -p \
  /var/www/html/uploads \
  /var/www/html/uploads/kittens \
  /var/www/html/uploads/profiles \
  /var/www/html/uploads/backgrounds \
  /var/www/html/config

chown -R www-data:www-data /var/www/html/uploads /var/www/html/config

exec "$@"