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

COPY . /app
COPY frameworks/skim/Caddyfile /etc/frankenphp/Caddyfile

WORKDIR /app/frameworks/skim

RUN composer install --no-dev --optimize-autoloader

WORKDIR /app

ENV FRANKENPHP_CONFIG="worker /app/frameworks/skim/public/worker.php"

EXPOSE 80 443
