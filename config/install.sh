#!/bin/bash

cd /var/www/html/

if [ ! -f app/etc/local.xml ]; then

    echo "CHECKING DB CONNECTION"
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

    echo "INSTALLING WORDPRESS"
    wp  --allow-root core install \
        --url=$WORDPRESS_URL \
        --title=$WORDPRESS_SITE_NAME \
        --admin_user=$WORDPRESS_ADMIN_USER \
        --admin_password=$WORDPRESS_ADMIN_PASSWORD \
        --admin_email=$WORDPRESS_ADMIN_EMAIL

    echo "SETTING WORDPRESS THEME"
    #    wp --allow-root theme activate twentytwenty

    echo "INSTALLING WOOCOMMERCE"
    wp --allow-root plugin install wordpress-importer --activate
    wp --allow-root plugin install https://github.com/woocommerce/woocommerce/archive/$WOOCOMMERCE_VERSION.zip --activate
    echo "IMPORTING PRODUCTS TO WC WITH > /dev/null 2>&1"
    wp --allow-root import wp-content/plugins/woocommerce/sample-data/sample_products.xml --authors=create > /dev/null 2>&1
    echo "PRODUCTS IMPORTED WITH SUCCESS"

    echo "ACTIVATING PAGANTIS PLUGIN"
    wp --allow-root plugin activate pagantis


    echo "SETTING WOOCOMMERCE OPTIONS"
    wp --allow-root option delete woocommerce_admin_notices
    wp --allow-root option update woocommerce_store_city Madrid
    wp --allow-root option update woocommerce_store_address Castellana
    wp --allow-root option update woocommerce_default_country ES
    wp --allow-root option update woocommerce_store_postcode 28008
    wp --allow-root option update woocommerce_currency EUR
    wp --allow-root option update woocommerce_cart_redirect_after_add yes

    echo "GENERATING PAGES + SETTING DEFAULT ONE"
    CARTIDPAGE=`wp --allow-root post create --post_type=page  --user=admin --post_title=Carro    --post_status=publish --post_content=[woocommerce_cart] --porcelain`
    CHECKOUTIDPAGE=`wp --allow-root post create --post_type=page  --user=admin --post_title=Checkout --post_status=publish --post_content=[woocommerce_checkout] --porcelain`
    SHOPIDPAGE=`wp --allow-root post create --post_type=page  --user=admin --post_title=Shop --post_status=publish --post_content="<a id='goToCart' class='button' href='http://$WORDPRESS_URL/?page_id=$CARTIDPAGE'>Ir al carro</a></p>" --porcelain`
    wp --allow-root option update woocommerce_cart_page_id $CARTIDPAGE
    wp --allow-root option update woocommerce_checkout_page_id $CHECKOUTIDPAGE
    wp --allow-root option update woocommerce_shop_page_id $SHOPIDPAGE
    wp --allow-root option update show_on_front	page
    wp --allow-root option update page_on_front	$SHOPIDPAGE
    wp --allow-root wc --user=admin shipping_zone_method create 0 --method_id=flat_rate

    echo "ADDING DEVELOPMENT CONSTANTS"
    wp --allow-root config set --add --type=constant WP_DEBUG_LOG true
    wp --allow-root config set --add --type=constant WP_DEBUG_DISPLAY true
    wp --allow-root config set --add --type=constant WP_DEBUG true
    wp --allow-root config set --add --type=constant SAVEQUERIES true

    echo "CHANGING WP TIMEZONE"
    wp --allow-root option update timezone_string "Europe/Madrid"

    echo "REMOVING WP CLUTTER"
    wp --allow-root plugin delete hello
    wp --allow-root plugin delete akismet

    echo "REMOVING EXAMPLE PAGE"
    wp --allow-root post delete 2

    currentDateTimne=`date +"%D %T"`
    echo "Current Server Time is $currentDateTimne"
    chown -R www-data:www-data /var/www/html/*
fi

exec "$@"
