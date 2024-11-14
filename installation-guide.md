# Backup-Monitor Installation Guide

## 1. Systemvoraussetzungen installieren

Zuerst aktualisieren wir das System und installieren alle benötigten Pakete:

```bash
# System aktualisieren
apt update
apt upgrade -y

# Benötigte Pakete installieren
apt install nginx mysql-server php-fpm php-mysql php-mbstring php-xml php-curl unzip -y
```

## 2. MySQL einrichten

```bash
# MySQL sichern
mysql_secure_installation

# Folgen Sie den Anweisungen:
# - Setup VALIDATE PASSWORD plugin? Nein (einfachheitshalber)
# - Neues Root-Passwort setzen: Ja (merken Sie sich das Passwort!)
# - Remove anonymous users? Ja
# - Disallow root login remotely? Ja
# - Remove test database? Ja
# - Reload privilege tables? Ja

# Datenbank und Benutzer anlegen
mysql -u root -p
```

In der MySQL-Konsole:
```sql
CREATE DATABASE backup_monitor;
CREATE USER 'backup_user'@'localhost' IDENTIFIED BY 'IhrSicheresPasswort';
GRANT ALL PRIVILEGES ON backup_monitor.* TO 'backup_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

## 3. Nginx konfigurieren

```bash
# Standard-Konfiguration sichern
cp /etc/nginx/sites-available/default /etc/nginx/sites-available/default.bak

# Neue Konfiguration erstellen
nano /etc/nginx/sites-available/backup-monitor
```

Fügen Sie folgende Konfiguration ein:
```nginx
server {
    listen 80;
    server_name _;  # Später mit Ihrer Domain ersetzen
    root /var/www/backup-monitor;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php-fpm.sock;
    }

    location ~ /\. {
        deny all;
    }
}
```

Aktivieren Sie die neue Konfiguration:
```bash
# Symlink erstellen
ln -s /etc/nginx/sites-available/backup-monitor /etc/nginx/sites-enabled/

# Standard-Konfiguration deaktivieren
rm /etc/nginx/sites-enabled/default

# Nginx-Konfiguration testen
nginx -t

# Nginx neustarten
systemctl restart nginx
```

## 4. PHP-FPM konfigurieren

```bash
# PHP-FPM Konfiguration anpassen
nano /etc/php/[VERSION]/fpm/php.ini
```

Ändern Sie folgende Werte:
```ini
memory_limit = 256M
upload_max_filesize = 64M
post_max_size = 64M
max_execution_time = 300
```

```bash
# PHP-FPM neustarten
systemctl restart php[VERSION]-fpm
```

## 5. Backup-Monitor installieren

```bash
# Verzeichnis erstellen
mkdir -p /var/www/backup-monitor
cd /var/www/backup-monitor

# Rechte setzen
chown -R www-data:www-data /var/www/backup-monitor
chmod -R 755 /var/www/backup-monitor
```

Jetzt müssen Sie alle Dateien des Backup-Monitors in das Verzeichnis `/var/www/backup-monitor` kopieren. Sie können dies entweder per SFTP tun oder wenn Sie ein Git-Repository haben:

```bash
cd /var/www/backup-monitor
git clone [REPOSITORY-URL] .
```

Verzeichnisstruktur erstellen:
```bash
mkdir -p config
mkdir -p logs
chmod 777 config logs  # Temporär für die Installation
```

## 6. Web-Installation durchführen

1. Öffnen Sie im Browser: `http://[IHRE-SERVER-IP]/install/`
2. Folgen Sie dem Installations-Assistenten:
   - Systemvoraussetzungen werden geprüft
   - Geben Sie die Datenbank-Informationen ein:
     * Host: localhost
     * Datenbank: backup_monitor
     * Benutzer: backup_user
     * Passwort: IhrSicheresPasswort

3. Nach erfolgreicher Installation:
```bash
# Rechte wieder einschränken
chmod 755 config
chmod 644 config/config.php
rm -rf install  # Installations-Verzeichnis löschen
```

## 7. Mail-Verarbeitung einrichten

```bash
# Cron-Job für Mail-Verarbeitung einrichten
crontab -e
```

Fügen Sie folgende Zeile hinzu:
```
*/5 * * * * php /var/www/backup-monitor/process_mails.php
```

## 8. Sicherheit

```bash
# Firewall einrichten
ufw enable
ufw allow 22    # SSH
ufw allow 80    # HTTP
ufw allow 443   # HTTPS (falls später benötigt)

# Regelmäßige Updates
apt install unattended-upgrades
dpkg-reconfigure -plow unattended-upgrades
```

## 9. Backup-Monitor konfigurieren

1. Öffnen Sie im Browser: `http://[IHRE-SERVER-IP]`
2. Klicken Sie auf "Einstellungen"
3. Konfigurieren Sie:
   - E-Mail-Einstellungen (POP3-Zugangsdaten)
   - Backup-Status und Suchbegriffe
   - Erste Kunden und Backup-Jobs

## 10. Monitoring einrichten (optional)

```bash
# Monitoring für Dienste
apt install monitoring-plugins-basic

# Disk-Space prüfen
df -h /var/www/backup-monitor

# Log-Monitoring
tail -f /var/www/backup-monitor/logs/error.log
```

## Wartung

Regelmäßige Wartungsaufgaben:
```bash
# Logs rotieren
nano /etc/logrotate.d/backup-monitor
```

Fügen Sie hinzu:
```
/var/www/backup-monitor/logs/*.log {
    weekly
    rotate 4
    compress
    delaycompress
    missingok
    notifempty
    create 0644 www-data www-data
}
```

## Troubleshooting

Wichtige Log-Dateien:
- Nginx: `/var/log/nginx/error.log`
- PHP: `/var/log/php[VERSION]-fpm.log`
- Backup-Monitor: `/var/www/backup-monitor/logs/error.log`

Status-Überprüfung:
```bash
# Nginx Status
systemctl status nginx

# PHP-FPM Status
systemctl status php[VERSION]-fpm

# MySQL Status
systemctl status mysql

# Firewall Status
ufw status
```

## Backup
```bash
# Datenbank-Backup
mysqldump -u backup_user -p backup_monitor > /root/backup_monitor_$(date +%Y%m%d).sql

# Dateien-Backup
tar -czf /root/backup_monitor_files_$(date +%Y%m%d).tar.gz /var/www/backup-monitor
```

## Update

Wenn Updates verfügbar sind:
```bash
cd /var/www/backup-monitor
git pull  # wenn Git verwendet wird

# oder neue Dateien manuell übertragen

chown -R www-data:www-data /var/www/backup-monitor
```

Haben Sie noch Fragen zu bestimmten Schritten oder möchten Sie weitere Details zu einem bestimmten Bereich?
