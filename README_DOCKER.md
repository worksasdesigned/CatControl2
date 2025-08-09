# CatControl Docker Quickstart

## Development (bind-mounted source)

1) Build and start:

```bash
docker compose up -d --build
```

2) Open in browser:

- http://localhost:8080/install.php

3) Installation values are prefilled via env:

- DB host: db
- DB name: catcontrol
- DB user: phpuser
- DB password: changeme123
- Admin E-Mail: admin@localhost

4) After install, you can remove `install.php` if desired.

Stop:

```bash
docker compose down
```

Remove volumes (DB data):

```bash
docker compose down -v
```

## Production-like (image clones latest from GitHub)

Build and run with repo clone baked into image:

```bash
REPO_URL=https://github.com/worksasdesigned/CatControl2.git \
GIT_REF=main \
DB_PASSWORD="yourStrongPassword" \
ADMIN_EMAIL="you@example.com" \
docker compose -f docker-compose.prod.yml up -d --build
```

Notes:

- App: http://localhost:8080
- First run: visit `/install.php`. The form uses env values. Config and uploads are persisted in named volumes.
- Database runs in `db` container; use `DB host = db`.

## Useful commands

- Logs:

```bash
docker compose logs -f web
```

- DB shell:

```bash
docker compose exec db mysql -uphpuser -p${DB_PASSWORD:-changeme123} catcontrol
```

- Reset everything (dev):

```bash
docker compose down -v && docker compose up -d --build
```