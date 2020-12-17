#!/usr/bin/env bash

while true; do
    read -p "Do you wish to run dev or test [test|dev]? " devtest
    case $devtest in
        [dev]* ) container="woocommerce-dev";test=false; break;;
        [test]* ) container="woocommerce-test";test=true; break;;
        * ) echo "Please answer dev or test.";;
    esac
done


# Prepare environment and build package
docker-compose down --volumes --remove-orphans
docker-compose up -d --build ${container}
numberOfExitedContainers=$(docker ps -aq --no-trunc -f status=exited | wc -l);
if [ "${numberOfExitedContainers}" -gt 1 ]; then
    docker ps -aq --no-trunc -f status=exited | xargs docker rm;
fi

docker-compose exec ${container} curl -s https://getcomposer.org/installer | php
docker-compose exec ${container} ./composer.phar install

containerPort=$(docker container port ${container})
PORT=$(sed  -e 's/.*://' <<< $containerPort)
#echo "Build of Woocommerce complete: http://'${container}'.docker'${PORT}/wp-admin"
# Time to boot and install woocommerce
ncHost="${container}.docker"

sleep 30
#until nc -z -v -w30 "${ncHost}" "${PORT}";
# 	do
#		echo "Waiting for database connection..."
#		sleep 1
#	done
#set -e

export WOOCOMMERCE_TEST_ENV=${devtest}
while true; do
    read -p "Do you want to run full tests battery or only configure the module [full/setup/none]? " tests
    case $tests in
        [full]* ) break;;
        [setup]* ) break;;
        [none]* ) break;;
        * ) echo "Please answer full, setup or none."; exit;;
    esac
done

if [ ! -z "$tests" ] && [ "$tests" != "none" ];
then
    vendor/bin/phpunit --group woocommerce3-basic

    #Only for TEST environment. DEV environment is already installed
    if [ $devtest = "test" ];
    then
        vendor/bin/phpunit --group woocommerce3-install
    else
        export WOOCOMMERCE_LANG=EN #in dev mode, woocommerce is installed in english
        vendor/bin/phpunit --group woocommerce3-setup
    fi

    if [ $tests = "full" ];
    then
        vendor/bin/phpunit --group woocommerce3-buy
    fi
fi

containerPort=$(docker container port ${container})
PORT=$(sed  -e 's/.*://' <<< $containerPort)
echo 'Build of Woocommerce complete: http://'${container}'.docker:'${PORT}/wp-admin