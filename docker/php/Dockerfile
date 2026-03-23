# PHP 8.4 with Apache for the Lampfire development environment.
#
# Installs required PHP extensions, enables mod_rewrite, and
# configures Composer for dependency management.

FROM php:8.4-apache

# Install system dependencies and PHP extensions.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libicu-dev \
        libzip-dev \
        unzip \
        git \
    && docker-php-ext-install \
        intl \
        pdo_mysql \
        zip \
    && pecl install apcu \
    && docker-php-ext-enable apcu \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache modules.
RUN a2enmod rewrite headers

# Copy the virtual host configuration.
COPY docker/php/vhost.conf /etc/apache2/sites-available/000-default.conf

# Set the working directory.
WORKDIR /var/www/app/app

# Install Composer from the official image.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy the entrypoint script that handles dependency installation at
# container start. This avoids the problem where volume mounts overlay
# the vendor directory that was built during the image build.
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
