# syntax=docker/dockerfile:1
#
# The suite runs on SQL Server, which is not something the stock PHP images
# know how to talk to — pdo_sqlsrv needs Microsoft's ODBC driver underneath it,
# and that only ships for Debian. So the image is built rather than inferred:
# Railway's PHP autodetection would give us a working Laravel and no database.
#
# FrankenPHP serves the app itself, so there is no nginx and no php-fpm to keep
# in step with each other, and it binds whatever port Railway hands it.

# ------------------------------------------------------------- php packages
# Split out so the asset stage can see vendor/. The panel theme imports
# Filament's own stylesheet by relative path, so Tailwind cannot compile it
# without the package on disk.
FROM composer:2 AS vendor

WORKDIR /build
COPY composer.json composer.lock ./
COPY packages ./packages
# Platform checks are skipped here and only here: this stage downloads
# packages, it does not run them. The runtime image below installs intl, exif
# and the rest, and that is where the check that matters happens.
RUN composer install \
        --no-dev --no-scripts --no-autoloader --ignore-platform-reqs \
        --prefer-dist --no-interaction --no-progress

# ---------------------------------------------------------------- asset build
FROM node:22-bookworm-slim AS assets

WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
# The panel theme imports both Filament's stylesheet and the local Savanna
# theme by relative path, so both have to be on disk before Tailwind runs.
COPY packages ./packages
COPY --from=vendor /build/vendor ./vendor
RUN npm run build

# ------------------------------------------------------------------- runtime
FROM dunglas/frankenphp:1-php8.4-bookworm

# Microsoft's ODBC driver, then the two PHP extensions that sit on top of it.
# ACCEPT_EULA is required by the driver's own package, not by us.
RUN apt-get update && apt-get install -y --no-install-recommends \
        curl gnupg2 apt-transport-https ca-certificates unixodbc-dev \
        libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    && curl -fsSL https://packages.microsoft.com/keys/microsoft.asc \
        | gpg --dearmor -o /usr/share/keyrings/microsoft-prod.gpg \
    && echo "deb [signed-by=/usr/share/keyrings/microsoft-prod.gpg] https://packages.microsoft.com/debian/12/prod bookworm main" \
        > /etc/apt/sources.list.d/mssql-release.list \
    && apt-get update \
    && ACCEPT_EULA=Y apt-get install -y --no-install-recommends msodbcsql18 \
    && rm -rf /var/lib/apt/lists/*

RUN install-php-extensions \
        pdo_sqlsrv sqlsrv intl zip gd bcmath exif pcntl opcache

# Only for the optimised autoloader below; the packages themselves are already
# resolved in the stage above.
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

# Resolved once, in the stage above, and carried in rather than resolved twice.
COPY --from=vendor /build/vendor ./vendor

COPY . .
COPY --from=assets /build/public/build ./public/build

RUN composer dump-autoload --optimize --no-dev --classmap-authoritative \
    && php artisan package:discover --ansi

# Written to at runtime, and the image runs as a non-root user.
RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

ENV SERVER_NAME=":${PORT:-8080}"

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

ENTRYPOINT ["entrypoint"]
CMD ["frankenphp", "php-server", "--root", "/app/public", "--listen", ":8080"]
