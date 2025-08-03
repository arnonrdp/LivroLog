FROM php:8.4-fpm

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libzip-dev zip unzip git curl libpng-dev libonig-dev libxml2-dev libicu-dev \
    && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath zip intl

# Install Redis extension
RUN pecl install redis \
    && docker-php-ext-enable redis

# Install Xdebug
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && echo "xdebug.mode=debug,develop,coverage" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.log=/var/log/xdebug.log" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set PHP upload limits
RUN echo "post_max_size = 100M" >> /usr/local/etc/php/conf.d/99-upload-limits.ini && \
    echo "upload_max_filesize = 100M" >> /usr/local/etc/php/conf.d/99-upload-limits.ini && \
    echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/99-upload-limits.ini && \
    echo "max_input_time = 300" >> /usr/local/etc/php/conf.d/99-upload-limits.ini && \
    echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/99-upload-limits.ini

WORKDIR /var/www/html
