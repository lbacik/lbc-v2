FROM php:8.2 as base

RUN apt -y update \
    && apt -y install git unzip \
    && apt -y clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* \
    && rm -rf /var/cache/apk/*

COPY --from=composer:2.1 /usr/bin/composer /usr/bin/composer

COPY . /opt/app
WORKDIR /opt/app

RUN composer install
    # --no-devgc --no-interaction --no-progress --no-suggest --optimize-autoloader --classmap-authoritative

FROM php:8.2-fpm

COPY --from=base /opt/app /opt/app/
WORKDIR /opt/app

RUN chown -R www-data:www-data /opt/app/var
