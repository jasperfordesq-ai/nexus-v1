# =============================================================================
# Project NEXUS - Development Dockerfile
# =============================================================================
# Uses Apache with mod_rewrite (required for .htaccess routing)
# PHP 8.1 with all required extensions
# =============================================================================

FROM php:8.2-apache

# Build arguments
ARG DEBIAN_FRONTEND=noninteractive

# =============================================================================
# System Dependencies
# =============================================================================
RUN apt-get update && apt-get install -y --no-install-recommends \
    # Build tools
    libpng-dev \
    libjpeg-dev \
    libwebp-dev \
    libfreetype6-dev \
    libicu-dev \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    # Utilities
    curl \
    git \
    unzip \
    default-mysql-client \
    # Clean up
    && rm -rf /var/lib/apt/lists/*

# =============================================================================
# PHP Extensions (matching composer.json requirements)
# =============================================================================
RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        gd \
        intl \
        zip \
        mbstring \
        bcmath \
        opcache \
        xml \
        curl \
        fileinfo

# Redis extension (for sessions/cache)
RUN pecl install redis && docker-php-ext-enable redis

# =============================================================================
# Apache Configuration
# =============================================================================
# Enable required modules
RUN a2enmod rewrite headers expires deflate

# Set document root to httpdocs (where index.php lives)
ENV APACHE_DOCUMENT_ROOT=/var/www/html/httpdocs

# Update Apache config to use httpdocs as document root
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Allow .htaccess overrides
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Pass environment variables to PHP via Apache
# This ensures Docker env vars are available to PHP via $_SERVER and getenv()
RUN echo 'PassEnv DB_HOST DB_PORT DB_NAME DB_USER DB_PASS DB_TYPE REDIS_HOST REDIS_PORT APP_ENV APP_DEBUG APP_URL SENTRY_DSN_PHP SENTRY_ENVIRONMENT SENTRY_TRACES_SAMPLE_RATE' >> /etc/apache2/conf-available/passenv.conf \
    && a2enconf passenv

# =============================================================================
# PHP Configuration (Development)
# =============================================================================
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

# Custom PHP settings
RUN echo "\n\
; Project NEXUS Development Settings\n\
display_errors = On\n\
error_reporting = E_ALL\n\
log_errors = On\n\
error_log = /var/log/php_errors.log\n\
\n\
; Performance\n\
memory_limit = 256M\n\
max_execution_time = 60\n\
max_input_time = 60\n\
max_input_vars = 3000\n\
\n\
; File uploads\n\
upload_max_filesize = 50M\n\
post_max_size = 55M\n\
max_file_uploads = 20\n\
\n\
; Sessions (file-based for dev, Redis in container)\n\
session.gc_maxlifetime = 7200\n\
session.cookie_httponly = 1\n\
\n\
; Timezone\n\
date.timezone = UTC\n\
\n\
; OPcache (less aggressive for dev)\n\
opcache.enable = 1\n\
opcache.validate_timestamps = 1\n\
opcache.revalidate_freq = 0\n\
" >> "$PHP_INI_DIR/php.ini"

# =============================================================================
# Working Directory
# =============================================================================
WORKDIR /var/www/html

# =============================================================================
# Composer (for dependency management)
# =============================================================================
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# =============================================================================
# Health Check Endpoint
# =============================================================================
# Create a simple health check file
RUN mkdir -p /var/www/html/httpdocs && \
    echo '<?php header("Content-Type: application/json"); echo json_encode(["status" => "healthy", "timestamp" => date("c")]);' \
    > /var/www/html/httpdocs/health.php

# =============================================================================
# Permissions
# =============================================================================
RUN chown -R www-data:www-data /var/www/html

# Create upload directories with correct ownership so Docker volumes mount
# with the right permissions (named volumes initialise from image content)
RUN mkdir -p /var/www/html/httpdocs/uploads /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html/httpdocs/uploads /var/www/html/uploads \
    && chmod -R 775 /var/www/html/httpdocs/uploads /var/www/html/uploads

# =============================================================================
# Expose Port
# =============================================================================
EXPOSE 80

# =============================================================================
# Default Command
# Ensure upload dirs are writable by www-data at each startup (handles volume
# re-mounts that reset ownership) then start Apache.
# =============================================================================
CMD ["sh", "-c", "chown -R www-data:www-data /var/www/html/httpdocs/uploads /var/www/html/uploads 2>/dev/null; chmod -R 775 /var/www/html/httpdocs/uploads /var/www/html/uploads 2>/dev/null; apache2-foreground"]
