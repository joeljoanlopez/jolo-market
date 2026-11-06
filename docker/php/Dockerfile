# Versions are pinned on purpose (see docs/architecture.md#stack).
FROM composer:2.11.1 AS composer

FROM php:8.5.12-fpm-alpine

ARG UID=1000
ARG GID=1000
ARG INSTALL_XDEBUG=false
ARG XDEBUG_VERSION=3.5.3

RUN apk add --no-cache icu-libs libzip git unzip shadow \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev linux-headers \
    # OPcache is built into PHP since 8.5, so it is not listed here.
    && docker-php-ext-install -j"$(nproc)" intl pdo_mysql zip \
    && if [ "$INSTALL_XDEBUG" = "true" ]; then \
        pecl install "xdebug-${XDEBUG_VERSION}" && docker-php-ext-enable xdebug; \
    fi \
    && apk del .build-deps \
    && rm -rf /tmp/pear

# Match the host user so files written through the bind mount stay editable.
RUN groupmod -o -g "${GID}" www-data && usermod -o -u "${UID}" -g "${GID}" www-data

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY xdebug.ini /usr/local/etc/php/conf.d/zz-xdebug.ini

ENV COMPOSER_HOME=/tmp/composer

WORKDIR /var/www/backend
USER www-data
