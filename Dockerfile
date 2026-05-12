FROM php:8.2-apache

# Enable Apache Rewrite Module
RUN a2enmod rewrite

# Install SQLite extensions
RUN apt-get update && apt-get install -y \
    sqlite3 \
    libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite

# Set working directory
WORKDIR /var/www/html

# We will mount the src directory via docker-compose, but we need to ensure permissions
# This command runs when container starts to ensure mounted volumes have right permissions
CMD chown -R www-data:www-data /var/www/html/uploads /var/www/html/db && apache2-foreground
