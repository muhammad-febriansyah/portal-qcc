
FROM php:8.4-cli-bookworm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git unzip curl ca-certificates gnupg \
    libzip-dev libicu-dev libonig-dev \
    && docker-php-ext-install \
    pdo_mysql mbstring bcmath intl zip pcntl \
    && mkdir -p /etc/apt/keyrings \
    && curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key \
       | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_22.x nodistro main" \
       > /etc/apt/sources.list.d/nodesource.list \
    && apt-get update \
    && apt-get install -y nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copy project
COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/root/.composer/cache \
    composer install --no-interaction --prefer-dist --no-scripts --no-autoloader

COPY . .
RUN printf '%s\n' \
    'upload_max_filesize=100M' \
    'post_max_size=110M' \
    'memory_limit=256M' \
    > /usr/local/etc/php/conf.d/uploads.ini \
 && cp .env.example .env \
 && composer dump-autoload --optimize \
 && php artisan package:discover --ansi

# Generate Laravel key
RUN php artisan key:generate

# Install frontend dependencies
RUN npm ci

# Build frontend
RUN npm run build

# Set permissions
RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
