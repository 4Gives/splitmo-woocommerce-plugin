#!/bin/bash

source ~/.bashrc
#Site configuration options
SITE_TITLE="Dev Site"
#Space-separated list of plugin ID's to install and activate
PLUGINS="woocommerce all-in-one-wp-migration"

echo "Setting up WordPress"
cd /var/www/html;

echo "Installing Additional Plugin Dependencies";
composer install;

if $WP_RESET ; then
    echo "Resetting WP"
    wp plugin delete $PLUGINS
    wp db reset --yes
    rm -f wp-config.php;
fi

if [ ! -f wp-config.php ]; then 
    echo "Configuring";
    wp config create --dbhost="$WORDPRESS_DB_HOST" --dbname="$WORDPRESS_DB_NAME" --dbuser="$WORDPRESS_DB_USER" --dbpass="$WORDPRESS_DB_PASSWORD" --skip-check;
    wp core install --url="$SITE_URL" --title="$SITE_TITLE" --admin_user="$WORDPRESS_ADMIN_USERNAME" --admin_email="$WORDPRESS_ADMIN_EMAIL" --admin_password="$WORDPRESS_ADMIN_PASSWORD" --skip-email;

    # Install Default Plugins
    echo "Installing Default Plugins"
    wp plugin install $PLUGINS --activate

    # install additional plugins added in /data/plugins
    echo "Installing Additional Plugins"
    cp -r /mnt/.devcontainer/data/plugins/* /var/www/html/wp-content/plugins
    find "/mnt/.devcontainer/data/plugins/" -mindepth 1 -maxdepth 1 -type d | while read -r plugin; do
        echo "Installing $(basename $plugin)"
        wp plugin activate $(basename $plugin)
    done

    #Create Test Product
    wp wc product create --user=admin --name="Test Product" --regular_price=500.00

    #Change Checkout block style to classic woocommerce
    wp post update 8 --post_content='<!-- wp:woocommerce/classic-shortcode {"shortcode":"checkout"} /-->'

    # wp plugin activate additional plugins
    wp plugin activate plugin-dev

    # enable woocommerce plugin-dev as payment_gateway
    wp wc payment_gateway update splitmo_payment_gateway --enabled=1 --user=$WORDPRESS_ADMIN_USERNAME

    #Allow woocommerce logging
    echo "if ( ! defined( 'FS_METHOD' ) ) define( 'FS_METHOD', 'direct' );" >> wp-config.php

    #Set File Upload Limit
    echo "@ini_set( 'upload_max_filesize' , '128M' );" >> wp-config.php
    echo "@ini_set( 'post_max_size', '128M');" >> wp-config.php
    echo "@ini_set( 'memory_limit', '256M' );" >> wp-config.php
    echo "@ini_set( 'max_execution_time', '300' );" >> wp-config.php
    echo "@ini_set( 'max_input_time', '300' );" >> wp-config.php

    if [ "$WORDPRESS_DEBUG" -eq 1 ]; then
        wp config set WP_DEBUG true --raw
        wp config set WP_DEBUG_LOG true --raw
    fi

else
    echo "Already configured"
fi