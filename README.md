# 🐱 CatControl - Docker Setup

Diese Anleitung konzentriert sich ausschließlich auf eine funktionierende Docker-Installation (lokales Heimnetz). Sicherheitsaspekte sind nur basal berücksichtigt.

## Voraussetzungen
- Docker und Docker Compose
- Optional: Portainer (als Alternative zur CLI)

## Schnellstart mit Docker

1. Projekt klonen oder Dateien lokal bereitstellen
2. Container bauen und starten:
```bash
docker compose up -d --build
```
3. Browser öffnen und Installation ausführen:
- `http://localhost:8080/install.php`
- Voreinstellungen für die Datenbank sind gesetzt:
  - Host: `db`
  - Datenbank: `catcontrol`
  - Benutzer: `phpuser`
  - Passwort: `changeme123`
4. Nach erfolgreicher Installation optional `install.php` entfernen.

### Nützliche Befehle
- Logs anzeigen:
```bash
docker compose logs -f web
```
- Datenbank-Shell öffnen:
```bash
docker compose exec db mysql -uphpuser -pchangeme123 catcontrol
```
- Alles stoppen und Daten behalten:
```bash
docker compose down
```
- Alles stoppen und Daten (DB) entfernen:
```bash
docker compose down -v
```

## Alternative: Installation mit Portainer
1. Portainer öffnen
2. "Stacks" -> "Add stack"
3. Inhalt der `docker-compose.yml` in das Editorfeld kopieren
4. Stack deployen
5. Im Browser `http://<host>:8080/install.php` aufrufen und Installation abschließen

## Hinweise zur install.php
- Die Eingabefelder sind bereits mit sinnvollen Werten vorbelegt (siehe oben). 
- `ALLOW_INSTALL=1` ist aktiviert, um die Erstinstallation zu erlauben. Nach Abschluss kann die Datei `install.php` gelöscht werden.
- Die `config/database.php` wird von `install.php` angelegt/aktualisiert. Diese ist im Host unter `./config` persistiert.

## Passwort-Reset
- Admin-Notfall-Reset: `resetAdmin.php` aufrufen und bestätigen. Das Admin-Passwort wird auf `katze` gesetzt (beim nächsten Login Änderung erforderlich). Danach die Datei aus Sicherheitsgründen löschen.
- Benutzer-Passwort-Reset: `reset-passwort.php` führt durch einen Verifizierungs- und Setz-Workflow.

## Kompatibilität (Apache, PHP, MariaDB)
- Web: `php:8.2-apache` mit Extensions: `pdo`, `pdo_mysql`, `gd`, `zip`
- Datenbank: `mariadb:11.4`
- Verbindung: über Host `db` innerhalb des Docker-Netzwerks oder `localhost` von der DB-Shell

## Verzeichnisse und Persistenz
- Uploads: `./uploads` wird nach `/var/www/html/uploads` gemountet
- Konfiguration: `./config` wird nach `/var/www/html/config` gemountet
- Datenbankdaten: Volume `db_data`

## Ports
- HTTP: `8080` (Host) -> `80` (Container)

Viel Erfolg mit CatControl! 🐾
