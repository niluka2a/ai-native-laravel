# Development image: PHP 8.3 + extensions + Composer.
# The application code is bind-mounted at /app by docker-compose.yml.
FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libzip-dev \
    && docker-php-ext-install pdo_mysql zip pcntl \
    && rm -rf /var/lib/apt/lists/*

# Dev-friendly limits: the planted slow endpoint must be slow, not time out.
RUN printf 'max_execution_time=120\nmemory_limit=512M\n' > /usr/local/etc/php/conf.d/zz-dev.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/start.sh /usr/local/bin/start-api

RUN chmod +x /usr/local/bin/start-api

WORKDIR /app
EXPOSE 3001

CMD ["start-api"]
