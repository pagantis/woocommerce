#!/usr/bin/env bash

set -e
# Output colorized strings
# https://linux.101hacks.com/ps1-examples/prompt-color-using-tput/
# Color codes:
# 0 - black | 1 - red | 2 - green | 3 - yellow | 4 - blue | 5 - magenta | 6 - cyan | 7 - white
output() {
    local INDENT="  "
    echo "$(tput setb 7)$(tput setaf "$1")$INDENT$2$(tput sgr0)"
}

function check_requirements() {
    local INDENT="  "
    if [ "${BASH_VERSINFO:-0}" -lt 4 ]; then
        output 1 "$INDENT [ERROR] Unmet requirements: Bash 4"
        output 1 "$INDENT Your Bash version is $BASH_VERSION"
        output 1 "$INDENT Exiting with error code 1" 1>&2
        exit 1
    fi

    if ! [ -x "$(command -v curl)" ]; then
        output 1 "$INDENT Error: curl is not installed." >&2
        exit 1
    fi
    if ! [ -x "$(command -v docker)" ]; then
        output 1 "$INDENT Error: docker is not installed." >&2
        exit 1
    fi

    if ! [ -x "$(command -v docker-compose)" ]; then
        output 1 "$INDENT Error: docker-compose is not installed." >&2
        exit 1
    fi
}

function type_of_var() {

    local type_signature=$(declare -p "$1" 2 > /dev/null)

    if [[ "$type_signature" =~ "declare --" ]]; then
        printf "string"
    elif [[ "$type_signature" =~ "declare -a" ]]; then
        printf "array"
    elif [[ "$type_signature" =~ "declare -A" ]]; then
        printf "map"
    else
        printf "none"
    fi

}

check_requirements
shopt -s nocasematch


if [[ "$@" -eq "f" ]];
then
    while [[ "$#" -eq 1 ]];
    do
        container="woocommerce-dev";
        test=false;
        fast_mode=true;
        output 6 "FAST MODE enabled"
        break;
    done
fi

if [ -z "${fast_mode}" ];
then
    while true; do
        read -p "Do you wish to run dev or test [test|dev|f]? " devtest
        case $devtest in
            [dev]*) container="woocommerce-dev";
            test=false;
            break ;;
            [f]*) container="woocommerce-dev";
            test=false;
            fast_mode=true;
            output 6 "FAST MODE enabled"
            break ;;
            [test]*) container="woocommerce-test";
            test=true;
            break ;;
            *) output 5 "Please answer dev or test." ;;
        esac
    done
    while true; do
        read -p "You have chosen to start ${container^^}, are you sure [y/n]? " yn
        case $yn in
            [Yy]*) break ;;
            [Nn]*) exit ;;
            *) output 5 "Please answer yes or no." ;;
        esac
    done
fi
# Prepare environment and build package
echo
output 6 "Removing previously built 🐳️containers"
docker-compose down -v

echo
output 6 "Starting to build 🐳️containers"
docker-compose up -d --build ${container}
#containerLogPath=$(docker inspect --format='{{.LogPath}}' ${container})
#touch ./docker/logs/${container}-logs.log
#docker-compose logs  --raw --timestamps --no-color ${container} |& tee ./docker/logs/${container}-logs.log

if [ -z "${fast_mode}" ];
then
    echo
    output 6 "Starting selenium container"
    echo
    docker-compose up -d selenium
    echo
    output 6 "npm i"
    echo
    npm install
    echo
    output 6 "ZIPPING PLUGIN"
    echo
    node_modules/.bin/grunt
fi

echo
output 6 "Installing composer in ${container^^}"
docker-compose exec ${container} curl -s https://getcomposer.org/installer | php

echo
output 6 "Running composer install in ${container^^}"
echo
docker-compose exec ${container} ./composer.phar install --no-suggest

SLEEP_TIME=40
echo
output 6 "SLEEPING ${SLEEP_TIME} SECONDS TO GIVE TIME TO "${container^^}" TO SPIN UP"
sleep ${SLEEP_TIME}
echo
output 6 "CHECKING container STATUS"
echo
CONTAINER_PORT=$(docker container port ${container})
PORT=$(sed -e 's/.*://' <<< ${CONTAINER_PORT})

isContainerPaused=$(docker inspect --format='{{.State.Paused}}' ${container})
isContainerDead=$(docker inspect --format='{{.State.Dead}}' ${container})
isContainerRunning=$(docker inspect --format "{{.State.Running}}" ${container} 2> /dev/null)

#set -vx
RETURN=1
while [[ "$RETURN" -ne "0" ]]; do
    set +e
    httpResponse=$(curl -sf -o /dev/null -w '%{response_code}' "http:/${container}.docker:${PORT}/wp-admin/admin.php?page=wc-status")
    if [ "$httpResponse" -ne "302" ]; then
        SLEEP_CYCLE=5
        output 3 "Looks like the WooCommerce Install is not finished yet.. waiting ${SLEEP_CYCLE} more seconds"
        sleep ${SLEEP_CYCLE}
    else
        echo
        output 2 "(*・‿・)ノ⌒*:･ﾟ✧ 🎉"
        output 2 "Build of Woocommerce complete -  Visit whichever url you prefer"
        output 2 "- http://${container}.docker:${PORT}/wp-admin/"
        output 2 "- http://${container}.docker:${PORT}/wp-admin/admin.php?page=wc-settings&tab=checkout&section=pagantis"
        output 2 "- http://${container}.docker:${PORT}/wp-admin/admin.php?page=wc-status"
        RETURN=0
    fi
    set -e
    CONTAINER_STATUS=$(docker inspect --format='{{.State.Status}}' ${container})
    CONTAINER_PID=$(docker inspect --format='{{.State.Pid}}' ${container})
    CONTAINER_ERROR_MESSAGE=$(docker inspect --format='{{.State.Error}}' ${container})
    if [ "$CONTAINER_PID" == "0" ] && [ "$isContainerRunning" != 'true' ];
    then
        echo
        output 1 "$(date +'%Y-%m-%dT%H:%M:%S%z')]"
        output 1 "${container} BUILD FAILED"
        output 1 "CURRENT STATUS : ${CONTAINER_STATUS}"
        output 1 "CONTAINER_EXIT_CODE : ${CONTAINER_PID}"
        output 1 "ERROR MESSAGE : ${CONTAINER_ERROR_MESSAGE}"
        exit 1
    fi

done
if [ -z "${fast_mode}" ];
then
    export WOOCOMMERCE_TEST_ENV=${devtest}
    while true; do
        read -p "Do you want to run full tests battery or only configure the module [full/configure/none]? " tests
        case $tests in
            [full]*) break ;;
            [configure]*) break ;;
            [none]*) break ;;
            *) output 5 "Please answer full, configure or none.";
            exit ;;
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
            vendor/bin/phpunit --group woocommerce3-configure
        fi

        if [ $tests = "full" ];
        then
            vendor/bin/phpunit --group woocommerce3-buy
        fi
    fi

fi
