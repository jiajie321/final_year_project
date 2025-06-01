FROM php:8.1-apache

COPY fyp/ /var/www/html/

RUN a2enmod rewrite
RUN docker-php-ext-install mysqli

RUN echo "DirectoryIndex index.php" >> /etc/apache2/apache2.conf

WORKDIR /var/www/html

ENV PORT 80
EXPOSE $PORT
CMD ["apache2-foreground"]
