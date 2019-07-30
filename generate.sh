#!/bin/bash

while true; do
    read -p "Do you wish to run dev or test [test|dev]? " devtest
    case $devtest in
        [dev]* ) container="woocommerce-dev";test=false; break;;
        [test]* ) container="woocommerce-test";test=true; break;;
        * ) echo "Please answer dev or test.";;
    esac
done
while true; do
    read -p "You have chosen to start ${container}, are you sure [y/n]? " yn
    case $yn in
        [Yy]* ) break;;
        [Nn]* ) exit;;
        * ) echo "Please answer yes or no.";;
    esac
done

# Prepare environment and build package
docker-compose down
docker-compose up -d --build ${container}
docker-compose up -d selenium
npm install
node_modules/.bin/grunt
docker-compose exec ${container} curl -s https://getcomposer.org/installer | php
docker-compose exec ${container} ./composer.phar install

# Time to boot and install woocommerce
sleep 80
set -e

export WOOCOMMERCE_TEST_ENV=${devtest}
while true; do
    read -p "Do you want to run full tests battery or only configure the module [full/configure]? " tests
    case $tests in
        [full]* ) break;;
        [configure]* ) break;;
        * ) echo "Please answer full or configure."; exit;;
    esac
done

if [ ! -z "$tests" ];
then
    vendor/bin/phpunit --group woocommerce3-basic

    #Only for TEST environment. DEV environment is already installed
    if [ $devtest = "test" ];
    then
        vendor/bin/phpunit --group woocommerce3-install
    else
        export WOOCOMMERCE_LANG=EN #in dev mode, woocommerce is installed in english
        vendor/bin/phpunit --group woocommerce3-configure
    fi

    if [ $tests = "full" ];
    then
        vendor/bin/phpunit --group woocommerce3-buy
    fi
fi
