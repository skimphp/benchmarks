FROM php:8.5-fpm-alpine

RUN apk add --no-cache \
    bash git unzip curl linux-headers \
    libzip-dev icu-dev icu-libs oniguruma-dev \
    libpq-dev postgresql-dev \
    $PHPIZE_DEPS

RUN apk add --no-cache --virtual .build-deps \
    build-base openssl-dev \
    && git clone https://github.com/wg/wrk.git /tmp/wrk \
    && cd /tmp/wrk \
    && make \
    && cp wrk /usr/local/bin/ \
    && cd / \
    && rm -rf /tmp/wrk \
    && apk del .build-deps

RUN apk add --no-cache python3 py3-matplotlib py3-numpy

RUN docker-php-ext-configure pdo_pgsql --with-pdo-pgsql=/usr \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        pdo_pgsql \
        mbstring \
        intl \
        zip \
        bcmath \
        pcntl \
        posix \
        sockets

RUN pecl install redis && docker-php-ext-enable redis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/bench/php.ini /usr/local/etc/php/conf.d/skim.ini
COPY docker/bench/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf

WORKDIR /app
