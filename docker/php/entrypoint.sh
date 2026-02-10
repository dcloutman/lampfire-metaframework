#!/bin/bash
# Development entrypoint for the Lampfire web container.
#
# Installs Composer dependencies if the vendor directory is missing,
# ensures writable directories exist, then starts Apache.

set -e

# Grant www-data read access to the configuration directory.
# The .config mount arrives with host-only permissions. The entrypoint
# runs as root, so it can adjust group ownership before Apache drops
# to the www-data user. This keeps the host files owner-restricted
# while allowing the web server to read them.
chgrp -R www-data /var/www/app/.config
chmod 750 /var/www/app/.config
chmod 640 /var/www/app/.config/.env

# Install dependencies when vendor directory is absent.
if [ ! -f /var/www/app/code/vendor/autoload.php ]; then
    echo "Installing Composer dependencies..."
    composer install --no-interaction --prefer-dist --working-dir=/var/www/app/code
fi

# Ensure writable directories exist.
mkdir -p /var/www/app/code/logs
mkdir -p /var/www/app/code/templates_compiled
chown -R www-data:www-data /var/www/app/code/logs
chown -R www-data:www-data /var/www/app/code/templates_compiled

# Start Apache in the foreground.
exec apache2-foreground
