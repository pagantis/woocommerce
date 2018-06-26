#!/bin/bash

# Prepare environment and build package
docker-compose down
docker-compose up -d --build woocommerce-test
docker-compose up -d selenium
npm install
composer install
node_modules/.bin/grunt

# Time to boot and install woocommerce
sleep 30
set -e

# Run test
composer install && vendor/bin/phpunit --group woocommerce3-basic
composer install && vendor/bin/phpunit --group woocommerce3-install
composer install && vendor/bin/phpunit --group woocommerce3-buy-unregistered
composer install && vendor/bin/phpunit --group woocommerce3-buy-registered
