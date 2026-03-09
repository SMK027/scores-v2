#!/bin/bash
set -e

# Fix uploads directory permissions at container startup
mkdir -p /var/www/html/public/uploads
chown -R www-data:www-data /var/www/html/public/uploads
chmod -R 777 /var/www/html/public/uploads

# Run database migrations
echo "[entrypoint] Exécution des migrations..."
php /var/www/html/database/migrate.php
echo "[entrypoint] Migrations terminées."

# Configure cron for automatic space purge (every minute)
touch /var/log/scores-purge.log
chown www-data:www-data /var/log/scores-purge.log
# Export env vars into crontab so the PHP script can connect to DB
{
    echo "DB_HOST=${DB_HOST}"
    echo "DB_PORT=${DB_PORT}"
    echo "DB_NAME=${DB_NAME}"
    echo "DB_USER=${DB_USER}"
    echo "DB_PASS=${DB_PASS}"
    echo "APP_DEBUG=${APP_DEBUG:-false}"
    echo ""
    echo "* * * * * /usr/local/bin/php /var/www/html/bin/purge-spaces.php >> /var/log/scores-purge.log 2>&1"
} > /tmp/scores-cron
crontab -u www-data /tmp/scores-cron
rm -f /tmp/scores-cron
service cron start
echo "[entrypoint] Cron démarré (purge auto toutes les minutes)."

# Start Apache in foreground
exec apache2-foreground
