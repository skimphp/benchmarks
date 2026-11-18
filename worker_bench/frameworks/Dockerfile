ARG FRANKENPHP_VERSION=php8.5

# Shared base: OS deps + PHP extensions + composer. Cached once for all targets.
FROM dunglas/frankenphp:${FRANKENPHP_VERSION} AS base

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
        opcache \
        apcu \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ---------------------------------------------------------------- skim
FROM base AS skim

COPY . /app
COPY frameworks/skim/Caddyfile /etc/frankenphp/Caddyfile

WORKDIR /app/frameworks/skim
RUN composer install --no-dev --optimize-autoloader
WORKDIR /app

ENV FRANKENPHP_CONFIG="worker /app/frameworks/skim/public/worker.php"

# ---------------------------------------------------------------- symfony
FROM base AS symfony

ENV DEFAULT_URI=http://localhost

RUN rm -rf /app/* /app/.* 2>/dev/null || true && \
    composer create-project symfony/skeleton . --no-interaction

COPY frameworks/symfony/src/ /app/src/
COPY frameworks/symfony/public/index.php /app/public/index.php

RUN composer install --no-dev --optimize-autoloader

COPY frameworks/symfony/Caddyfile /etc/frankenphp/Caddyfile

# ---------------------------------------------------------------- laravel
FROM base AS laravel

RUN { \
    echo 'opcache.memory_consumption=256'; \
    echo 'opcache.interned_strings_buffer=16'; \
    echo 'opcache.max_accelerated_files=20000'; \
    echo 'opcache.revalidate_freq=0'; \
    echo 'opcache.validate_timestamps=0'; \
    echo 'opcache.fast_shutdown=1'; \
    echo 'opcache.enable_cli=1'; \
    echo 'opcache.jit=tracing'; \
    echo 'opcache.jit_buffer_size=128M'; \
} > $PHP_INI_DIR/conf.d/opcache.ini

RUN rm -rf /app/* /app/.* 2>/dev/null || true && \
    composer create-project laravel/laravel . --no-interaction

RUN composer require laravel/octane --no-interaction && \
    php artisan octane:install --server=frankenphp

RUN sed -i 's/APP_ENV=.*/APP_ENV=production/' .env && \
    sed -i 's/APP_DEBUG=.*/APP_DEBUG=false/' .env && \
    sed -i 's/LOG_LEVEL=.*/LOG_LEVEL=emergency/' .env && \
    sed -i 's/LOG_CHANNEL=.*/LOG_CHANNEL=null/' .env && \
    sed -i 's/SESSION_DRIVER=.*/SESSION_DRIVER=array/' .env && \
    sed -i 's/CACHE_DRIVER=.*/CACHE_DRIVER=array/' .env && \
    php artisan key:generate && \
    php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache && \
    php artisan event:cache

COPY frameworks/laravel/routes/web.php /app/routes/web.php

RUN php artisan route:cache

CMD ["php", "artisan", "octane:start", "--server=frankenphp", "--workers=16", "--max-requests=100000", "--host=0.0.0.0", "--port=80", "--admin-port=2019", "--no-interaction"]

# ---------------------------------------------------------------- ci4
FROM base AS ci4

RUN rm -rf /app/* /app/.* 2>/dev/null || true && \
    composer create-project codeigniter4/appstarter . --no-interaction

COPY frameworks/ci4/app/Controllers/Home.php /app/app/Controllers/Home.php
COPY frameworks/ci4/app/Config/Routes.php /app/app/Config/Routes.php
COPY frameworks/ci4/public/index.php /app/public/index.php
COPY frameworks/ci4/Caddyfile /etc/frankenphp/Caddyfile

# ---------------------------------------------------------------- slim
FROM base AS slim

RUN rm -rf /app/* /app/.* 2>/dev/null || true && \
    composer create-project slim/slim-skeleton . --no-interaction

COPY frameworks/slim/public/index.php /app/public/index.php
COPY frameworks/slim/Caddyfile /etc/frankenphp/Caddyfile

ENV APP_ENV=prod

# ---------------------------------------------------------------- leaf
FROM base AS leaf

RUN composer init --name=bench/leaf --no-interaction && \
    composer require leafs/leaf --no-interaction

COPY frameworks/leaf/public/index.php /app/public/index.php
COPY frameworks/leaf/Caddyfile /etc/frankenphp/Caddyfile

# ---------------------------------------------------------------- flight
FROM base AS flight

RUN composer init --name=bench/flight --no-interaction && \
    composer require flightphp/core --no-interaction

COPY frameworks/flight/public/index.php /app/public/index.php
COPY frameworks/flight/Caddyfile /etc/frankenphp/Caddyfile
