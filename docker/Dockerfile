# docker/Dockerfile

# Verwende ein offizielles PHP-Image mit Apache
FROM php:8.2-apache

# Installiere erforderliche Abhängigkeiten und Erweiterungen
RUN apt-get update && apt-get install -y \
    apt-utils \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libcurl4-openssl-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd \
    && docker-php-ext-install mysqli \
    && docker-php-ext-install curl

# Kopiere den Inhalt deiner Webseite in das Containerverzeichnis
COPY ./src /var/www/html/

# Setze die Berechtigungen
RUN chown -R www-data:www-data /var/www/html
