#!/bin/bash
# Development entrypoint for the Lampfire web container.
#
# Installs Composer dependencies if the vendor directory is missing,
# ensures writable directories exist, then starts Apache.

set -e

# Grant www-data read access to the .env file.
# The bind-mounted .env arrives with host-only permissions. The entrypoint
# runs as root, so it can adjust group ownership before Apache drops
# to the www-data user. This keeps the host file owner-restricted
# while allowing the web server to read it.
chgrp www-data /var/www/app/.env
chmod 640 /var/www/app/.env

# Install dependencies when vendor directory is absent.
if [ ! -f /var/www/app/app/vendor/autoload.php ]; then
    echo "Installing Composer dependencies..."
    composer install --no-interaction --prefer-dist --working-dir=/var/www/app/app
fi

# Ensure writable directories exist.
mkdir -p /var/www/app/app/logs
mkdir -p /var/www/app/app/templates_compiled
chown -R www-data:www-data /var/www/app/app/logs
chown -R www-data:www-data /var/www/app/app/templates_compiled

# Start Apache in the foreground.
exec apache2-foreground
