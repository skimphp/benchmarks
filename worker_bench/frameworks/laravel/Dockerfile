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
        opcache \
        apcu \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Production opcache settings
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

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN rm -rf /app/* /app/.* 2>/dev/null || true && composer create-project laravel/laravel . --no-interaction

RUN composer require laravel/octane --no-interaction && php artisan octane:install --server=frankenphp

# Force production env before caching
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

COPY routes/web.php /app/routes/web.php

# Re-cache routes after our custom routes are in place
RUN php artisan route:cache

CMD ["php", "artisan", "octane:start", "--server=frankenphp", "--workers=16", "--max-requests=100000", "--host=0.0.0.0", "--port=80", "--admin-port=2019", "--no-interaction"]

EXPOSE 80 443
