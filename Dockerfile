FROM php:8.3-apache

# Build for development (default) or production: docker build --build-arg APP_ENV=prod .
ARG APP_ENV=dev
ENV APP_ENV=${APP_ENV}

RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libzip-dev libpng-dev libjpeg-dev libfreetype6-dev unzip git \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql intl zip gd opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
COPY docker/apache.conf /etc/apache2/conf-enabled/symfony.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-kdesigns.ini

# Production compiles opcode once and never re-checks the files it has already compiled.
RUN if [ "$APP_ENV" = "prod" ]; then { \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.max_accelerated_files=20000'; \
        echo 'opcache.memory_consumption=192'; \
        echo 'opcache.interned_strings_buffer=16'; \
        echo 'opcache.preload_user=www-data'; \
    } > /usr/local/etc/php/conf.d/zz-opcache-prod.ini; fi

WORKDIR /var/www/html

COPY composer.json composer.lock symfony.lock ./
RUN if [ "$APP_ENV" = "prod" ]; then \
        composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction; \
    else \
        composer install --no-scripts --no-autoloader --prefer-dist --no-interaction; \
    fi

COPY . .
RUN if [ "$APP_ENV" = "prod" ]; then \
        composer dump-autoload --optimize --classmap-authoritative --no-dev; \
    else \
        composer dump-autoload --optimize; \
    fi \
    && mkdir -p var \
    && chown -R www-data:www-data var public/images
