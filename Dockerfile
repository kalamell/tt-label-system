FROM php:8.2-cli

# System dependencies
RUN apt-get update && apt-get install -y \
    git curl zip unzip libpng-dev libjpeg-dev \
    libonig-dev libxml2-dev libzip-dev \
    python3 python3-pip \
    && docker-php-ext-install pdo pdo_mysql mbstring zip gd opcache \
    && pip3 install pymupdf --break-system-packages \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/app

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts

COPY . .

RUN mkdir -p public/uploads/originals public/uploads/temp public/labels \
    && chmod -R 755 storage bootstrap/cache public/uploads public/labels

EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
