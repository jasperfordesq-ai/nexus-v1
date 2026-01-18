# ============================================
# Project NEXUS - Production Docker Image
# Multi-stage build for optimized image size
# ============================================

# ============================================
# Stage 1: Composer Dependencies
# ============================================
FROM composer:2.6 AS composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --ignore-platform-reqs

COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative

# ============================================
# Stage 2: Node.js Assets Build
# ============================================
FROM node:18-alpine AS assets

WORKDIR /app
COPY package*.json ./
RUN npm ci --production=false 2>/dev/null || npm install 2>/dev/null || true

COPY . .
RUN npm run build 2>/dev/null || echo "No build script, skipping"

# ============================================
# Stage 3: Production Image
# ============================================
FROM php:8.1-fpm-alpine AS production

# Build arguments
ARG APP_VERSION=1.0.0
ARG BUILD_DATE
ARG VCS_REF

# Labels
LABEL org.opencontainers.image.title="Project NEXUS" \
      org.opencontainers.image.description="Enterprise Community Platform" \
      org.opencontainers.image.version="${APP_VERSION}" \
      org.opencontainers.image.created="${BUILD_DATE}" \
      org.opencontainers.image.revision="${VCS_REF}" \
      org.opencontainers.image.vendor="NEXUS" \
      org.opencontainers.image.licenses="MIT"

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    freetype-dev \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    mysql-client \
    git \
    && rm -rf /var/cache/apk/*

# Install PHP extensions
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
        opcache \
        bcmath

# Install Redis extension
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/pear

# Create non-root user
RUN addgroup -g 1000 nexus \
    && adduser -u 1000 -G nexus -s /bin/sh -D nexus

# Set working directory
WORKDIR /var/www/html

# Copy configuration files
COPY docker/php/php.ini /usr/local/etc/php/php.ini
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/scripts/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy application files
COPY --from=composer /app/vendor ./vendor
COPY --from=assets /app/httpdocs/assets/dist ./httpdocs/assets/dist
COPY . .

# Create required directories
RUN mkdir -p /var/www/html/logs \
    /var/www/html/storage \
    /var/www/html/storage/exports \
    /var/www/html/storage/uploads \
    /var/log/php \
    /run/nginx

# Set permissions
RUN chown -R nexus:nexus /var/www/html \
    && chmod -R 755 /var/www/html/httpdocs \
    && chmod -R 775 /var/www/html/logs \
    && chmod -R 775 /var/www/html/storage \
    && chown -R nexus:nexus /var/log/php \
    && chown -R nexus:nexus /run/nginx

# Copy and set entrypoint
COPY docker/scripts/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Health check
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

# Expose port
EXPOSE 80

# Environment variables
ENV APP_ENV=production \
    APP_VERSION=${APP_VERSION}

# Set entrypoint
ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

# ============================================
# Stage 4: Development Image
# ============================================
FROM production AS development

# Install development tools
RUN apk add --no-cache \
    git \
    vim \
    bash \
    openssh-client

# Install Xdebug
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del .build-deps \
    && rm -rf /tmp/pear

# Xdebug configuration
RUN echo "xdebug.mode=develop,debug,coverage" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Development PHP settings
RUN echo "display_errors = On" >> /usr/local/etc/php/conf.d/dev.ini \
    && echo "error_reporting = E_ALL" >> /usr/local/etc/php/conf.d/dev.ini

ENV APP_ENV=development
