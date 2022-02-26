#!/bin/sh

cd /var/www/social/file || exit 1

mkdir -p oauth && cd oauth || exit 1

if [ ! -f private.key ]; then
    openssl genrsa -out private.key 4096
    openssl rsa -in private.key -pubout -out public.key

    chown www-data:www-data private.key public.key
else
    echo "Keys exist, nothing to do"
fi
