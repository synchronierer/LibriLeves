#!/usr/bin/env bash
set -e
umask 0002
# Verzeichnisse anlegen + Rechte setzen
install -d -m 2775 -o www-data -g www-data /var/www/html/media /var/www/html/media/covers
chown -R www-data:www-data /var/www/html/media || true
find /var/www/html/media -type d -exec chmod 2775 {} + || true
find /var/www/html/media -type f -exec chmod 664 {} + || true

# Webserver starten
exec apache2-foreground
