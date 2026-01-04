FROM php:8.4-cli-alpine

# Install required system dependencies
RUN apk add --no-cache \
    libc6-compat \
    udev \
    tzdata

# Set timezone (optional but recommended)
ENV TZ=Europe/Lisbon

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set workdir
WORKDIR /app

# Copy composer files first for layer caching
COPY composer.json composer.lock ./

# Install PHP dependencies (production only)
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

# Copy application source
COPY . .

# Ensure correct permissions
RUN chmod +x /app

# Healthcheck (basic)
HEALTHCHECK CMD php -v || exit 1

# Default command (can be overridden)
CMD ["php", "-S", "0.0.0.0:8080", "-t", "/app"]
