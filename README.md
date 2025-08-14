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

**Option A (empfohlen) – Repository**
1. In Portainer einen neuen Stack erstellen und "Repository" wählen.
2. Repository-URL: `https://github.com/worksasdesigned/CatControl2`
3. Compose-Dateipfad: `docker-compose.yml`
4. Branch/Reference: `Master`
5. Optional: Umgebungsvariablen setzen (z. B. `DB_PASSWORD`).
6. Deployen und danach `http://<Portainer-Host>:8080/install.php` im Browser öffnen.

**Option B – Web-Editor (YAML einfügen)**
- Der Web-Editor hat keinen Build-Kontext. Dadurch entsteht der Fehler "open Dockerfile: no such file or directory", wenn nur das YAML eingefügt wird.
- Verwenden Sie stattdessen die Datei `docker-compose.portainer.yml` aus diesem Repo (sie nutzt einen Git-Build-Kontext). Inhalt in den Editor kopieren und deployen.
- Falls der Build dennoch fehlschlägt, verwenden Sie Option A.

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
