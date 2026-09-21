FROM dunglas/frankenphp:php8.5

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

RUN rm -rf /app/* /app/.* 2>/dev/null || true && \
    composer create-project slim/slim-skeleton . --no-interaction

COPY public/index.php /app/public/index.php
COPY Caddyfile /etc/frankenphp/Caddyfile

ENV APP_ENV=prod
