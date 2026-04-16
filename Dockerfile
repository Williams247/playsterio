FROM php:8.3-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libssl-dev \
    pkg-config \
    && rm -rf /var/lib/apt/lists/*

# Laravel + Composer need these; route:cache also requires cacheable routes (no closures in web.php)
RUN docker-php-ext-install -j"$(nproc)" \
    mbstring \
    xml \
    zip \
    pcntl \
    bcmath \
    exif

RUN pecl install mongodb && docker-php-ext-enable mongodb

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY . /var/www/html
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

WORKDIR /var/www/html

RUN if [ -f .env.example ]; then cp .env.example .env; else \
    printf '%s\n' \
      'APP_NAME=Laravel' \
      'APP_ENV=production' \
      'APP_KEY=' \
      'APP_DEBUG=false' \
      'APP_URL=http://localhost' \
      'LOG_CHANNEL=stack' \
      'QUEUE_CONNECTION=sync' \
      'CACHE_STORE=file' \
      'SESSION_DRIVER=file' \
      'DB_CONNECTION=mongodb' \
      'MONGODB_URI=' \
      'MONGODB_DATABASE=audiodec' \
      > .env; \
    fi

# Do NOT run config:cache / route:cache here: the image .env has empty secrets. Render injects
# env at runtime; cached config would ignore MONGODB_URI, APP_KEY, etc. and cause 500s in prod.
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress \
    && php artisan key:generate --force \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 10000
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
