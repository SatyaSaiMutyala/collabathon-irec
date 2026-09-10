# Local-development image for the Collabathon admin panel (Laravel 13).
#
# PHP 8.4, not the 8.3 that composer.json's `"php": "^8.3"` would allow: composer.lock
# pins symfony/filesystem v8.1.5, which itself requires php >=8.4.1, so an 8.3 image
# cannot install the locked dependency set at all. The constraint in composer.json is
# looser than what the lock actually resolves to — follow the lock.
#
# php:8.4-cli rather than fpm+nginx on purpose: the panel is served here with
# `php artisan serve`, which is the same way it is run on the host, so there is no
# second web-server config to keep in sync with production. Composer dependencies are
# installed by the entrypoint rather than baked in, because the source tree is bind
# mounted at runtime and a vendor/ baked into the image would simply be shadowed by it.
FROM php:8.4-cli

# gd is not optional: App\Support\ImageCompressor re-encodes every imported image, and
# khanamiryan/qrcode-detector-decoder decodes with it too. exif is deliberately absent —
# ImageCompressor's docblock documents the host as not having it, and installing it here
# would make container and host disagree about EXIF rotation.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip default-mysql-client \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev libzip-dev libonig-dev \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql gd zip bcmath \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Master Data import pulls a dozen remote assets in one request and is bounded by its own
# 300s limit; the default 128M/30s would kill it first.
RUN { echo 'memory_limit=512M'; \
      echo 'upload_max_filesize=64M'; \
      echo 'post_max_size=64M'; \
      echo 'max_execution_time=300'; } > /usr/local/etc/php/conf.d/collabathon.ini

WORKDIR /var/www/html

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 8000
ENTRYPOINT ["entrypoint"]
