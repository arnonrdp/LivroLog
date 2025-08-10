FROM php:8.4-fpm

# Install system dependencies, PHP extensions, Redis, and Xdebug
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    git \
    libicu-dev \
    libonig-dev \
    libpng-dev \
    libxml2-dev \
    libzip-dev \
    unzip \
    zip \
    && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath zip intl \
    && pecl install redis xdebug \
    && docker-php-ext-enable redis xdebug \
    && echo "xdebug.mode=debug,develop,coverage" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.log=/var/log/xdebug.log" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set PHP upload limits
RUN echo "post_max_size = 100M" >> /usr/local/etc/php/conf.d/99-upload-limits.ini && \
    echo "upload_max_filesize = 100M" >> /usr/local/etc/php/conf.d/99-upload-limits.ini && \
    echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/99-upload-limits.ini && \
    echo "max_input_time = 300" >> /usr/local/etc/php/conf.d/99-upload-limits.ini && \
    echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/99-upload-limits.ini

# Create non-root user for security
RUN groupadd -g 1000 www-data-secure && \
    useradd -u 1000 -g www-data-secure -m -s /bin/bash www-data-secure

WORKDIR /var/www/html

# Change ownership of the working directory to the non-root user
RUN chown -R www-data-secure:www-data-secure /var/www/html

# Switch to non-root user
USER www-data-secure
