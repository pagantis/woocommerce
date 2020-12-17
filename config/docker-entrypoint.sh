#!/usr/bin/env bash
set -euo pipefail

is_mysql_command_available() {
	which mysql > /dev/null 2>&1
}

if ! is_mysql_command_available; then
	echo "The MySQL/MariaDB client mysql(1) is not installed."
	exit 1
fi

# usage: file_env VAR [DEFAULT]
#    ie: file_env 'XYZ_DB_PASSWORD' 'example'
# (will allow for "$XYZ_DB_PASSWORD_FILE" to fill in the value of
#  "$XYZ_DB_PASSWORD" from a file, especially for Docker's secrets feature)
file_env() {
	local var="$1"
	local fileVar="${var}_FILE"
	local def="${2:-}"
	if [ "${!var:-}" ] && [ "${!fileVar:-}" ]; then
		echo >&2 "error: both $var and $fileVar are set (but are exclusive)"
		exit 1
	fi
	local val="$def"
	if [ "${!var:-}" ]; then
		val="${!var}"
	elif [ "${!fileVar:-}" ]; then
		val="$(< "${!fileVar}")"
	fi
	export "$var"="$val"
	unset "$fileVar"
}

if [[ "$1" == apache2* ]] || [ "$1" == php-fpm ]; then
	if [ "$(id -u)" = '0' ]; then
		case "$1" in
			apache2*)
				user="${APACHE_RUN_USER:-www-data}"
				group="${APACHE_RUN_GROUP:-www-data}"

				# strip off any '#' symbol ('#1000' is valid syntax for Apache)
				pound='#'
				user="${user#$pound}"
				group="${group#$pound}"
				;;
			*) # php-fpm
				user='www-data'
				group='www-data'
				;;
		esac
	else
		user="$(id -u)"
		group="$(id -g)"
	fi

	if [ ! -e index.php ] && [ ! -e wp-includes/version.php ]; then
		# if the directory exists and WordPress doesn't appear to be installed AND the permissions of it are root:root, let's chown it (likely a Docker-created directory)
		if [ "$(id -u)" = '0' ] && [ "$(stat -c '%u:%g' .)" = '0:0' ]; then
			chown "$user:$group" .
		fi

		echo >&2 "WordPress not found in $PWD - copying now..."
		if [ -n "$(find -mindepth 1 -maxdepth 1 -not -name wp-content)" ]; then
			echo >&2 "WARNING: $PWD is not empty! (copying anyhow)"
		fi
		sourceTarArgs=(
			--create
			--file -
			--directory /var/www/html/wordpress
			--owner "$user" --group "$group"
		)
		targetTarArgs=(
			--extract
			--file -
		)
		if [ "$user" != '0' ]; then
			# avoid "tar: .: Cannot utime: Operation not permitted" and "tar: .: Cannot change mode to rwxr-xr-x: Operation not permitted"
			targetTarArgs+=(--no-overwrite-dir)
		fi
		# loop over "pluggable" content in the source, and if it already exists in the destination, skip it
		# https://github.com/docker-library/wordpress/issues/506 ("wp-content" persisted, "akismet" updated, WordPress container restarted/recreated, "akismet" downgraded)
		for contentDir in /var/www/html/wordpress/wp-content/*/*/; do
			contentDir="${contentDir%/}"
			[ -d "$contentDir" ] || continue
			contentPath="${contentDir#/var/www/html/wordpress/}" # "wp-content/plugins/akismet", etc.
			if [ -d "$PWD/$contentPath" ]; then
				echo >&2 "WARNING: '$PWD/$contentPath' exists! (not copying the WordPress version)"
				sourceTarArgs+=(--exclude "./$contentPath")
			fi
		done
		tar "${sourceTarArgs[@]}" . | tar "${targetTarArgs[@]}"
		echo >&2 "Complete! WordPress has been successfully copied to $PWD"
		if [ ! -e .htaccess ]; then
			# NOTE: The "Indexes" option is disabled in the php:apache base image
			cat > .htaccess <<- 'EOF'
				# BEGIN WordPress
				<IfModule mod_rewrite.c>
				RewriteEngine On
				RewriteBase /
				RewriteRule ^index\.php$ - [L]
				RewriteCond %{REQUEST_FILENAME} !-f
				RewriteCond %{REQUEST_FILENAME} !-d
				RewriteRule . /index.php [L]
				</IfModule>
				# END WordPress
			EOF
			chown "$user:$group" .htaccess
		fi
	fi

	# allow any of these "Authentication Unique Keys and Salts." to be specified via
	# environment variables with a "WORDPRESS_" prefix (ie, "WORDPRESS_AUTH_KEY")
	uniqueEnvs=(
		AUTH_KEY
		SECURE_AUTH_KEY
		LOGGED_IN_KEY
		NONCE_KEY
		AUTH_SALT
		SECURE_AUTH_SALT
		LOGGED_IN_SALT
		NONCE_SALT
	)
	envs=(
		WORDPRESS_DB_HOST
		WORDPRESS_DB_USER
		WORDPRESS_DB_PASSWORD
		WORDPRESS_DB_NAME
		WORDPRESS_DB_CHARSET
		WORDPRESS_DB_COLLATE
		"${uniqueEnvs[@]/#/WORDPRESS_}"
		WORDPRESS_TABLE_PREFIX
		WORDPRESS_DEBUG
		WORDPRESS_CONFIG_EXTRA
	)
	haveConfig=
	for e in "${envs[@]}"; do
		file_env "$e"
		if [ -z "$haveConfig" ] && [ -n "${!e}" ]; then
			haveConfig=1
		fi
	done

	# linking backwards-compatibility
	if [ -n "${!MYSQL_ENV_MYSQL_*}" ]; then
		haveConfig=1
		# host defaults to "mysql" below if unspecified
		: "${WORDPRESS_DB_USER:=${MYSQL_ENV_MYSQL_USER:-root}}"
		if [ "$WORDPRESS_DB_USER" = 'root' ]; then
			: "${WORDPRESS_DB_PASSWORD:=${MYSQL_ENV_MYSQL_ROOT_PASSWORD:-}}"
		else
			: "${WORDPRESS_DB_PASSWORD:=${MYSQL_ENV_MYSQL_PASSWORD:-}}"
		fi
		: "${WORDPRESS_DB_NAME:=${MYSQL_ENV_MYSQL_DATABASE:-}}"
	fi

	# only touch "wp-config.php" if we have environment-supplied configuration values
	if [ "$haveConfig" ]; then
		: "${WORDPRESS_DB_HOST:=mysql}"
		: "${WORDPRESS_DB_USER:=root}"
		: "${WORDPRESS_DB_PASSWORD:=}"
		: "${WORDPRESS_DB_NAME:=wordpress}"
		: "${WORDPRESS_DB_CHARSET:=utf8}"
		: "${WORDPRESS_DB_COLLATE:=}"

		# version 4.4.1 decided to switch to windows line endings, that breaks our seds and awks
		# https://github.com/docker-library/wordpress/issues/116
		# https://github.com/WordPress/WordPress/commit/1acedc542fba2482bab88ec70d4bea4b997a92e4
		sed -ri -e 's/\r$//' wp-config*

		if [ ! -e wp-config.php ]; then
			awk '
				/^\/\*.*stop editing.*\*\/$/ && c == 0 {
					c = 1
					system("cat")
					if (ENVIRON["WORDPRESS_CONFIG_EXTRA"]) {
						print "// WORDPRESS_CONFIG_EXTRA"
						print ENVIRON["WORDPRESS_CONFIG_EXTRA"] "\n"
					}
				}
				{ print }
			' wp-config-sample.php > wp-config.php << 'EOPHP'
// If we're behind a proxy server and using HTTPS, we need to alert WordPress of that fact
// see also http://codex.wordpress.org/Administration_Over_SSL#Using_a_Reverse_Proxy
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
	$_SERVER['HTTPS'] = 'on';
}
EOPHP
			chown "$user:$group" wp-config.php
		elif [ -e wp-config.php ] && [ -n "$WORDPRESS_CONFIG_EXTRA" ] && [[ "$(< wp-config.php)" != *"$WORDPRESS_CONFIG_EXTRA"* ]]; then
			# (if the config file already contains the requested PHP code, don't print a warning)
			echo >&2
			echo >&2 'WARNING: environment variable "WORDPRESS_CONFIG_EXTRA" is set, but "wp-config.php" already exists'
			echo >&2 '  The contents of this variable will _not_ be inserted into the existing "wp-config.php" file.'
			echo >&2 '  (see https://github.com/docker-library/wordpress/issues/333 for more details)'
			echo >&2
		fi

		# see http://stackoverflow.com/a/2705678/433558
		sed_escape_lhs() {
			echo "$@" | sed -e 's/[]\/$*.^|[]/\\&/g'
		}
		sed_escape_rhs() {
			echo "$@" | sed -e 's/[\/&]/\\&/g'
		}
		php_escape() {
			local escaped="$(php -r 'var_export(('"$2"') $argv[1]);' -- "$1")"
			if [ "$2" = 'string' ] && [ "${escaped:0:1}" = "'" ]; then
				escaped="${escaped//$'\n'/"' + \"\\n\" + '"}"
			fi
			echo "$escaped"
		}
		set_config() {
			key="$1"
			value="$2"
			var_type="${3:-string}"
			start="(['\"])$(sed_escape_lhs "$key")\2\s*,"
			end="\);"
			if [ "${key:0:1}" = '$' ]; then
				start="^(\s*)$(sed_escape_lhs "$key")\s*="
				end=";"
			fi
			sed -ri -e "s/($start\s*).*($end)$/\1$(sed_escape_rhs "$(php_escape "$value" "$var_type")")\3/" wp-config.php
		}

		set_config 'DB_HOST' "$WORDPRESS_DB_HOST"
		set_config 'DB_USER' "$WORDPRESS_DB_USER"
		set_config 'DB_PASSWORD' "$WORDPRESS_DB_PASSWORD"
		set_config 'DB_NAME' "$WORDPRESS_DB_NAME"
		set_config 'DB_CHARSET' "$WORDPRESS_DB_CHARSET"
		set_config 'DB_COLLATE' "$WORDPRESS_DB_COLLATE"

		for unique in "${uniqueEnvs[@]}"; do
			uniqVar="WORDPRESS_$unique"
			if [ -n "${!uniqVar}" ]; then
				set_config "$unique" "${!uniqVar}"
			else
				# if not specified, let's generate a random value
				currentVal="$(sed -rn -e "s/define\(\s*(([\'\"])$unique\2\s*,\s*)(['\"])(.*)\3\s*\);/\4/p" wp-config.php)"
				if [ "$currentVal" = 'put your unique phrase here' ]; then
					set_config "$unique" "$(head -c1m /dev/urandom | sha1sum | cut -d' ' -f1)"
				fi
			fi
		done

		if [ "$WORDPRESS_TABLE_PREFIX" ]; then
			set_config '$table_prefix' "$WORDPRESS_TABLE_PREFIX"
		fi

		if [ "$WORDPRESS_DEBUG" ]; then
			set_config 'WP_DEBUG' 1 boolean
		fi

		if ! TERM=dumb php -- << 'EOPHP'; then
<?php
// database might not exist, so let's try creating it (just to be safe)
$stderr = fopen('php://stderr', 'w');
// https://codex.wordpress.org/Editing_wp-config.php#MySQL_Alternate_Port
//   "hostname:port"
// https://codex.wordpress.org/Editing_wp-config.php#MySQL_Sockets_or_Pipes
//   "hostname:unix-socket-path"
list($host, $socket) = explode(':', getenv('WORDPRESS_DB_HOST'), 2);
$port = 0;
if (is_numeric($socket)) {
	$port = (int) $socket;
	$socket = null;
}
$user = getenv('WORDPRESS_DB_USER');
$pass = getenv('WORDPRESS_DB_PASSWORD');
$dbName = getenv('WORDPRESS_DB_NAME');
$maxTries = 10;
do {
	$mysql = new mysqli($host, $user, $pass, '', $port, $socket);
	if ($mysql->connect_error) {
		fwrite($stderr, "\n" . 'MySQL Connection Error: (' . $mysql->connect_errno . ') ' . $mysql->connect_error . "\n");
		--$maxTries;
		if ($maxTries <= 0) {
			exit(1);
		}
		sleep(3);
	}
} while ($mysql->connect_error);
if (!$mysql->query('CREATE DATABASE IF NOT EXISTS `' . $mysql->real_escape_string($dbName) . '`')) {
	fwrite($stderr, "\n" . 'MySQL "CREATE DATABASE" Error: ' . $mysql->error . "\n");
	$mysql->close();
	exit(1);
}
$mysql->close();
EOPHP

			echo >&2
			echo >&2 "WARNING: unable to establish a database connection to '$WORDPRESS_DB_HOST'"
			echo >&2 '  continuing anyways (which might have unexpected results)'
			echo >&2
		fi
	fi

	# now that we're definitely done writing configuration, let's clear out the relevant environment variables (so that stray "phpinfo()" calls don't leak secrets from our code)
	for e in "${envs[@]}"; do
		unset "$e"
	done
fi

cd /var/www/html/ || ls -laF

echo "SOURCING ENV"
source .env

echo "SLEEPING $WORDPRESS_DB_WAIT_TIME "
sleep "${WORDPRESS_DB_WAIT_TIME}"

echo "CHECKING DB CONNECTION"

if ! $(wp core is-installed); then
	declare -p WORDPRESS_URL
	declare -p WORDPRESS_SITE_NAME
	declare -p WORDPRESS_ADMIN_USER
	declare -p WORDPRESS_ADMIN_PASSWORD
	declare -p WORDPRESS_ADMIN_EMAIL

	echo "INSTALLING WORDPRESS"
	wp core install \
		--url=$WORDPRESS_URL \
		--title=$WORDPRESS_SITE_NAME \
		--admin_user=$WORDPRESS_ADMIN_USER \
		--admin_password=$WORDPRESS_ADMIN_PASSWORD \
		--admin_email=$WORDPRESS_ADMIN_EMAIL \
		--skip-email

	wp config set WP_DEBUG_LOG true --raw

fi
if $(wp core is-installed); then

	echo "SETTING WORDPRESS THEME"
	#	wp theme activate twentytwenty
	wp theme install storefront
	declare -p PROJECT_NAME
	declare -p WORDPRESS_VERSION
	declare -p WOOCOMMERCE_VERSION

	if  ! $(wp plugin is-active woocommerce); then
		echo "INSTALLING WOOCOMMERCE"
		wp  plugin install woocommerce --version="${WOOCOMMERCE_VERSION}" --force --activate
	else
		echo "✅ Woocommerce Installed and Activated"
	fi

	echo "Adding basic WooCommerce settings..."
  wp option set woocommerce_store_address "${WPCLI_WOOCOMMERCE_STORE_ADDRESS}"
  wp option set woocommerce_store_address_2 "${WPCLI_WOOCOMMERCE_STORE_ADDRESS_2}"
  wp option set woocommerce_store_city "${WPCLI_WOOCOMMERCE_STORE_CITY}"
  wp option set woocommerce_default_country "${WPCLI_WOOCOMMERCE_DEFAULT_COUNTRY}"
  wp option set woocommerce_store_postalcode "${WPCLI_WOOCOMMERCE_STORE_POSTALCODE}"
  wp option set woocommerce_currency "${WPCLI_WOOCOMMERCE_STORE_CURRENCY}"
  wp option set woocommerce_product_type "${WPCLI_WOOCOMMERCE_STORE_PRODUCT_TYPE}"
  wp option set woocommerce_allow_tracking "${WPCLI_WOOCOMMERCE_STORE_ALLOW_TRACKING}"
	wp option set woocommerce_cart_redirect_after_add "${WPCLI_WOOCOMMERCE_CART_REDIRECT_AFTER_ADD}"
	wp wc --user=admin shipping_zone_method create 0 --method_id=flat_rate

	echo
	echo "SETTING UP WOOCOMMERCE"
	declare -p IMPORT_SAMPLE_PRODUCTS WORDPRESS_LOCALE DELETE_WC_ADMIN_NOTICE GENERATE_PAGES
	if [ "$IMPORT_SAMPLE_PRODUCTS" ]; then
		echo "IMPORTING SAMPLE PRODUCTS"
		wp plugin install wordpress-importer --activate
		wp import wp-content/plugins/woocommerce/sample-data/sample_products.xml --authors=skip
	fi
	if [ "$WORDPRESS_LOCALE" != 'en_US' ]; then
		echo "INSTALLING LOCALE"
		wp language core install $WORDPRESS_LOCALE --activate --debug
	fi

	if  [ "$DELETE_WC_ADMIN_NOTICE" == 'true' ]; then
		echo "DELETING WOOCOMMERCE ADMIN NOTICES"
		wp option delete woocommerce_admin_notices
		wp user meta add 1 dismissed_install_notice true
		wp option add storefront_nux_dismissed true

	fi

	if [ "$GENERATE_PAGES" == 'true' ]; then
		echo "GENERATING PAGES + SETTING DEFAULT ONE"
		CART_ID_PAGE=$(wp post create --post_type=page --user=admin --post_title=Carro   --post_status=publish --post_content=[woocommerce_cart] --porcelain)
		CHECKOUT_ID_PAGE=$(wp post create --post_type=page --user=admin --post_title=Checkout --post_status=publish --post_content=[woocommerce_checkout] --porcelain)
		SHOP_ID_PAGE=$(wp post create --post_type=page --user=admin --post_title=Shop --post_status=publish --post_content="<a id='goToCart' class='button' href='http://$WORDPRESS_URL/?page_id=$CART_ID_PAGE'>Ir al carro</a></p>" --porcelain)
		wp option update woocommerce_cart_page_id $CART_ID_PAGE
		wp option update woocommerce_checkout_page_id $CHECKOUT_ID_PAGE
		wp option update woocommerce_shop_page_id $SHOP_ID_PAGE
		wp option update show_on_front page
		wp option update page_on_front $SHOP_ID_PAGE
	fi

	echo "***Installing Wordpress plugins***"

	if [ "$INSTALL_AFTERPAY" ]; then
		wp plugin install afterpay-gateway-for-woocommerce --activate
	fi

	if [ "$INSTALL_CLEARPAY" ]; then
		wp plugin install clearpay-gateway-for-woocommerce --activate
	fi

	if [ "$INSTALL_DEBUG_BAR" ]; then
		wp plugin install debug-bar --activate
	fi

	if [ "$INSTALL_QUERY_MONITOR" ]; then
		wp plugin install query-monitor --activate
	fi
	wp plugin uninstall akismet
	wp plugin uninstall hello

	wp plugin activate ${PROJECT_NAME:-clearpay-gateway-europe-for-woocommerce}

	echo " (*・‿・)ノ⌒*:･ﾟ✧ 🎉"
	echo " Build of Woocommerce complete: $(wp option get siteurl)/wp-admin/"

else
	wp core is-installed
	echo
	wp cli info
	echo
	wp cli param-dump
	echo
	exit 1
fi

exec "$@"
