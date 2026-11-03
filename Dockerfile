FROM composer:2 AS vendor

WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-progress --optimize-autoloader --no-scripts

FROM node:26.13.0-bookworm-slim AS frontend

WORKDIR /var/www/html
RUN npm install --global pnpm@10.33.0
COPY package.json pnpm-lock.yaml pnpm-workspace.yaml ./
RUN pnpm install --frozen-lockfile
COPY . .
RUN pnpm build

FROM php:8.5.12-cli-bookworm AS app

WORKDIR /var/www/html
RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libonig-dev libzip-dev unzip \
    && docker-php-ext-install bcmath intl mbstring opcache pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=vendor /var/www/html/vendor ./vendor
COPY --from=frontend /var/www/html/public/build ./public/build
COPY . .

RUN mkdir -p storage/app/private storage/app/public storage/framework/cache \
    storage/framework/sessions storage/framework/views storage/logs \
    && chmod -R ug+rwX storage bootstrap/cache

EXPOSE 8000
CMD ["sh", "-c", "php artisan storage:link || true; php artisan optimize; php artisan serve --host=0.0.0.0 --port=8000"]
