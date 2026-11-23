# syntax=docker/dockerfile:1
#
# CollabSpace — one container: Nginx + PHP-FPM 8.4 + Laravel Reverb + scheduler (supervisord).
#   docker compose up --build        → http://localhost:8000
#   Render (render.yaml)             → free web service
#
# ---------- 1) Front-end assets (Vite + Tailwind) ----------
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json .npmrc ./
RUN npm ci --ignore-scripts --no-audit --no-fund
COPY . .
RUN npm run build

# ---------- 2) Application ----------
FROM php:8.4-fpm-alpine

RUN apk add --no-cache nginx supervisor sqlite icu-libs libzip \
 && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev linux-headers \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql pcntl zip intl opcache \
 && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Dependencies first (better layer caching). Dev packages stay: the demo seeder uses Faker.
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-autoloader --no-progress

COPY . .
COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-interaction \
 && rm -f public/hot \
 && chown -R www-data:www-data storage bootstrap/cache database

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-collabspace.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-collabspace.conf
COPY docker/nginx.conf /etc/nginx/http.d/default.conf.template
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint && rm -f /etc/nginx/http.d/default.conf

ENV PORT=8000
EXPOSE 8000

ENTRYPOINT ["entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
