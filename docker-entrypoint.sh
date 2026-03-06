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

# Start Apache in foreground
exec apache2-foreground
