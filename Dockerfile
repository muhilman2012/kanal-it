FROM php:8.4-apache-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends python3-reportlab unzip libonig-dev libsqlite3-dev libxml2-dev libcurl4-openssl-dev && docker-php-ext-install mbstring pdo_sqlite dom curl && a2enmod rewrite && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . .
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|' /etc/apache2/sites-available/000-default.conf && composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader && chown -R www-data:www-data storage bootstrap/cache
