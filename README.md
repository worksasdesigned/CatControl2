# CatControl – Docker Setup (Lokal/Heimnetz)

Diese Anleitung setzt CatControl mit Docker (Apache + PHP + MariaDB) lokal im Heimnetz auf. Fokus: Es funktioniert zuverlässig, Security ist hier zweitrangig. CatControl ist für das lokale Netzwerk gedacht und nicht für das Internet.
Am Ende der Doku findest du die Beschreibung wie du curl und docker installiertst.

## Komponenten und Ports
- **Web (Apache + PHP 8.2)**: Port `8242` (Host) → `http://localhost:8242`
- **Datenbank (MariaDB 10.11)**: Port `3308` (Host) → für externe DB-Tools `127.0.0.1:3308`
- **Wichtige PHP-Extensions**: `pdo_mysql`, `gd`, `fileinfo`

## Voraussetzungen
- Docker und Docker Compose installiert
- Optional: Portainer, wenn die Bereitstellung dort erfolgen soll

## Schnellstart (Docker Compose CLI)
1. Repository lokal klonen.
2. Im Projektordner ausführen:
   ```bash
   docker compose up -d --build
   ```
3. Browser öffnen: `http://localhost:8242/install.php`
4. Installationsformular ausfüllen (Vorgabewerte passen für Docker):
   - Datenbank-Host: `db`
   - Datenbankname: `catcontrol`
   - Benutzer: `phpuser`
   - Passwort: `changeme123`
5. Installation abschließen. Standard-Admin: `admin` / `katze` (beim ersten Login ändern!)
6. Wichtiger Hinweis: Nach erfolgreicher Installation `install.php` entfernen (oder `ALLOW_INSTALL` abschalten), siehe unten.

### Container-Verwaltung
- Logs anzeigen: `docker compose logs -f app`
- Dienste stoppen: `docker compose down`
- Update (Images neu bauen/ziehen):
  ```bash
  docker compose pull
  docker compose build --no-cache
  docker compose up -d
  ```

## Portainer-Variante
Es gibt zwei Wege:

### A) Web Editor (empfohlen, am einfachsten)
1. Portainer öffnen → Stacks → Add Stack.
2. Einen Namen vergeben, z. B. `catcontrol`.
3. Im **Web editor** den Inhalt der `docker-compose.yml` aus diesem Repository einfügen.
4. Deploy the stack.
5. Nach dem Start im Browser `http://<portainer-host>:8242/install.php` aufrufen.

### B) Über Repository (Git)
1. Portainer → Stacks → Add Stack → **Repository** Tab.
2. Git-Repository-URL angeben (dieses Repo). Branch auswählen.
3. Compose path: `docker-compose.yml` (Pfad im Repo).
4. Optional Auto-Update konfigurieren.
5. Deploy the stack.
6. Nach dem Start im Browser `http://<portainer-host>:8242/install.php` aufrufen.

Hinweise zu Portainer:
- Bei Repository-Stacks baut Portainer das Image selbst. Es werden die in der Compose-Datei definierten Ports/Volumes verwendet.
- Wenn Ports 8242 oder 3308 auf dem Host belegt sind, die Werte in der `docker-compose.yml` anpassen.

## Datenbankzugriff (optional)
- Aus dem Host: `mysql -h 127.0.0.1 -P 3308 -u phpuser -p`
- Innerhalb des App-Containers: Hostname `db`, Port `3306` (intern)

## Verzeichnisse und Persistenz
Folgende Daten werden dauerhaft gespeichert (Docker Volumes):
- `config/` (enthält `config/database.php` nach Installation)
- `uploads/` (Benutzer-Uploads, Profilbilder, etc.)
- MariaDB-Daten in einem Volume (`db_data`)

## install.php – wichtige Hinweise
- Rufen Sie `http://localhost:8242/install.php` auf, um die Ersteinrichtung zu starten.
- In Docker ist der DB-Host bereits korrekt auf `db` gesetzt. Benutzer/Passwort: `phpuser` / `changeme123`.
- Hinweis zu `localhost` vs. `db`: Innerhalb des Containers funktioniert `localhost` NICHT als DB-Host (das wäre der App-Container selbst). Verwenden Sie im Container immer `db`. `localhost` ist nur sinnvoll, wenn Sie ganz ohne Docker direkt auf eine lokale DB zugreifen.
- Die Installation erstellt die Datenbanktabellen, die Datei `config/database.php` und die Upload-Verzeichnisse.
- Nach erfolgreicher Installation bitte `install.php` aus Sicherheitsgründen löschen. Alternativ die Umgebungsvariable `ALLOW_INSTALL` in der Compose-Datei entfernen oder auf `0` setzen und den Container neu starten.

## Passwort-Reset
- Benutzer-Reset: `reset-passwort.php` im Browser aufrufen. Der Ablauf prüft Benutzername + Kätzchennamen und setzt ein neues Passwort.
- Admin-Notfall-Reset: `resetAdmin.php` aufrufen und Button klicken. Das Admin-Passwort wird auf `katze` zurückgesetzt und muss beim nächsten Login geändert werden. Danach die Datei `resetAdmin.php` wieder löschen.

## Kompatibilität (Apache, PHP, MariaDB)
- Apache läuft im Container mit PHP 8.2 (mod_php), aktivierter `rewrite`-Engine und `.htaccess`-Unterstützung.
- PHP-Extensions `pdo_mysql` (Datenbankzugriff) und `gd` (Bilder) sind installiert; `fileinfo` ist in PHP standardmäßig verfügbar.
- Datenbank ist MariaDB 10.11 (LTS), kompatibel mit dem verwendeten PDO-MySQL-Treiber.

## Anpassungen
- Ports ändern: In `docker-compose.yml` die Zeilen `8242:80` (Web) bzw. `3308:3306` (DB) anpassen.
- DB-Zugangsdaten: In `docker-compose.yml` unter `db.environment` und `app.environment` ändern. Beachten: App und DB müssen dieselben Werte nutzen.

## Sicherheitshinweis
CatControl ist für das **lokale Heimnetz** gedacht. Für den produktiven Internetbetrieb sind zusätzliche Maßnahmen notwendig (SSL/TLS, Härtung, Benutzer-/Rollen-/Backup-Konzept, regelmäßige Updates). Für den lokalen Einsatz genügt die hier gezeigte Konfiguration.





## Installation unter Debian (für Anfänger) 

Diese Anleitung beschreibt, wie man CatControl2 auf einem frischen Debian-System installiert und für die Docker-Ausführung vorbereitet.

### 1. System aktualisieren & Grundpakete installieren

`sudo apt update`
`sudo apt install -y git curl apt-transport-https ca-certificates gnupg lsb-release`

Docker-Repository hinzufügen & installieren:

`curl -fsSL https://download.docker.com/linux/debian/gpg | sudo gpg --dearmor -o /usr/share/keyrings/docker-archive-keyring.gpg`

`echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/docker-archive-keyring.gpg] \`
`https://download.docker.com/linux/debian $(lsb_release -cs) stable" \`
`| sudo tee /etc/apt/sources.list.d/docker.list > /dev/null`

`sudo apt update`
`sudo apt install -y docker-ce docker-ce-cli containerd.io docker-compose `


#Optional: Docker testen

`sudo docker run hello-world`

3. Projekt herunterladen
`git clone https://github.com/worksasdesigned/CatControl2.git`
`cd CatControl2`

4. Container bauen & starten
`docker compose up -d --build`

.
