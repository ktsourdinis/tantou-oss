# ==============================================================================
# ⚡ Tantou (担当) — Human Capacity OS
# Production Dockerfile (PHP 8.4-FPM Alpine)
# ==============================================================================

FROM php:8.4-fpm-alpine

# Set working directory
WORKDIR /var/www/html

# Install system runtime & build dependencies
RUN apk add --no-cache \
    curl \
    zip \
    unzip \
    git \
    bash \
    icu-data-full \
    icu-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    oniguruma-dev \
    mariadb-client \
    shadow

# Configure and install core PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        pcntl \
        bcmath \
        gd \
        intl \
        zip \
        opcache

# Install PHP Redis extension via PECL
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Copy Composer from official Composer image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configure non-root user (UID/GID 1000:1000)
RUN usermod -u 1000 www-data && groupmod -g 1000 www-data

# Copy custom PHP configuration recommendations
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 20M/g' "$PHP_INI_DIR/php.ini" \
    && sed -i 's/post_max_size = 8M/post_max_size = 25M/g' "$PHP_INI_DIR/php.ini" \
    && sed -i 's/memory_limit = 128M/memory_limit = 512M/g' "$PHP_INI_DIR/php.ini"

# Set permissions for www-data
RUN chown -R www-data:www-data /var/www/html

# Switch to non-root user
USER www-data

# Expose FastCGI port
EXPOSE 9000

CMD ["php-fpm"]
