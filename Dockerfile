# AdPilot AI production container (Render free web service).

FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY resources ./resources
COPY vite.config.js ./
COPY tailwind.config.js postcss.config.js ./
RUN npm run build

FROM php:8.3-cli

RUN apt-get update && apt-get install -y --no-install-recommends 
        git unzip libzip-dev 
    && docker-php-ext-install pdo_mysql bcmath opcache zip 
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --no-progress --prefer-dist --optimize-autoloader

COPY . .
COPY --from=assets /app/public/build ./public/build
RUN composer dump-autoload --optimize

ENV PORT=10000
EXPOSE 10000

CMD ["sh", "-c", "php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan migrate --force && exec php artisan serve --host=0.0.0.0 --port=${PORT:-10000}"]
