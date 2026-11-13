# Versions are pinned on purpose (see docs/architecture.md#stack).
# Build context: the repository root (see .dockerignore).
#   dev   development image (compose.yaml): code mounted from the host, Composer, optional Xdebug.
#   prod  production image (compose.prod.yaml, E6): code and dependencies baked in, no dev dependencies.
FROM composer:2.11.1 AS composer

FROM php:8.5.12-fpm-alpine AS base

RUN apk add --no-cache icu-libs libzip \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev linux-headers \
    # OPcache is built into PHP since 8.5, so it is not listed here.
    && docker-php-ext-install -j"$(nproc)" intl pdo_mysql zip \
    && apk del .build-deps

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /var/www/backend

FROM base AS dev

ARG UID=1000
ARG GID=1000
ARG INSTALL_XDEBUG=false
ARG XDEBUG_VERSION=3.5.3

RUN apk add --no-cache git unzip shadow \
    && if [ "$INSTALL_XDEBUG" = "true" ]; then \
        apk add --no-cache --virtual .build-deps $PHPIZE_DEPS linux-headers \
        && pecl install "xdebug-${XDEBUG_VERSION}" && docker-php-ext-enable xdebug \
        && apk del .build-deps; \
    fi \
    && rm -rf /tmp/pear

# Match the host user so files written through the bind mount stay editable.
RUN groupmod -o -g "${GID}" www-data && usermod -o -u "${UID}" -g "${GID}" www-data

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/zz-xdebug.ini

ENV COMPOSER_HOME=/tmp/composer

USER www-data

# Production dependencies only. composer.json and the lock first, so the layer is reused while they do not change.
FROM base AS vendor

RUN apk add --no-cache git unzip
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
ENV COMPOSER_HOME=/tmp/composer

COPY backend/composer.json backend/composer.lock backend/symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress
COPY backend/ ./
RUN composer dump-autoload --no-dev --classmap-authoritative --no-interaction

FROM base AS prod

ENV APP_ENV=prod \
    APP_DEBUG=0

COPY docker/php/prod.ini /usr/local/etc/php/conf.d/zz-prod.ini
COPY docker/php/entrypoint.sh /usr/local/bin/app-entrypoint
# The code belongs to root and is read-only for php-fpm; only var/ (cache, sessions) is writable.
COPY --from=vendor /var/www/backend /var/www/backend
RUN bin/console cache:warmup --no-interaction \
    && chown -R www-data:www-data var

USER www-data

ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]
