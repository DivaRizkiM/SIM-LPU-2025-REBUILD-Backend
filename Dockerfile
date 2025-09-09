# Stage 1: Use the official FrankenPHP base image
FROM dunglas/frankenphp:php8.3

# Set the main working directory inside the container
WORKDIR /app

# Enable PHP production settings (safer and faster)
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

RUN apt-get update && apt-get install -y \
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
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install gd zip


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

# Set the environment variable for Caddy. 
# Use :80 for local development (HTTP).
ENV SERVER_NAME=http://verifikasilpu.komdigi.go.id
# For production, use your domain name for automatic HTTPS
# ENV SERVER_NAME=your-domain.com

# (Optional) If your application needs the .env file during build, you can copy it here
# COPY .env.production /app/.env

# Ports exposed by Caddy inside the container
EXPOSE 80 
