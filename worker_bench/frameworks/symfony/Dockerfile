ARG FRANKENPHP_VERSION=php8.5
FROM dunglas/frankenphp:${FRANKENPHP_VERSION}

WORKDIR /app

RUN apt-get update && apt-get install -y \
    git unzip curl \
    libzip-dev icu-devtools libicu-dev \
    libpq-dev libonig-dev libsqlite3-dev \
    && install-php-extensions \
        pdo_mysql \
        pdo_pgsql \
        pdo_sqlite \
        mbstring \
        intl \
        zip \
        bcmath \
        pcntl \
        posix \
        sockets \
        redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV DEFAULT_URI=http://localhost

RUN rm -rf /app/* /app/.* 2>/dev/null || true && composer create-project symfony/skeleton . --no-interaction

COPY src/ /app/src/
COPY public/index.php /app/public/index.php

RUN composer install --no-dev --optimize-autoloader

COPY Caddyfile /etc/frankenphp/Caddyfile

EXPOSE 80 443
