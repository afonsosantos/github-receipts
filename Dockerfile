FROM php:8.4-cli-alpine

# Install system dependencies required for intl
RUN apk add --no-cache \
    icu-dev \
    libc6-compat \
    udev \
    tzdata

# Build and enable PHP intl extension
RUN docker-php-ext-install intl

# Set timezone
ENV TZ=Europe/Lisbon

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set workdir
WORKDIR /app

# Copy composer files first (better cache)
COPY composer.json composer.lock ./

# Install PHP dependencies (production only)
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

# Copy application source
COPY . .

# Healthcheck
HEALTHCHECK CMD php -v || exit 1

# Run PHP built-in server
CMD ["php", "-S", "0.0.0.0:8080", "-t", "/app"]
