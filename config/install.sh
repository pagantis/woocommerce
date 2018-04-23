#!/bin/bash

cd /var/www/html/

if [ ! -f app/etc/local.xml ]; then

    RET=1
    while [ $RET -ne 0 ]; do
        mysql -h $WORDPRESS_DB_HOST -u $WORDPRESS_DB_USER -p$WORDPRESS_DB_PASSWORD -e "status" > /dev/null 2>&1
        RET=$?
        if [ $RET -ne 0 ]; then
            echo "Waiting for confirmation of MySQL service startup";
            sleep 5
        fi
    done

    echo "CREATING wp-config.php"
    wp  --allow-root config create \
        --dbname=$WORDPRESS_DB_NAME \
        --dbuser=$WORDPRESS_DB_USER \
        --dbpass=$WORDPRESS_DB_PASSWORD \
        --dbhost=$WORDPRESS_DB_HOST \

    echo "INSTALLING"
    wp  --allow-root core install \
        --url=$WORDPRESS_URL \
        --title=$WORDPRESS_SITE_NAME \
        --admin_user=$WORDPRESS_ADMIN_USER \
        --admin_password=$WORDPRESS_ADMIN_PASSWORD \
        --admin_email=$WORDPRESS_ADMIN_EMAIL
fi

exec "$@"
