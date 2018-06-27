#!/bin/bash

# Prepare environment and build package
docker-compose down
docker-compose up -d --build woocommerce-test
docker-compose up -d selenium
npm install
node_modules/.bin/grunt
docker-compose exec woocommerce-test curl -s https://getcomposer.org/installer | php
docker-compose exec woocommerce-test ./composer.phar install

# Time to boot and install woocommerce
sleep 30
set -e

# Run test
docker-compose exec woocommerce-test vendor/bin/phpunit --group woocommerce3-basic
docker-compose exec woocommerce-test vendor/bin/phpunit --group woocommerce3-install
docker-compose exec woocommerce-test vendor/bin/phpunit --group woocommerce3-buy-unregistered
docker-compose exec woocommerce-test vendor/bin/phpunit --group woocommerce3-buy-registered


