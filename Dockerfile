FROM php:8.4-cli

# System dependencies
RUN apt-get update && apt-get install -y \
    git curl zip unzip \
    libpng-dev libjpeg-dev libfreetype6-dev \
    libonig-dev libxml2-dev libzip-dev \
    python3 python3-pip \
    fonts-thai-tlwg \
    libmagickwand-dev ghostscript \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install pdo pdo_mysql mbstring zip gd opcache dom xml fileinfo \
    && pecl install imagick \
    && docker-php-ext-enable imagick \
    && pip3 install pymupdf --break-system-packages \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

ENV COMPOSER_MEMORY_LIMIT=-1

WORKDIR /var/www/app

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts

COPY . .

RUN mkdir -p public/uploads/originals public/uploads/temp public/labels public/fonts \
    storage/fonts storage/framework/sessions storage/framework/views storage/framework/cache \
    && cp /usr/share/fonts/truetype/tlwg/TlwgTypo.ttf public/fonts/thai-regular.ttf \
    && cp /usr/share/fonts/truetype/tlwg/TlwgTypo-Bold.ttf public/fonts/thai-bold.ttf \
    && chmod -R 777 storage bootstrap/cache public/uploads public/labels public/fonts

COPY docker-entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

RUN echo "memory_limit = 2048M\nmax_execution_time = 300\nupload_max_filesize = 60M\npost_max_size = 65M" \
    > /usr/local/etc/php/conf.d/custom.ini

EXPOSE 8000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
