# Use PHP-FPM untuk kontrol lebih baik terhadap upload limits
FROM php:8.2-fpm

# Set the main working directory inside the container
WORKDIR /app

# Install dependencies
RUN apt-get update && apt-get install -y \
    nginx \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libwebp-dev \
    libzip-dev \
    zip unzip \
    pkg-config \
    libxml2-dev \
    libonig-dev \
    zlib1g-dev \
    supervisor \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install gd zip pdo pdo_mysql mysqli

# Configure PHP untuk handle upload banyak file
RUN { \
    echo 'max_file_uploads = 100'; \
    echo 'upload_max_filesize = 200M'; \
    echo 'post_max_size = 500M'; \
    echo 'memory_limit = 512M'; \
    echo 'max_execution_time = 300'; \
    echo 'max_input_time = 300'; \
} > /usr/local/etc/php/conf.d/uploads.ini


# --- Docker Cache Optimization ---
# Copy only the composer files first
COPY composer.json composer.lock ./

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php \
    && mv composer.phar /usr/local/bin/composer

# Copy the rest of your application files
COPY . .

# Run composer install.
# This will only be re-run if composer.json or composer.lock changes.
# The dunglas/frankenphp image already includes Composer.
RUN composer install --no-dev --no-interaction --no-scripts --optimize-autoloader
RUN composer dump-autoload --no-dev --optimize

# --- Application Setup ---
# Create .env from example, as artisan needs it to run without errors.
RUN cp .env.example .env
RUN php artisan key:generate

# IMPORTANT: Clear any cached configuration from the host environment.
# This is crucial if bootstrap/cache was committed to git or copied
# from a dev environment that had dev-only providers cached (like Laravel Sail).
RUN php artisan config:clear && php artisan cache:clear

# Set the correct file permissions for Laravel's storage and cache folders.
# The standard web server user in Debian-based images is 'www-data'.
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache && \
    chmod -R 775 /app/storage /app/bootstrap/cache

# Copy Nginx configuration
COPY docker/nginx.conf /etc/nginx/sites-available/default

# Copy Supervisor configuration
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Expose port 8000
EXPOSE 8000

# Start Supervisor (akan jalankan nginx + php-fpm)
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
