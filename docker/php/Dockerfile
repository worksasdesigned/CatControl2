FROM php:8.2-apache

# Install system dependencies and PHP extensions
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libzip-dev \
    unzip \
    git \
    mariadb-client \
    curl \
    netcat-openbsd \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" pdo pdo_mysql gd zip \
 && a2enmod rewrite headers \
 && rm -rf /var/lib/apt/lists/*

# PHP configuration
COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini

# Apache configuration: allow .htaccess overrides
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Optional: clone latest from Git during image build
ARG CLONE_FROM_GIT=0
ARG REPO_URL=https://github.com/worksasdesigned/CatControl2.git
ARG GIT_REF=main
WORKDIR /var/www/html
RUN if [ "$CLONE_FROM_GIT" = "1" ]; then \
      rm -rf /var/www/html/* /var/www/html/.git && \
      git clone --depth 1 -b "$GIT_REF" "$REPO_URL" /var/www/html; \
    fi

# Entrypoint
COPY docker/php/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
ENTRYPOINT ["/entrypoint.sh"]
CMD ["apache2-foreground"]