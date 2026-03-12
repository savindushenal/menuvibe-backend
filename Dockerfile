FROM php:8.3-cli

# Install system dependencies and PHP extensions including gd
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libxml2-dev \
    libonig-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql mbstring xml curl zip bcmath tokenizer fileinfo opcache gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy composer files first for caching
COPY composer.json composer.lock ./

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# Copy application files
COPY . .

# Set permissions
RUN mkdir -p storage/framework/cache/data storage/framework/sessions \
    storage/framework/views storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Run artisan discovery
RUN php artisan package:discover --ansi

EXPOSE 8000

CMD mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && php artisan migrate --force \
    && php artisan route:clear \
    && php artisan config:clear \
    && php artisan cache:clear \
    && php artisan storage:link --force 2>/dev/null \
    && php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
