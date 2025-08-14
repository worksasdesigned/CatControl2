# 🐱 CatControl – Installation mit Docker (empfohlen)

CatControl ist eine Webanwendung zur Verwaltung von Kätzchen. Diese Anleitung beschreibt ausschließlich die Installation mit Docker – ideal für den lokalen Einsatz im Heimnetz (Portainer optional). Sicherheitsaspekte sind hier bewusst einfach gehalten.

## Voraussetzungen
- Docker und Docker Compose (oder Portainer mit Compose-Stack)
- 1–2 GB freier Speicherplatz für Container und Uploads

## Schnellstart (Docker Compose)
1. Repository-Dateien auf Ihren Host kopieren (dieses Verzeichnis ist der Compose-Kontext).
2. Optional: Datenbank-Passwort setzen (wenn nicht, wird `changeme123` verwendet):
   ```bash
   export DB_PASSWORD="meinPasswort"
   ```
3. Container starten:
   ```bash
   docker compose up -d --build
   ```
4. Browser öffnen und Installation ausführen:
   - URL: `http://localhost:8080/install.php`
   - Voreinstellungen:
     - Host: `db`
     - DB-Name: `catcontrol`
     - Benutzer: `phpuser`
     - Passwort: `changeme123` (oder Ihr Wert aus `DB_PASSWORD`)
5. Nach erfolgreicher Installation die Datei `install.php` entfernen (oder später löschen), um versehentliche Neuinstallationen zu vermeiden.

Hinweise:
- Die Anwendung ist nach der Installation unter `http://localhost:8080` erreichbar.
- DB-Zugriff vom Host (z. B. Adminer/HeidiSQL): Host `localhost`, Port `3306`, Benutzer `phpuser`, Passwort `${DB_PASSWORD}`.
- Persistente Daten:
  - Datenbank: Volume `db_data`
  - Uploads: Volume `uploads_data`
  - Konfiguration (`config/database.php`): Volume `config_data`

## Alternative: Installation mit Portainer
1. In Portainer anmelden und einen neuen Stack erstellen.
2. Den Inhalt der Datei `docker-compose.yml` aus diesem Projekt in das Portainer-Editorfenster kopieren.
3. Optional Umgebungsvariablen setzen (z. B. `DB_PASSWORD`).
4. Stack deployen.
5. Nach dem Start: `http://<Portainer-Host>:8080/install.php` im Browser aufrufen und die Installation ausführen.

## Details zur Installation (install.php)
- `install.php` erstellt (falls nötig) die Datenbanktabellen und speichert die DB-Verbindung in `config/database.php`.
- Voreinstellungen werden aus den Umgebungsvariablen übernommen:
  - `DB_HOST` (Standard: `db`)
  - `DB_NAME` (Standard: `catcontrol`)
  - `DB_USER` (Standard: `phpuser`)
  - `DB_PASSWORD` (Standard: `changeme123` oder Ihr gesetzter Wert)
- Das Skript legt erforderliche Upload-Verzeichnisse an.
- Nach erfolgreicher Installation bitte `install.php` löschen.

## Passwort-Reset (Nutzer und Admin)
- Nutzerpasswort zurücksetzen: `reset-passwort.php` im Browser aufrufen und den Schritten folgen (Nutzername + Kätzchenname zur Verifizierung, dann neues Passwort setzen).
- Admin-Reset: `resetAdmin.php` kann das Admin-Passwort auf den Standardwert `katze` zurücksetzen. Aus Sicherheitsgründen danach die Datei wieder löschen.

## Kompatibilität (getestete Kombination)
- Apache/PHP-Container: `php:8.2-apache` mit aktivem `mod_rewrite`
- PHP-Extensions: `pdo`, `pdo_mysql`, `gd`, `zip` (für Uploads/Grafiken), weitere Standardmodule
- Datenbank: `mariadb:11.4` (MySQL/MariaDB kompatibel)

## Verwaltung und typische Befehle
- Logs ansehen:
  ```bash
  docker compose logs -f web
  ```
- DB-Shell öffnen:
  ```bash
  docker compose exec db mysql -uphpuser -p${DB_PASSWORD:-changeme123} catcontrol
  ```
- Stack stoppen:
  ```bash
  docker compose down
  ```
- Stack inkl. Daten (Volumes) zurücksetzen:
  ```bash
  docker compose down -v && docker compose up -d --build
  ```

## Ordner/Volumes
- `uploads/` – Benutzeruploads (persistiert im Volume `uploads_data`)
- `config/` – Datenbankkonfiguration (`config/database.php`) (persistiert im Volume `config_data`)

## FAQ
- Verbindung zu `db` funktioniert nicht? Warten Sie wenige Sekunden, bis `db` „healthy“ ist. Die App wartet vor dem Start auf Port 3306.
- pdo_mysql fehlt? Das Image installiert `pdo_mysql` fest. Bei Abweichungen: Image neu bauen (`--build`).
