FROM php:8.2-apache

RUN apt-get update \
 && apt-get install -y --no-install-recommends \
    libjpeg62-turbo-dev \
    libpng-dev \
    libfreetype6-dev \
    libzip-dev \
    unzip netcat-openbsd mariadb-client \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" pdo pdo_mysql gd zip \
 && a2enmod rewrite headers \
 && rm -rf /var/lib/apt/lists/*

# App-Code in den Container kopieren
COPY . /var/www/html/

# PHP Einstellungen
COPY php.ini /usr/local/etc/php/conf.d/zz-app.ini

# Apache: .htaccess erlauben und Rechte setzen
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf \
 && mkdir -p /var/www/html/uploads/kittens /var/www/html/uploads/profiles /var/www/html/uploads/backgrounds /var/www/html/config \
 && chown -R www-data:www-data /var/www/html/uploads /var/www/html/config \
 && chmod -R 775 /var/www/html/uploads /var/www/html/config

# Entrypoint
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
ENTRYPOINT ["/entrypoint.sh"]

CMD ["apache2-foreground"]