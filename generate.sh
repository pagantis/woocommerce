#!/bin/bash

# Prepare environment and build package
#docker-compose pull
docker-compose down
docker-compose up -d --build woocommerce-test
composer install
grunt default

# Time to boot and install woocommerce
sleep 30
set -e

# Run test
vendor/bin/phpunit --group woocommerce3-basic
vendor/bin/phpunit --group woocommerce3-install
vendor/bin/phpunit --group woocommerce3-buy-unregistered
vendor/bin/phpunit --group woocommerce3-buy-registered
