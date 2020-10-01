


# Load BlackFire
if [ "${ENABLE_BLACKFIRE}" != "1" ]; then
	rm /usr/local/etc/php/conf.d/blackfire.ini
fi

# Load xDebug
if [ "${ENABLE_XDEBUG}" != "1" ]; then
	rm /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
fi

service php-fpm restart
exec /usr/local/bin/docker-entrypoint.sh "$@"
