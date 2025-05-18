# Use an official PHP image with extensions
FROM php:8.2-fpm

# Set working directory
WORKDIR /var/www

# Install dependencies
RUN apt-get update && apt-get install -y \
    git curl unzip libzip-dev zip \
    libpng-dev libjpeg-dev libonig-dev libxml2-dev \
    python3 python3-pip python3-venv \
    libgl1-mesa-glx libglib2.0-0 ffmpeg libsm6 libxext6 \
    && docker-php-ext-install pdo_mysql zip gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Laravel dependencies
COPY . .

RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# Set up rembg (recommended to avoid [all] shortcut)
RUN pip install --upgrade pip && pip install rembg onnxruntime numpy click aiohttp

# Permissions
RUN chown -R www-data:www-data /var/www && chmod -R 755 /var/www

# Start PHP-FPM
CMD ["php-fpm"]
