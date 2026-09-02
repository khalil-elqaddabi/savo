# ---------- Frontend build stage ----------
# Node 22 LTS satisfies Vite 8's engines requirement (^20.19.0 || >=22.12.0).
FROM node:22-alpine AS frontend

WORKDIR /app

# Install frontend deps and build the React/Inertia (Vite) production bundle.
# Public build assets are emitted to /app/public/build.
COPY package.json package-lock.json .npmrc ./
RUN npm ci

COPY vite.config.js tsconfig.json .prettierrc .eslintrc.json ./
COPY resources ./resources
COPY public ./public

RUN npm run build

# ---------- PHP / Composer dependencies stage ----------
# Uses a PHP 8.5 base (same major/minor as the runtime image) so composer
# resolves platform/extension checks consistently. No DB access happens here.
FROM php:8.5-cli-bookworm AS composer

WORKDIR /app

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libicu-dev \
        libonig-dev \
        libzip-dev \
        libpq-dev \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        mbstring \
        intl \
        zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install production PHP dependencies exactly as required.
# --no-scripts is used so composer does not boot the Laravel app during build
# (there is no artisan/ app or .env in this stage yet). package:discover runs
# once at container boot in the entrypoint, where the app source is present.
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-scripts

# ---------- Runtime image ----------
# PHP 8.5 FPM + Nginx + Supervisor. Serves the built Laravel app on 0.0.0.0:$PORT.
FROM php:8.5-fpm-bookworm AS runtime

# Install system libs, PHP extensions, Nginx, Supervisor and gettext (for template rendering).
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        gettext-base \
        curl \
        libicu-dev \
        libonig-dev \
        libzip-dev \
        libpq-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libwebp-dev \
        libxml2-dev \
        libcurl4-openssl-dev \
        libssl-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pdo_mysql \
        bcmath \
        mbstring \
        intl \
        exif \
        gd \
        zip \
        opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* \
    && rm -rf /etc/nginx/sites-enabled/default \
    && mkdir -p /etc/nginx/conf.d \
    && mkdir -p /run/php /var/run/supervisor

# ---- Application ----
WORKDIR /var/www/html

COPY --from=composer /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build

# Copy only production-relevant source into the image (no local .env is copied).
COPY --chown=www-data:www-data . .

RUN rm -f .env && \
    mkdir -p bootstrap/cache storage/framework/{cache/data,sessions,views} storage/logs && \
    chown -R www-data:www-data storage bootstrap/cache public/build && \
    chmod -R ug+rwx storage bootstrap/cache

# ---- Nginx / php-fpm / supervisor config ----
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY docker/php-fpm-www.conf /usr/local/etc/php-fpm.d/zz-docker-www.conf
COPY docker/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# entrypoint.sh (ENTRYPOINT + CMD pattern) renders PORT into nginx and boots services.
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf", "-n"]
