# AdPilot AI production container (Render free web service).
FROM php:8.3-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev \
    && docker-php-ext-install pdo_mysql bcmath opcache zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --no-progress --prefer-dist --optimize-autoloader

COPY . .
RUN composer dump-autoload --optimize

# Render uses healthCheckPath /up from render.yaml; no in-container HEALTHCHECK needed.
# Migrations run at container start (env vars present by then); APP_KEY is required.
ENV PORT=10000
EXPOSE 10000

CMD ["sh", "-c", "php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan migrate --force && exec php artisan serve --host=0.0.0.0 --port=${PORT:-10000}"]
