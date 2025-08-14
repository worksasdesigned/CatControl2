FROM php:8.2-apache

# Install system dependencies and PHP extensions
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libzip-dev \
    unzip \
    mariadb-client \
    netcat-openbsd \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" pdo pdo_mysql gd zip \
 && a2enmod rewrite headers \
 && rm -rf /var/lib/apt/lists/*

# PHP configuration
COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini

# Apache: allow overrides via .htaccess
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

WORKDIR /var/www/html

# Copy application code
COPY . /var/www/html

# Entrypoint
COPY docker/php/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
ENTRYPOINT ["/entrypoint.sh"]
CMD ["apache2-foreground"]