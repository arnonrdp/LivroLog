FROM php:8.4-fpm

# Install system dependencies, Nginx, Supervisor, PHP extensions, Redis, and Xdebug
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    git \
    nginx \
    supervisor \
    libicu-dev \
    libonig-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libxml2-dev \
    libzip-dev \
    unzip \
    zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath zip intl gd \
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

# Create required directories for storage and logs
RUN mkdir -p /var/www/html/bootstrap/cache /var/www/html/storage/logs \
    /var/www/html/storage/framework/{cache,sessions,testing,views} \
    /var/log/supervisor

WORKDIR /var/www/html

# Configure Nginx
RUN rm -f /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default
COPY docker/nginx.conf /etc/nginx/sites-available/laravel
RUN ln -s /etc/nginx/sites-available/laravel /etc/nginx/sites-enabled/laravel

# Configure Supervisor to run both PHP-FPM and Nginx
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy and make entrypoint executable
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/bootstrap/cache /var/www/html/storage

# Expose port 8080 for Nginx
EXPOSE 8080

# Use entrypoint to run Laravel optimizations, then start supervisor
ENTRYPOINT ["/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
