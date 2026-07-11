#!/bin/bash
set -e

if [ "$1" = 'start' ]; then
    php -v
    ### Check if a vendor installed ###
    if [ ! -d "/var/www/vendor" ]
    then
        rm -f ./bootstrap/cache/*.php
        su - -c "composer install" www-data
        su - -c "composer clear-cache" www-data
    fi
    exec /usr/bin/supervisord -n -c /var/www/docker/app/supervisord.conf
    exit
fi

if [ "$1" = 'composer' ]; then
    rm -f ./bootstrap/cache/*.php
    su - -c "composer install" www-data
    su - -c "composer clear-cache" www-data
    exit
fi

exec "$@"
