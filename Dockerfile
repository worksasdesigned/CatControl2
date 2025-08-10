FROM php:8.2-apache

# Git installieren
RUN apt-get update && apt-get install -y git && rm -rf /var/lib/apt/lists/*

# Repo klonen
RUN git clone https://github.com/worksasdesigned/CatControl2.git /var/www/html/catcontrol

# Apache-Rewrite aktivieren
RUN a2enmod rewrite
