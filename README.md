# Woocommerce Module <img src="https://pagamastarde.com/img/icons/logo.svg" width="100" align="right">

[![Build Status](https://travis-ci.org/PagaMasTarde/WooCommerce.svg?branch=master)](https://travis-ci.org/PagaMasTarde/WooCommerce)
[![Latest Stable Version](https://poser.pugx.org/pagamastarde/woocommerce/v/stable)](https://packagist.org/packages/pagamastarde/woocommerce)
[![composer.lock](https://poser.pugx.org/pagamastarde/woocommerce/composerlock)](https://packagist.org/packages/pagamastarde/woocommerce)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/PagaMasTarde/woocommerce/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/PagaMasTarde/woocommerce/?branch=master)

## Instrucciones de Instalación
1. Crea tu cuenta en pagamastarde.com si aún no la tienes [desde aquí](https://bo.pagamastarde.com/users/sign_up)
2. Descarga el módulo de [aquí](https://github.com/pagamastarde/woocommerce/releases/latest)
3. Instala el módulo en tu woocommerce
4. Configuralo con la información de tu cuenta que encontrarás en [el panel de gestión de Paga+Tarde](https://bo.pagamastarde.com/shop). Ten en cuenta que para hacer cobros reales deberás activar tu cuenta de Paga+Tarde.

## Modo real y modo de pruebas

Tanto el módulo como Paga+Tarde tienen funcionamiento en real y en modo de pruebas independientes. Debes introducir las credenciales correspondientes del entorno que desees usar.

### Soporte

Si tienes alguna duda o pregunta no tienes más que escribirnos un email a [welcome@pagamastarde.com]

## Development Instructions:

To develop or improve this module you need to have installed in your environment
    * Composer
    
To make the module operative you need to download the dependencies, 

    composer install
    
Once both dependencies are ready you can generate the specific module files using

    grunt default
    
Grunt will compress the CSS and the JS and generate a zip file with the necessary files to push
to the market.

### Testing and Improvements

* Doing some phpUnit testing on the module.
* Improving the code structure to make it more human.
