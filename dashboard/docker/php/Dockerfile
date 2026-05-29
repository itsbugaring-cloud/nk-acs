FROM composer:2 AS vendor
WORKDIR /app
COPY dashboard/app/composer.json /app/composer.json
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --ignore-platform-req=ext-sockets

FROM php:8.4-apache

RUN sed -i 's|http://|https://|g' /etc/apt/sources.list.d/*.sources 2>/dev/null; \
    sed -i 's|http://|https://|g' /etc/apt/sources.list 2>/dev/null; true

RUN apt-get update \
 && apt-get install -y --no-install-recommends default-mysql-client libcurl4-openssl-dev libonig-dev \
 && docker-php-ext-install mysqli curl mbstring sockets \
 && a2enmod rewrite headers \
 && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY dashboard/app/ /var/www/html/
COPY --from=vendor /app/vendor /var/www/html/vendor
COPY dashboard/docker/php/entrypoint.sh /usr/local/bin/dashboard-entrypoint.sh

RUN chmod +x /usr/local/bin/dashboard-entrypoint.sh \
 && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && { \
      echo "memory_limit=256M"; \
      echo "upload_max_filesize=32M"; \
      echo "post_max_size=32M"; \
      echo "max_execution_time=120"; \
      echo "date.timezone=Asia/Jakarta"; \
    } > /usr/local/etc/php/conf.d/dashboard.ini \
 && chown -R www-data:www-data /var/www/html

ENTRYPOINT ["dashboard-entrypoint.sh"]
CMD ["apache2-foreground"]
