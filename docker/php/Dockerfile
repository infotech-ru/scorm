FROM php:8.2-cli
RUN apt-get update && apt-get install -y --no-install-recommends libzip-dev libicu-dev \
    && docker-php-ext-install pdo_mysql zip intl \
    && rm -rf /var/lib/apt/lists/*
RUN echo "upload_max_filesize=200M\npost_max_size=200M\nmemory_limit=512M" > /usr/local/etc/php/conf.d/app.ini
WORKDIR /app
