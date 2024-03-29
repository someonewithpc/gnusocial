FROM php:8-fpm-alpine

RUN apk update && apk add git autoconf make gcc g++ file gettext-dev icu-dev zlib-dev libpng-dev gmp-dev \
        mariadb-dev mariadb-client postgresql-dev postgresql-client vips-dev composer ffmpeg > /dev/null

ARG MAKEFLAGS="-j$(cat /proc/cpuinfo | grep processor | wc -l)"

RUN apk add --virtual .phpize-deps $PHPIZE_DEPS

RUN docker-php-ext-install bcmath exif gd gettext gmp intl mysqli opcache pdo pdo_mysql mysqli pdo_pgsql pgsql

RUN cd /tmp && git clone https://github.com/krakjoe/apcu         && cd apcu         && phpize && ./configure --enable-apcu && make install
RUN cd /tmp && git clone https://github.com/php-ds/ext-ds        && cd ext-ds       && phpize && ./configure && make install
RUN cd /tmp && git clone https://github.com/msgpack/msgpack-php  && cd msgpack-php  && phpize && ./configure && make install
RUN cd /tmp && git clone https://github.com/lz4/lz4              && cd lz4          && make install
RUN cd /tmp && git clone https://github.com/phpredis/phpredis    && cd phpredis     && phpize && ./configure --enable-redis-msgpack --enable-redis-lz4 --with-liblz4=yes && make install
RUN cd /tmp && git clone https://github.com/libvips/php-vips-ext && cd php-vips-ext && phpize && ./configure && make install

RUN apk add --no-cache ocaml && cd /tmp && git clone https://github.com/dlitz/texvc.git && cd texvc && make && cp texvc /usr/local/bin/texvc

RUN docker-php-ext-enable opcache ds msgpack redis apcu vips

RUN rm -rf /usr/share/php7 && rm -rf /tmp/* && apk del .phpize-deps gcc g++ autoconf > /dev/null

WORKDIR /var/www/social
