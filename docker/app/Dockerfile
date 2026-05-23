# syntax=docker/dockerfile:1.7

ARG PHP_VERSION=8.3

###############################################################################
# Base image: PHP-FPM with all required extensions for Symfony + Strava sync
###############################################################################
FROM php:${PHP_VERSION}-fpm-bookworm AS base

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1 \
    COMPOSER_MEMORY_LIMIT=-1 \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libpq-dev \
        libicu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype-dev \
        libmagickwand-dev \
        libwebp-dev \
        cron \
        ca-certificates \
    && docker-php-ext-configure intl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        intl \
        opcache \
        pdo_pgsql \
        pgsql \
        zip \
        gd \
        bcmath \
    && pecl install redis imagick \
    && docker-php-ext-enable redis imagick \
    && apt-get purge -y --auto-remove \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf

WORKDIR /var/www/html

###############################################################################
# Deps stage: install composer dependencies (cached layer)
###############################################################################
FROM base AS deps

COPY composer.json composer.lock* ./
RUN --mount=type=cache,target=/root/.composer/cache \
    composer install --no-dev --no-scripts --no-autoloader --prefer-dist

###############################################################################
# Production image
###############################################################################
FROM base AS prod

ARG APP_ENV=prod
ENV APP_ENV=${APP_ENV} \
    APP_DEBUG=0

COPY --from=deps /var/www/html/vendor ./vendor
COPY . .

RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
    && mkdir -p var/cache var/log var/maps var/tiles \
    && chown -R www-data:www-data var \
    && chmod +x bin/console docker/app/entrypoint.sh

ENTRYPOINT ["docker/app/entrypoint.sh"]
CMD ["php-fpm"]

###############################################################################
# Development image (includes dev dependencies + xdebug)
###############################################################################
FROM base AS dev

ENV APP_ENV=dev \
    APP_DEBUG=1

RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/zz-xdebug.ini

# Source mounted at runtime via docker compose volume; install runs on container start
CMD ["php-fpm"]
