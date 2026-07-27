# syntax=docker/dockerfile:1
#
# Vault PRODUCTION image. A single container: FrankenPHP serves the application
# and manages the TLS certificate.
#
# Built from the repository root:
#   docker build -f deploy/Dockerfile -t vault .

# --- 1. Frontend assets ------------------------------------------------------
FROM node:24-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
# `artisan` is included because laravel-vite-plugin uses it to locate the
# project root; without it the build fails inside the container.
COPY vite.config.ts tsconfig.json artisan ./
COPY resources ./resources
RUN npm run build

# --- 2. PHP base -------------------------------------------------------------
FROM dunglas/frankenphp:1-php8.4 AS base
RUN install-php-extensions pdo_sqlite opcache
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# --- 3. PHP dependencies -----------------------------------------------------
FROM base AS vendor
WORKDIR /app
COPY . .
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-scripts \
 && composer dump-autoload --optimize --no-dev --no-scripts \
 && php artisan package:discover

# --- 4. Runtime --------------------------------------------------------------
FROM base AS runtime
WORKDIR /app

# The vault lives on a volume, never in the image: rebuilding loses nothing.
ENV APP_ENV=production \
    APP_DEBUG=false \
    VAULT_DATA_DIR=/vault-data

COPY --from=vendor /app /app
COPY --from=assets /app/public/build /app/public/build
COPY deploy/Caddyfile /etc/caddy/Caddyfile
COPY deploy/entrypoint.sh /usr/local/bin/vault-entrypoint

RUN chmod +x /usr/local/bin/vault-entrypoint \
 && mkdir -p /vault-data storage/framework/cache/data storage/framework/sessions \
             storage/framework/views storage/logs bootstrap/cache

ENTRYPOINT ["vault-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
