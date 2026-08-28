# syntax=docker/dockerfile:1

ARG FRANKENPHP_VERSION=1.12-php8.5

#
# Build stage: composer dependencies + compiled assets
#
FROM dunglas/frankenphp:${FRANKENPHP_VERSION} AS build

RUN apt -y update \
    && apt -y install --no-install-recommends git unzip \
    && apt -y clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /opt/app

# Dependencies first, so that a code-only change does not invalidate this layer
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --no-progress

COPY . .

RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
    && composer run-script post-install-cmd \
    && php bin/console asset-map:compile

#
# Runtime stage: FrankenPHP (Caddy + PHP as a single binary)
#
FROM dunglas/frankenphp:${FRANKENPHP_VERSION}

RUN apt -y update \
    && apt -y install --no-install-recommends libcap2-bin \
    && apt -y clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN mv "${PHP_INI_DIR}/php.ini-production" "${PHP_INI_DIR}/php.ini"

COPY <<'INI' ${PHP_INI_DIR}/conf.d/app.ini
expose_php = 0
opcache.preload = /opt/app/config/preload.php
opcache.preload_user = www-data
opcache.memory_consumption = 256
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0
INI

WORKDIR /opt/app
COPY --from=build /opt/app /opt/app

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    SERVER_NAME=:80 \
    SERVER_ROOT=/opt/app/public

# Worker mode: keeps the Symfony kernel booted between requests. Supported out of the
# box by symfony/runtime (Runner\FrankenPhpWorkerRunner) - FrankenPHP exports
# FRANKENPHP_WORKER=1 to the worker script and the runtime picks the runner accordingly.
# Set FRANKENPHP_LOOP_MAX to change the 500-requests-per-worker recycle.
# ENV FRANKENPHP_CONFIG="worker /opt/app/public/index.php"

# Run unprivileged; CAP_NET_BIND_SERVICE is what allows binding :80/:443 as www-data
RUN setcap cap_net_bind_service=+ep /usr/local/bin/frankenphp \
    && chown -R www-data:www-data /opt/app/var /data /config

USER www-data

EXPOSE 80 443 443/udp
