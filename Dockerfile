FROM php:8.1-apache

COPY fyp/ /var/www/html/

RUN a2enmod rewrite
RUN docker-php-ext-install mysqli

WORKDIR /var/www/html
