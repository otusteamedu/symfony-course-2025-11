FROM php:8.3-fpm-alpine

# Install dev dependencies
RUN apk update \
    && apk upgrade --available \
    && apk add --virtual build-deps \
        autoconf \
        build-base \
        icu-dev \
        libevent-dev \
        openssl-dev \
        zlib-dev \
        libzip \
        libzip-dev \
        zlib \
        zlib-dev \
        bzip2 \
        git \
        libpng \
        libpng-dev \
        libjpeg \
        libjpeg-turbo-dev \
        libwebp-dev \
        freetype \
        freetype-dev \
        postgresql-dev \
        linux-headers \
        libmemcached-dev \
        curl \
        wget \
        bash

RUN echo 'memory_limit = 4096M' >> /usr/local/etc/php/conf.d/docker-php-memlimit.ini

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype=/usr/include/ --with-jpeg=/usr/include/
RUN docker-php-ext-install -j$(getconf _NPROCESSORS_ONLN) \
    intl \
    gd \
    bcmath \
    pdo_pgsql \
    sockets \
    zip
RUN pecl channel-update pecl.php.net \
    && pecl install -o -f \
        redis \
        event \
        memcached \
    && rm -rf /tmp/pear \
    && echo "extension=redis.so" > /usr/local/etc/php/conf.d/redis.ini \
    && echo "extension=memcached.so" > /usr/local/etc/php/conf.d/memcached.ini \
    && echo "extension=event.so" > /usr/local/etc/php/conf.d/event.ini

# XDebug
RUN apk add --no-cache linux-headers \
    && apk add --update --no-cache --virtual .build-dependencies $PHPIZE_DEPS

#RUN pecl install xdebug \
#    && docker-php-ext-enable xdebug
#RUN echo "xdebug.mode=debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
#RUN echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
#RUN echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
#RUN echo "xdebug.client_port=9003" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
#
#RUN pecl clear-cache \
#    && apk del .build-dependencies

# Symfony installer
COPY --link \
   --from=ghcr.io/symfony-cli/symfony-cli:latest \
   /usr/local/bin/symfony /usr/local/bin/symfony
