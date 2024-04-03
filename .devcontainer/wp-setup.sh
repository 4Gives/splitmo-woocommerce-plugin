#!  /bin/bash

#Site configuration options
SITE_TITLE="Dev Site"
#Space-separated list of plugin ID's to install and activate
PLUGINS="woocommerce"

#Set to true to wipe out and reset your wordpress install (on next container rebuild)
WP_RESET=true


echo "Setting up WordPress"
DEVDIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
cd /var/www/html;
if $WP_RESET ; then
    echo "Resetting WP"
    wp plugin delete $PLUGINS
    wp db reset --yes
    rm wp-config.php;
fi

if [ ! -f wp-config.php ]; then 
    echo "Configuring";
    wp config create --dbhost="$WORDPRESS_DB_HOST" --dbname="$WORDPRESS_DB_NAME" --dbuser="$WORDPRESS_DB_USER" --dbpass="$WORDPRESS_DB_PASSWORD" --skip-check;
    wp core install --url="$SITE_URL" --title="$SITE_TITLE" --admin_user="$WORDPRESS_ADMIN_USERNAME" --admin_email="$WORDPRESS_ADMIN_EMAIL" --admin_password="$WORDPRESS_ADMIN_PASSWORD" --skip-email;
    wp plugin install $PLUGINS --activate
    #TODO: Only activate plugin if it contains files - i.e. might be developing a theme instead

    #Data import
    cd $DEVDIR/data/

    #Data Migration
    #Add .sql files to /data/backups
    for f in *.sql; do
        wp db import $f
    done

    # wp plugin activate additional plugins
    wp plugin install plugin-dev --activate
    # install additional plugins added in /data/plugins
    cp -r plugins/* /var/www/html/wp-content/plugins
    for p in plugins/*; do
        wp plugin activate $(basename $p)
    done

else
    echo "Already configured"
fi