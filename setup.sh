#!/bin/bash

# CatControl Setup Script für Debian 12 LXC
# Automatisiert die Grundinstallation des Systems

set -e  # Exit bei Fehlern

# Farben für Output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging
LOG_FILE="/tmp/catcontrol-setup.log"
exec > >(tee -a ${LOG_FILE})
exec 2>&1

print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Banner
echo -e "${GREEN}"
cat << "EOF"
 ██████╗ █████╗ ████████╗     ██████╗ ██████╗ ███╗   ██╗████████╗██████╗  ██████╗ ██╗     
██╔════╝██╔══██╗╚══██╔══╝    ██╔════╝██╔═══██╗████╗  ██║╚══██╔══╝██╔══██╗██╔═══██╗██║     
██║     ███████║   ██║       ██║     ██║   ██║██╔██╗ ██║   ██║   ██████╔╝██║   ██║██║     
██║     ██╔══██║   ██║       ██║     ██║   ██║██║╚██╗██║   ██║   ██╔══██╗██║   ██║██║     
╚██████╗██║  ██║   ██║       ╚██████╗╚██████╔╝██║ ╚████║   ██║   ██║  ██║╚██████╔╝███████╗
 ╚═════╝╚═╝  ╚═╝   ╚═╝        ╚═════╝ ╚═════╝ ╚═╝  ╚═══╝   ╚═╝   ╚═╝  ╚═╝ ╚═════╝ ╚══════╝
                                                                                           
                    🐱 Kätzchen Verwaltungssystem - Setup Script 🐱
EOF
echo -e "${NC}"

print_status "Starte CatControl Setup für Debian 12..."

# Root-Rechte prüfen
if [[ $EUID -ne 0 ]]; then
   print_error "Dieses Script muss als root ausgeführt werden (sudo ./setup.sh)"
   exit 1
fi

# Debian Version prüfen
if ! grep -q "bookworm" /etc/os-release; then
    print_warning "Dieses Script ist für Debian 12 (bookworm) optimiert."
    read -p "Möchten Sie trotzdem fortfahren? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

# Variablen
WEBROOT="/var/www/html/catcontrol"
DB_NAME="catcontrol"
DB_USER="phpuser"
BACKUP_DIR="/var/backups/catcontrol"

print_status "=== Schritt 1: System aktualisieren ==="
apt update && apt upgrade -y
print_success "System aktualisiert"

print_status "=== Schritt 2: Pakete installieren ==="
apt install -y \
    apache2 \
    mariadb-server \
    php8.2 \
    php8.2-mysql \
    php8.2-gd \
    php8.2-curl \
    php8.2-fileinfo \
    php8.2-zip \
    php8.2-mbstring \
    unzip \
    wget \
    curl \
    git \
    certbot \
    python3-certbot-apache \
    ufw \
    fail2ban

print_success "Alle Pakete installiert"

print_status "=== Schritt 3: Services aktivieren ==="
systemctl enable apache2 mariadb
systemctl start apache2 mariadb
print_success "Services aktiviert und gestartet"

print_status "=== Schritt 4: Apache Module aktivieren ==="
a2enmod rewrite
a2enmod ssl
a2enmod headers
systemctl reload apache2
print_success "Apache Module aktiviert"

print_status "=== Schritt 5: PHP konfigurieren ==="
PHP_INI="/etc/php/8.2/apache2/php.ini"

# PHP Einstellungen optimieren
sed -i 's/upload_max_filesize = .*/upload_max_filesize = 10M/' $PHP_INI
sed -i 's/post_max_size = .*/post_max_size = 10M/' $PHP_INI
sed -i 's/max_execution_time = .*/max_execution_time = 300/' $PHP_INI
sed -i 's/memory_limit = .*/memory_limit = 256M/' $PHP_INI
sed -i 's/;date.timezone =.*/date.timezone = Europe\/Berlin/' $PHP_INI

# shell_exec erlauben (aus disable_functions entfernen)
if grep -qE '^\s*disable_functions\s*=' "$PHP_INI"; then
    sed -i 's/^\(\s*disable_functions\s*=\s*.*\)\bshell_exec\b,\?\s*/\1/' "$PHP_INI"
    # führendes Komma nach Entfernen bereinigen
    sed -i 's/^\(\s*disable_functions\s*=\s*\),\s*/\1/' "$PHP_INI"
    # abschließendes Komma entfernen
    sed -i 's/\(disable_functions\s*=\s*.*\),\s*$/\1/' "$PHP_INI"
else
    echo "disable_functions =" >> "$PHP_INI"
fi

# Änderungen anwenden
systemctl reload apache2

print_success "PHP konfiguriert"

print_status "=== Schritt 6: Webverzeichnis erstellen ==="
mkdir -p $WEBROOT
mkdir -p $WEBROOT/uploads/{kittens,profiles,backgrounds}
mkdir -p $WEBROOT/config
mkdir -p $BACKUP_DIR

# Berechtigungen setzen
chown -R www-data:www-data $WEBROOT
chmod -R 755 $WEBROOT
chmod -R 775 $WEBROOT/uploads
chmod -R 775 $WEBROOT/config

print_success "Webverzeichnis erstellt und konfiguriert"

print_status "=== Schritt 7: MariaDB konfigurieren ==="

# Sichere MariaDB Installation
print_status "MariaDB wird gesichert..."

# Root-Passwort setzen (falls noch nicht gesetzt)
mysql -e "UPDATE mysql.user SET Password=PASSWORD('$(openssl rand -base64 32)') WHERE User='root';" 2>/dev/null || true
mysql -e "DELETE FROM mysql.user WHERE User='';" 2>/dev/null || true
mysql -e "DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');" 2>/dev/null || true
mysql -e "DROP DATABASE IF EXISTS test;" 2>/dev/null || true
mysql -e "DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';" 2>/dev/null || true
mysql -e "FLUSH PRIVILEGES;" 2>/dev/null || true

print_success "MariaDB gesichert"

print_status "=== Schritt 8: Virtual Host konfigurieren ==="

# Apache Virtual Host erstellen
cat > /etc/apache2/sites-available/catcontrol.conf << EOF
<VirtualHost *:80>
    DocumentRoot $WEBROOT
    
    <Directory $WEBROOT>
        AllowOverride All
        Require all granted
        Options -Indexes
    </Directory>
    
    # Upload-Limits
    php_admin_value upload_max_filesize 10M
    php_admin_value post_max_size 10M
    php_admin_value max_execution_time 300
    
    # Sicherheits-Header
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options SAMEORIGIN
    Header always set X-XSS-Protection "1; mode=block"
    
    ErrorLog \${APACHE_LOG_DIR}/catcontrol_error.log
    CustomLog \${APACHE_LOG_DIR}/catcontrol_access.log combined
</VirtualHost>
EOF

# Virtual Host aktivieren
a2ensite catcontrol.conf
a2dissite 000-default.conf
systemctl reload apache2

print_success "Virtual Host konfiguriert"

print_status "=== Schritt 9: Firewall konfigurieren ==="
ufw --force reset
ufw default deny incoming
ufw default allow outgoing
ufw allow ssh
ufw allow 'Apache Full'
ufw --force enable

print_success "Firewall konfiguriert"

print_status "=== Schritt 10: Fail2Ban konfigurieren ==="

# Fail2Ban Konfiguration
cat > /etc/fail2ban/jail.local << EOF
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 5
ignoreip = 127.0.0.1/8 ::1 192.168.0.0/16 10.0.0.0/8 172.16.0.0/12

[apache-auth]
enabled = true

[apache-badbots]
enabled = true

[apache-noscript]
enabled = true

[apache-overflows]
enabled = true
EOF

systemctl enable fail2ban
systemctl restart fail2ban

print_success "Fail2Ban konfiguriert"

print_status "=== Schritt 11: Backup-Script erstellen ==="

# Backup-Script erstellen
cat > /usr/local/bin/catcontrol-backup.sh << 'EOF'
#!/bin/bash
BACKUP_DIR="/var/backups/catcontrol"
DATE=$(date +%Y%m%d_%H%M%S)
DB_USER="phpuser"
DB_NAME="catcontrol"

# Verzeichnis erstellen
mkdir -p $BACKUP_DIR

# Config-Datei für DB-Passwort lesen
if [ -f "/var/www/html/catcontrol/config/database.php" ]; then
    DB_PASS=$(php -r "
    \$config = include '/var/www/html/catcontrol/config/database.php';
    echo \$config['password'];
    ")
    
    # Datenbank-Backup
    mysqldump -u$DB_USER -p$DB_PASS $DB_NAME > $BACKUP_DIR/catcontrol_db_$DATE.sql
    
    # Uploads-Backup
    tar -czf $BACKUP_DIR/catcontrol_uploads_$DATE.tar.gz -C /var/www/html/catcontrol uploads/
    
    # Alte Backups löschen (älter als 30 Tage)
    find $BACKUP_DIR -type f -mtime +30 -delete
    
    echo "Backup erstellt: $DATE"
else
    echo "CatControl noch nicht installiert - kein Backup möglich"
fi
EOF

chmod +x /usr/local/bin/catcontrol-backup.sh

print_success "Backup-Script erstellt"

print_status "=== Schritt 12: Index-Seite erstellen ==="

# Temporäre Index-Seite
cat > $WEBROOT/index.html << EOF
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CatControl - Setup erforderlich</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
        .container { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); max-width: 600px; margin: 0 auto; }
        .logo { font-size: 3em; margin-bottom: 20px; }
        h1 { color: #ff6b6b; }
        .step { background: #e3f2fd; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #2196f3; }
        .success { background: #d4edda; border-left-color: #28a745; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">🐱</div>
        <h1>CatControl System</h1>
        <div class="step success">✅ Server-Setup abgeschlossen!</div>
        <div class="step">📁 CatControl-Dateien in dieses Verzeichnis kopieren</div>
        <div class="step">🌐 install.php aufrufen und Installation abschließen</div>
        <div class="step">🗑️ install.php nach der Installation löschen</div>
        
        <h2>Nächste Schritte:</h2>
        <ol style="text-align: left;">
            <li>CatControl-Dateien nach <code>$WEBROOT</code> kopieren</li>
            <li><a href="install.php">install.php</a> aufrufen</li>
            <li>Datenbank-Konfiguration eingeben</li>
            <li>E-Mail-Einstellungen konfigurieren</li>
            <li>Installation abschließen</li>
        </ol>
    </div>
</body>
</html>
EOF

chown www-data:www-data $WEBROOT/index.html

print_success "Index-Seite erstellt"

print_status "=== Setup abgeschlossen! ==="

echo
echo -e "${GREEN}================================="
echo "🐱 CatControl Setup erfolgreich!"
echo "=================================${NC}"
echo
echo -e "${BLUE}Server-Informationen:${NC}"
echo "• Web-Verzeichnis: $WEBROOT"
echo "• Backup-Verzeichnis: $BACKUP_DIR"
echo "• Log-Dateien: /var/log/apache2/"
echo
echo -e "${BLUE}Nächste Schritte:${NC}"
echo "1. CatControl-Dateien nach $WEBROOT kopieren"
echo "2. http://$(hostname -I | awk '{print $1}')/install.php aufrufen"
echo "3. Installation abschließen"
echo "4. install.php löschen"
echo
echo -e "${BLUE}Nützliche Befehle:${NC}"
echo "• Logs anzeigen: sudo tail -f /var/log/apache2/catcontrol_error.log"
echo "• Services neustarten: sudo systemctl restart apache2 mariadb"
echo "• Backup erstellen: sudo /usr/local/bin/catcontrol-backup.sh"
echo "• Firewall Status: sudo ufw status"
echo
echo -e "${YELLOW}Sicherheitshinweise:${NC}"
echo "• Ändern Sie alle Standard-Passwörter"
echo "• Aktivieren Sie automatische Updates"
echo "• Überwachen Sie die Log-Dateien"
echo "• Erstellen Sie regelmäßige Backups"
echo
echo -e "${GREEN}Setup-Log gespeichert unter: $LOG_FILE${NC}"
echo

# Service-Status anzeigen
print_status "Service-Status:"
systemctl is-active apache2 && echo "✅ Apache2: Aktiv" || echo "❌ Apache2: Inaktiv"
systemctl is-active mariadb && echo "✅ MariaDB: Aktiv" || echo "❌ MariaDB: Inaktiv"
systemctl is-active fail2ban && echo "✅ Fail2Ban: Aktiv" || echo "❌ Fail2Ban: Inaktiv"

print_success "CatControl Server ist bereit! 🐱"