# 🐱 CatControl - Kätzchen Verwaltungssystem

CatControl ist eine webbasierte Anwendung zur Verwaltung von jungen Kätzchen, entwickelt für den Einsatz Heimnetzwerken. Das System ermöglicht die Erfassung von Fütterungsdaten, Tierarztbesuchen, Gewichtsstatistiken und vielem mehr.

<img width="1720" height="864" alt="image" src="https://github.com/user-attachments/assets/a1471571-bc01-40f1-a54b-7b7426f16216" />

Es ist möglich die Fütterungsdaten sowie Tierarztbesuche zu erfassen. Zudem können Bilder hochgeladen werden und je Kätzchen als Galerie angezeigt werden.
Die Daten können als Zip Datei exportiert werden.
Gewichtszuname ist als Diagramm mit diversen Kennzahlen verfügbar.
Kätzchen können auch anderen Benutzer freigegeben  oder (zur Ansicht) öffentlich zugänglich gemacht werden.

<img width="821" height="1031" alt="image" src="https://github.com/user-attachments/assets/f6eab8d9-3c62-48c9-9772-848d97270066" />
Erfasste Fütterungen können wieder gelöscht oder nachträglich abgeändert werden. Falls man manche Felder nicht pflegen /sehen will, kann man die Formularfelder auch individuell ausblenden.

Die Bildergalerie ermöglicht es Bilder zum Kätzchen zu speichern.
<img width="1077" height="941" alt="image" src="https://github.com/user-attachments/assets/58401cad-c595-44ed-96ee-21afdfe4a762" />

Die Gewichtszuname wird als Diagramm dargestellt:
<img width="1149" height="925" alt="image" src="https://github.com/user-attachments/assets/b4855902-082c-4c55-9cb7-ccd67e68de65" />

Es gibt sogar ein kleines Nachrichtensystem mit dem man zwischen den Benutzern einfache texte austauschen kann.
<img width="1028" height="436" alt="image" src="https://github.com/user-attachments/assets/50ba2467-65d4-4001-bb8c-73ee631a982d" />




## 📋 Systemanforderungen

- **Betriebssystem:** Debian 12 (empfohlen für LXC Container)
- **Webserver:** Apache2
- **Datenbank:** MariaDB 10.5+
- **PHP:** Version 8.2+
- **PHP Extensions:** PDO, PDO_MySQL, GD, Fileinfo, CURL, OpenSSL
- **Speicherplatz:** Mindestens 1 GB für Anwendung und Uploads

## 🚀 Schnellinstallation

### 1. System vorbereiten

```bash
# System aktualisieren
sudo apt update && sudo apt upgrade -y

# Erforderliche Pakete installieren
sudo apt install -y apache2 mariadb-server php8.2 php8.2-mysql php8.2-gd php8.2-curl php8.2-fileinfo php8.2-zip unzip wget curl git

# Apache2 und MariaDB starten
sudo systemctl enable apache2 mariadb
sudo systemctl start apache2 mariadb
```

### 2. MariaDB konfigurieren

```bash
# MariaDB sichern
sudo mysql_secure_installation

# Datenbank und Benutzer erstellen
sudo mysql -u root -p
```

In der MySQL-Konsole:
```sql
CREATE DATABASE catcontrol CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'phpuser'@'localhost' IDENTIFIED BY 'IhrSicheresPasswort';
GRANT ALL PRIVILEGES ON catcontrol.* TO 'phpuser'@'localhost';

-- Für Netzwerkzugriff (optional):
CREATE USER 'phpuser'@'%' IDENTIFIED BY 'IhrSicheresPasswort';
GRANT ALL PRIVILEGES ON catcontrol.* TO 'phpuser'@'%';

FLUSH PRIVILEGES;
EXIT;
```

### 3. Apache2 konfigurieren

```bash
# PHP Module aktivieren
sudo a2enmod rewrite
sudo a2enmod ssl
sudo systemctl restart apache2

# Projektverzeichnis erstellen
sudo mkdir -p /var/www/html/catcontrol
sudo chown www-data:www-data /var/www/html/catcontrol
sudo chmod 755 /var/www/html/catcontrol
```

### 4. CatControl installieren

```bash
# In das Webverzeichnis wechseln
cd /var/www/html/catcontrol

# Projekt herunterladen (ersetzen Sie mit dem tatsächlichen Repository)
sudo wget https://github.com/IhrRepo/catcontrol/releases/latest/download/catcontrol.zip
sudo unzip catcontrol.zip
sudo rm catcontrol.zip

# Oder mit Git:
# sudo git clone https://github.com/IhrRepo/catcontrol.git .

# Berechtigungen setzen
sudo chown -R www-data:www-data /var/www/html/catcontrol
sudo chmod -R 755 /var/www/html/catcontrol
sudo chmod -R 775 /var/www/html/catcontrol/uploads
sudo chmod -R 775 /var/www/html/catcontrol/config
```

### 5. Upload-Verzeichnisse erstellen

```bash
sudo mkdir -p /var/www/html/catcontrol/uploads/{kittens,profiles,backgrounds}
sudo chown -R www-data:www-data /var/www/html/catcontrol/uploads
sudo chmod -R 775 /var/www/html/catcontrol/uploads
```

### 6. Installation abschließen

1. Öffnen Sie Ihren Browser und navigieren Sie zu: `http://IhreIP/catcontrol/install.php`
2. Folgen Sie den Installationsanweisungen
3. Geben Sie Ihre Datenbankdaten ein
4. Konfigurieren Sie die E-Mail-Einstellungen (Gmail)
5. **Wichtig:** Löschen Sie nach erfolgreicher Installation die `install.php` Datei!

```bash
sudo rm /var/www/html/catcontrol/install.php
```

### 7. Admin-Passwort zurücksetzen (optional)

Falls Sie das Passwort des Standard-Admin-Benutzers (`admin`) zurücksetzen möchten, können Sie vorübergehend die Datei `resetAdmin.php` verwenden:

1. Rufen Sie `http://IhreIP/catcontrol/resetAdmin.php` im Browser auf
2. Bestätigen Sie das Zurücksetzen – das Passwort wird auf `katze` gesetzt und beim nächsten Login zur Änderung aufgefordert
3. **Sicherheits-Hinweis:** Löschen Sie die Datei nach Verwendung umgehend!

```bash
sudo rm /var/www/html/catcontrol/resetAdmin.php
```

## ⚙️ Erweiterte Konfiguration

### Apache Virtual Host (empfohlen)

```bash
sudo nano /etc/apache2/sites-available/catcontrol.conf
```

Inhalt:
```apache
<VirtualHost *:80>
    ServerName catcontrol.local
    DocumentRoot /var/www/html/catcontrol
    
    <Directory /var/www/html/catcontrol>
        AllowOverride All
        Require all granted
    </Directory>
    
    # Upload-Limits erhöhen
    php_admin_value upload_max_filesize 10M
    php_admin_value post_max_size 10M
    php_admin_value max_execution_time 300
    
    ErrorLog ${APACHE_LOG_DIR}/catcontrol_error.log
    CustomLog ${APACHE_LOG_DIR}/catcontrol_access.log combined
</VirtualHost>
```

Virtual Host aktivieren:
```bash
sudo a2ensite catcontrol.conf
sudo systemctl reload apache2
```

### MariaDB für Netzwerkzugriff konfigurieren

Wenn Sie von anderen Rechnern im Netzwerk auf die Datenbank zugreifen möchten:

```bash
sudo nano /etc/mysql/mariadb.conf.d/50-server.cnf
```

Ändern Sie:
```ini
bind-address = 0.0.0.0
```

MariaDB neu starten:
```bash
sudo systemctl restart mariadb
```

### PHP Konfiguration optimieren

```bash
sudo nano /etc/php/8.2/apache2/php.ini
```

Empfohlene Einstellungen:
```ini
upload_max_filesize = 10M
post_max_size = 10M
max_execution_time = 300
memory_limit = 256M
date.timezone = Europe/Berlin
```

### Automatische Backups einrichten

Erstellen Sie ein Backup-Script:

```bash
sudo nano /usr/local/bin/catcontrol-backup.sh
```

Inhalt:
```bash
#!/bin/bash
BACKUP_DIR="/var/backups/catcontrol"
DATE=$(date +%Y%m%d_%H%M%S)
DB_USER="phpuser"
DB_PASS="IhrSicheresPasswort"
DB_NAME="catcontrol"

# Verzeichnis erstellen
mkdir -p $BACKUP_DIR

# Datenbank-Backup
mysqldump -u$DB_USER -p$DB_PASS $DB_NAME > $BACKUP_DIR/catcontrol_db_$DATE.sql

# Uploads-Backup
tar -czf $BACKUP_DIR/catcontrol_uploads_$DATE.tar.gz -C /var/www/html/catcontrol uploads/

# Alte Backups löschen (älter als 30 Tage)
find $BACKUP_DIR -type f -mtime +30 -delete

echo "Backup erstellt: $DATE"
```

Script ausführbar machen und Cron-Job einrichten:
```bash
sudo chmod +x /usr/local/bin/catcontrol-backup.sh
sudo crontab -e
```

Cron-Job hinzufügen (täglich um 2:00 Uhr):
```cron
0 2 * * * /usr/local/bin/catcontrol-backup.sh >> /var/log/catcontrol-backup.log 2>&1
```

## 📧 E-Mail Konfiguration (Gmail)

### Gmail App-Passwort erstellen

1. Gehen Sie zu Ihrem Google-Konto
2. Aktivieren Sie die 2-Faktor-Authentifizierung
3. Gehen Sie zu "App-Passwörter"
4. Erstellen Sie ein neues App-Passwort für "CatControl"
5. Verwenden Sie dieses Passwort in der Installation

### Alternative E-Mail Provider

Das System unterstützt jeden SMTP-fähigen E-Mail-Provider. Passen Sie die Einstellungen entsprechend an:

- **GMX:** smtp.gmx.net, Port 587
- **Web.de:** smtp.web.de, Port 587
- **Outlook:** smtp-mail.outlook.com, Port 587

## 🔒 Sicherheit

### Für Heimnetzwerk (Standard)

Das System ist standardmäßig für den sicheren Einsatz in Heimnetzwerken konzipiert:

- ✅ Passwort-Hashing mit bcrypt
- ✅ SQL-Injection Schutz durch Prepared Statements
- ✅ XSS-Schutz durch Eingabevalidierung
- ✅ CSRF-Schutz durch Session-Management
- ✅ Sichere Cookie-Einstellungen

### Für Internet-Zugang (Zusätzliche Maßnahmen erforderlich)

**⚠️ WICHTIG:** Wenn Sie CatControl über das Internet zugänglich machen möchten, sind zusätzliche Sicherheitsmaßnahmen erforderlich:

#### 1. HTTPS/SSL einrichten

```bash
# Let's Encrypt installieren
sudo apt install certbot python3-certbot-apache

# SSL-Zertifikat erstellen
sudo certbot --apache -d ihre-domain.de

# Automatische Erneuerung einrichten
sudo crontab -e
```

Cron-Job hinzufügen:
```cron
0 12 * * * /usr/bin/certbot renew --quiet
```

#### 2. Firewall konfigurieren

```bash
# UFW installieren und konfigurieren
sudo apt install ufw
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow ssh
sudo ufw allow 'Apache Full'
sudo ufw enable
```

#### 3. Fail2Ban einrichten

```bash
# Fail2Ban installieren
sudo apt install fail2ban

# Konfiguration erstellen
sudo nano /etc/fail2ban/jail.local
```

Inhalt:
```ini
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 5

[apache-auth]
enabled = true

[apache-badbots]
enabled = true

[apache-noscript]
enabled = true

[apache-overflows]
enabled = true
```

#### 4. Zusätzliche Apache-Sicherheit

```bash
sudo nano /etc/apache2/conf-available/security.conf
```

Wichtige Einstellungen:
```apache
ServerTokens Prod
ServerSignature Off
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
Header always set Strict-Transport-Security "max-age=63072000; includeSubDomains; preload"
```

#### 5. Regelmäßige Updates

```bash
# Automatische Updates einrichten
sudo apt install unattended-upgrades
sudo dpkg-reconfigure -plow unattended-upgrades
```

## 🛠️ Wartung und Troubleshooting

### Log-Dateien prüfen

```bash
# Apache Fehler-Logs
sudo tail -f /var/log/apache2/error.log

# CatControl spezifische Logs
sudo tail -f /var/log/apache2/catcontrol_error.log

# PHP Fehler-Logs
sudo tail -f /var/log/apache2/error.log | grep PHP
```

### Häufige Probleme

#### Problem: Upload-Fehler
```bash
# Berechtigungen prüfen
ls -la /var/www/html/catcontrol/uploads/
# Sollte www-data:www-data mit 775 Berechtigungen zeigen

# PHP Upload-Limits prüfen
php -i | grep upload_max_filesize
```

#### Problem: Datenbank-Verbindungsfehler
```bash
# MariaDB Status prüfen
sudo systemctl status mariadb

# Verbindung testen
mysql -u phpuser -p catcontrol
```

#### Problem: E-Mail-Versand funktioniert nicht
```bash
# PHP CURL und OpenSSL prüfen
php -m | grep -E "(curl|openssl)"

# SMTP-Verbindung testen
telnet smtp.gmail.com 587
```

### Performance-Optimierung

#### MySQL/MariaDB optimieren

```bash
sudo nano /etc/mysql/mariadb.conf.d/50-server.cnf
```

Empfohlene Einstellungen für kleine bis mittlere Installationen:
```ini
innodb_buffer_pool_size = 128M
innodb_log_file_size = 32M
query_cache_type = 1
query_cache_size = 32M
```

#### Apache2 optimieren

```bash
sudo nano /etc/apache2/mods-available/mpm_prefork.conf
```

Anpassungen:
```apache
<IfModule mpm_prefork_module>
    StartServers 2
    MinSpareServers 2
    MaxSpareServers 5
    MaxRequestWorkers 150
    MaxConnectionsPerChild 3000
</IfModule>
```

## 🔄 Updates

### System-Updates
```bash
sudo apt update && sudo apt upgrade -y
sudo systemctl restart apache2 mariadb
```

### CatControl-Updates
1. Backup erstellen (siehe Backup-Section)
2. Neue Version herunterladen
3. Dateien ersetzen (config-Verzeichnis ausgenommen)
4. Browser-Cache leeren

### Datenbank-Upgrade (bestehende Installationen)

Führen Sie folgende SQL-Befehle aus, wenn Sie von einer älteren Version aktualisieren:

```sql
-- Nur ausführen, falls Spalte nicht existiert
ALTER TABLE kittens ADD COLUMN sex ENUM('kater','katze','unbekannt') DEFAULT 'unbekannt';

-- Nur ausführen, falls Spalte nicht existiert
ALTER TABLE kittens ADD COLUMN is_archived BOOLEAN DEFAULT FALSE AFTER is_public;

-- Nur ausführen, falls Wert 'gelb' noch nicht im ENUM vorhanden ist
ALTER TABLE feeding_records MODIFY COLUMN stool_color ENUM('braun','schwarz','orange','rot','grau','gelb','sonstiges');

-- Augenstatus Feld ergänzen (falls nicht vorhanden)
ALTER TABLE feeding_records ADD COLUMN eyes_open BOOLEAN NULL AFTER fitness_level;

-- Optional: first_login Default nur für Neuinstallationen ändern
-- (bestehende Tabellen können so bleiben); für Neu-User wird ohnehin 0 gesetzt
```

## 📞 Support und Dokumentation

### Standard-Benutzer
- **Benutzername:** admin
- **Passwort:** katze (beim ersten Login wird nur für den Benutzer `admin` eine Passwortänderung erzwungen)

### Wichtige Verzeichnisse
- **Anwendung:** `/var/www/html/catcontrol/`
- **Uploads:** `/var/www/html/catcontrol/uploads/`
- **Konfiguration:** `/var/www/html/catcontrol/config/`
- **Logs:** `/var/log/apache2/`

### Systemanforderungen erfüllt?
```bash
# PHP Version prüfen
php --version

# PHP Module prüfen
php -m | grep -E "(pdo|mysql|gd|fileinfo|curl)"

# Apache Module prüfen
apache2ctl -M | grep -E "(rewrite|ssl)"

# Speicherplatz prüfen
df -h /var/www/html/catcontrol/
```

## 📄 Lizenz

Dieses Projekt ist für den privaten und nicht-kommerziellen Gebrauch bestimmt. 

---

**Viel Erfolg bei der Verwaltung Ihrer Kätzchen! 🐱**
