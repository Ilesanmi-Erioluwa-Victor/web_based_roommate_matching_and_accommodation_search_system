FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    git unzip curl libcurl4-openssl-dev pkg-config libssl-dev \
    autoconf automake libtool \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-audit --ignore-platform-req=ext-mongodb

COPY . .

RUN composer dump-autoload --optimize

RUN php -r "echo 'MongoDB ext: ' . (extension_loaded('mongodb') ? 'OK' : 'MISSING') . PHP_EOL;" \
    && php -r "echo 'MongoDB\\Client: ' . (class_exists('MongoDB\\Client') ? 'OK' : 'MISSING') . PHP_EOL;"

EXPOSE 8000

CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8000} -t public"]
