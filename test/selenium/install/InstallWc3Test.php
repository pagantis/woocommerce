<?php

namespace Test\Selenium\Install;

use Facebook\WebDriver\Remote\LocalFileDetector;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Test\Selenium\PagantisWoocommerceTest;

/**
 * Class PagantisWc3InstallTest
 * @package Test\Selenium\install
 *
 * @group woocommerce3-install
 */
class PagantisWc3InstallTest extends PagantisWoocommerceTest
{
    /**
     * testInstallPagantisInPrestashop15
     */
    public function testInstallAndConfigurePagantisInWoocommerce3()
    {
        $this->loginToBackOffice();
        $this->uploadPagantisModule();
        $this->configureModule();
        $this->quit();
    }

    /**
     * Login to the backoffice
     */
    public function loginToBackOffice()
    {
        $this->webDriver->get(self::WC3URL.self::BACKOFFICE_FOLDER);
        sleep(2);

        $emailElementSearch = WebDriverBy::id('user_login');
        $condition = WebDriverExpectedCondition::visibilityOfElementLocated($emailElementSearch);
        $this->waitUntil($condition);

        $this->findById('user_login')->clear()->sendKeys($this->configuration['username']);
        $this->findById('user_pass')->clear()->sendKeys($this->configuration['password']);

        $submitElementSearch = WebDriverBy::id('wp-submit');
        $condition = WebDriverExpectedCondition::visibilityOfElementLocated($submitElementSearch);
        $this->waitUntil($condition);
        $this->assertTrue((bool) $condition, "button OK");
        $this->findById('loginform')->submit();

        $loginElements = $this->webDriver->findElements(WebDriverBy::id('login_error'));
        $errorMessage = (count($loginElements)) ? ($this->findById('login_error')->getText()) : '';
        $this->assertEquals(0, count($loginElements), "Login KO - $errorMessage");

        $emailElementSearch = WebDriverBy::id('adminmenumain');
        $condition = WebDriverExpectedCondition::visibilityOfElementLocated($emailElementSearch);
        $this->waitUntil($condition);
        $this->assertTrue((bool) $condition, "Login OK");
    }

    /**
     * Install PagantisModule
     */
    public function uploadPagantisModule()
    {
        $this->findByLinkText('Plugins')->click();

        //Se abre la pagina de plugins
        $validatorSubmenu = WebDriverBy::className('page-title-action');
        $condition = WebDriverExpectedCondition::visibilityOfElementLocated($validatorSubmenu);
        $this->waitUntil($condition);
        $this->assertTrue((bool) $condition);

        //Se abre la pagina para instalar
        $this->findByLinkText('Añadir nuevo')->click();
        $validatorUpload = WebDriverBy::className('upload');
        $condition = WebDriverExpectedCondition::visibilityOfElementLocated($validatorUpload);
        $this->waitUntil($condition);
        $this->assertTrue((bool) $condition);

        $this->findByLinkText('Subir plugin')->click();
        $moduleInstallBlock = WebDriverBy::className('wp-upload-form');
        $fileInputSearch = $moduleInstallBlock->name('pluginzip');
        $fileInput = $this->webDriver->findElement($fileInputSearch);
        $fileInput->setFileDetector(new LocalFileDetector());
        $fileInput->sendKeys(__DIR__.'/../../../pagantis.zip');
        $fileInput->submit();

        //Mensaje con el resultado de la instalación
        $validatorSearch = WebDriverBy::className('wrap');
        $condition = WebDriverExpectedCondition::visibilityOfElementLocated($validatorSearch);
        $this->waitUntil($condition);
        $this->assertTrue((bool) $condition, "Don't show result message after upload");

        //Comprobamos que el mensaje pone que ha sido instalado con éxito
        $actualString = $this->webDriver->findElement($validatorSearch)->getText();
        $compareString = (strpos($actualString, "Plugin instalado con éxito.")) === false ? false : true;
        $this->assertTrue($compareString, "PR1-PR4");

        $this->findByLinkText('Activar plugin')->click();
    }

    /**
     * Configure pagantis module
     */
    public function configureModule()
    {
        $this->findByLinkText('WooCommerce')->click();
        $this->findByLinkText('Ajustes')->click();
        $this->findByLinkText('Pagos')->click();
        $this->findByLinkText('Pagantis')->click();

        $verify = WebDriverBy::id('woocommerce_pagantis_pagantis_public_key');
        $condition = WebDriverExpectedCondition::visibilityOfElementLocated($verify);
        $this->waitUntil($condition);
        $this->assertTrue((bool) $condition, "PR5");

        $verify = WebDriverBy::id('woocommerce_pagantis_pagantis_private_key');
        $condition = WebDriverExpectedCondition::visibilityOfElementLocated($verify);
        $this->waitUntil($condition);
        $this->assertTrue((bool) $condition, "PR5");

        $verify = WebDriverBy::id('woocommerce_pagantis_enabled');
        $condition = WebDriverExpectedCondition::visibilityOfElementLocated($verify);
        $this->waitUntil($condition);
        $this->assertTrue((bool) $condition, "PR7");

        $enabledModule = $this->findById('woocommerce_pagantis_enabled')->click();

        $this->findById('woocommerce_pagantis_pagantis_public_key')->sendKeys($this->configuration['publicKey']);
        $this->findById('woocommerce_pagantis_pagantis_private_key')->sendKeys($this->configuration['secretKey']);
        $cssSelector = "form#mainform > p.submit > button.button-primary.woocommerce-save-button";
        $menuSearch = WebDriverBy::cssSelector($cssSelector);
        $menuElement = $this->webDriver->findElement($menuSearch);
        $menuElement->click();

        $verify = WebDriverBy::className('updated');
        $condition = WebDriverExpectedCondition::visibilityOfElementLocated($verify);
        $this->waitUntil($condition);

        $validatorSearch = WebDriverBy::className('updated');
        $actualString = $this->webDriver->findElement($validatorSearch)->getText();
        $compareString = (strstr($actualString, 'Tus ajustes se han guardado')) === false ? false : true;
        $this->assertTrue($compareString, $actualString);

        $verify = WebDriverBy::id('woocommerce_pagantis_enabled');
        $condition = WebDriverExpectedCondition::visibilityOfElementLocated($verify);
        $this->waitUntil($condition);
        $this->assertTrue((bool) $condition, "PR7");

        $verify = WebDriverBy::id('woocommerce_pagantis_simulator');
        $condition = WebDriverExpectedCondition::visibilityOfElementLocated($verify);
        $this->waitUntil($condition);
        $this->assertTrue((bool) $condition, "PR9");
    }
}
