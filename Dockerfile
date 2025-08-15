FROM php:8.2-apache

# System dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    unzip \
    mariadb-client \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" gd pdo pdo_mysql zip mbstring \
 && rm -rf /var/lib/apt/lists/*

# Enable Apache modules and config
RUN a2enmod rewrite
COPY docker/apache.conf /etc/apache2/conf-available/catcontrol.conf
RUN a2enconf catcontrol

# Copy application code
COPY . /var/www/html

# Custom PHP configuration
COPY php.ini /usr/local/etc/php/conf.d/custom.ini

# Permissions
RUN chown -R www-data:www-data /var/www/html

# Entrypoint
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/entrypoint.sh"]
CMD ["apache2-foreground"]