#!/bin/sh

case $SOCIAL_DBMS in
    "mariadb")
        CMD=mysqladmin ping --silent -hdb -uroot -p${MYSQL_ROOT_PASSWORD}
        ;;
    "postgres")
        CMD=su postgres && pg_isready -hdb -q
        ;;
    *)
        exit 1

esac

while ! $CMD;
do
    echo "Waiting for DB..."
    sleep 3
done