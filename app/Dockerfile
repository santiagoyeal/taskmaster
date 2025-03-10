FROM php:7.4-apache

# Instalar la extensión mysqli
RUN docker-php-ext-install mysqli

# Activar el módulo de Apache para reescrituras de URL (mod_rewrite)
RUN a2enmod rewrite
