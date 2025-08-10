FROM php:8.2-apache

# Git installieren
RUN apt-get update && apt-get install -y git && rm -rf /var/lib/apt/lists/*

# Repo klonen
RUN git clone https://github.com/worksasdesigned/CatControl2.git /var/www/html/catcontrol

# Apache-Rewrite aktivieren
RUN a2enmod rewrite

FROM php:8.2-apache

RUN apt-get update \
 && apt-get install -y libfreetype6-dev libjpeg62-turbo-dev libpng-dev libzip-dev zip unzip \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" gd pdo pdo_mysql zip mysqli \
 && rm -rf /var/lib/apt/lists/*
