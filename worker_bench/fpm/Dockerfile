FROM php:8.5-fpm

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/bin/

RUN install-php-extensions \
        pdo_mysql \
        pdo_pgsql \
        pdo_sqlite \
        mbstring \
        intl \
        zip \
        bcmath \
        posix \
        sockets \
        redis \
        opcache \
        apcu

COPY fpm/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf
COPY fpm/php.ini /usr/local/etc/php/conf.d/bench.ini
