<?php

namespace Test\Selenium\Install;

use Facebook\WebDriver\Exception\StaleElementReferenceException;
use Facebook\WebDriver\Remote\LocalFileDetector;
use Facebook\WebDriver\WebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Test\Selenium\PaylaterWoocommerceTest;

/**
 * Class PaylaterWc3InstallTest
 * @package Test\Selenium\install
 *
 * @group woocommerce3-install
 */
class PaylaterWc3InstallTest extends PaylaterWoocommerceTest
{
    /**
     * testInstallPaylaterInPrestashop15
     */
    public function testInstallAndConfigurePaylaterInWoocommerce3()
    {
        $this->loginToBackOffice();
        $this->uploadPaylaterModule();
        $this->configureModule();
        $this->quit();
    }

    /**
     * Login to the backoffice
     */
    public function loginToBackOffice()
    {
        $this->webDriver->get(self::WC3URL.self::BACKOFFICE_FOLDER);
        $emailElementSearch = WebDriverBy::id('user_login');
        $condition = WebDriverExpectedCondition::visibilityOfElementLocated($emailElementSearch);
        $this->waitUntil($condition);
        $this->findById('user_login')->clear()->sendKeys($this->configuration['username']);
        $this->findById('user_pass')->clear()->sendKeys($this->configuration['password']);
        $this->findById('wp-submit')->click();

        $emailElementSearch = WebDriverBy::id('adminmenumain');
        $condition = WebDriverExpectedCondition::visibilityOfElementLocated($emailElementSearch);
        $this->waitUntil($condition);
        $this->assertTrue((bool) $condition, "Login OK");
    }

    /**
     * Install PaylaterModule
     */
    public function uploadPaylaterModule()
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
        $fileInput->sendKeys(__DIR__.'/../../../paylater.zip');
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
     * Configure paylater module
     */
    public function configureModule()
    {
        $this->findByLinkText('WooCommerce')->click();
        $this->findByLinkText('Ajustes')->click();
        $this->findByLinkText('Finalizar compra')->click();
        $this->findByLinkText('Paga Más Tarde')->click();

        $verify = WebDriverBy::id('woocommerce_paylater_public_key');
        $condition = WebDriverExpectedCondition::visibilityOfElementLocated($verify);
        $this->waitUntil($condition);
        $this->assertTrue((bool) $condition, "PR5");

        $verify = WebDriverBy::id('woocommerce_paylater_secret_key');
        $condition = WebDriverExpectedCondition::visibilityOfElementLocated($verify);
        $this->waitUntil($condition);
        $this->assertTrue((bool) $condition, "PR5");

        $this->findById('woocommerce_paylater_public_key')->clear()->sendKeys($this->configuration['publicKey']);
        $this->findById('woocommerce_paylater_secret_key')->clear()->sendKeys($this->configuration['secretKey']);
        $this->findById('woocommerce_paylater_extra_title')->clear()->sendKeys($this->configuration['methodName']);
        $this->findById('woocommerce_paylater_enabled')->click();
        $this->webDriver->findElement(WebDriverBy::id('woocommerce_paylater_simulator_product'))
                        ->findElement(WebDriverBy::cssSelector("option[value='".$this->configuration['defaultSimulatorOpt']."']"))
                        ->click();
        $this->webDriver->findElement(WebDriverBy::id('woocommerce_paylater_simulator_checkout'))
                        ->findElement(WebDriverBy::cssSelector("option[value='".$this->configuration['defaultSimulatorOpt']."']"))
                        ->click();
        $this->findById('mainform')->submit();

        $verify = WebDriverBy::className('updated');
        $condition = WebDriverExpectedCondition::visibilityOfElementLocated($verify);
        $this->waitUntil($condition);

        $validatorSearch = WebDriverBy::className('updated');
        $actualString = $this->webDriver->findElement($validatorSearch)->getText();
        $compareString = (strstr($actualString, 'Tus ajustes se han guardado')) === false ? false : true;
        $this->assertTrue($compareString, $actualString);

        $verify = WebDriverBy::id('woocommerce_paylater_enabled');
        $condition = WebDriverExpectedCondition::visibilityOfElementLocated($verify);
        $this->waitUntil($condition);
        $this->assertTrue((bool) $condition, "PR7");

        $enabledModule = $this->findById('woocommerce_paylater_enabled')->isSelected();
        $this->assertTrue($enabledModule, 'PR6');

        $verify = WebDriverBy::id('woocommerce_paylater_iframe');
        $condition = WebDriverExpectedCondition::visibilityOfElementLocated($verify);
        $this->waitUntil($condition);
        $this->assertTrue((bool) $condition, "PR8");

        $verify = WebDriverBy::id('woocommerce_paylater_simulator_product');
        $condition = WebDriverExpectedCondition::visibilityOfElementLocated($verify);
        $this->waitUntil($condition);
        $this->assertTrue((bool) $condition, "PR9");

        $verify = WebDriverBy::id('woocommerce_paylater_simulator_checkout');
        $condition = WebDriverExpectedCondition::visibilityOfElementLocated($verify);
        $this->waitUntil($condition);
        $this->assertTrue((bool) $condition, "PR10");

        $verify = WebDriverBy::id('woocommerce_paylater_min_installments');
        $condition = WebDriverExpectedCondition::visibilityOfElementLocated($verify);
        $this->waitUntil($condition);
        $this->assertTrue((bool) $condition, "PR11");
        $simulatorElement = $this->findById('woocommerce_paylater_min_installments');
        $minInstallments = $simulatorElement->getAttribute('value');
        $this->assertEquals($minInstallments, $this->configuration['defaultMinIns'], "PR11");

        $verify = WebDriverBy::id('woocommerce_paylater_min_installments');
        $condition = WebDriverExpectedCondition::visibilityOfElementLocated($verify);
        $this->waitUntil($condition);
        $this->assertTrue((bool) $condition, "PR11");
        $simulatorElement = $this->findById('woocommerce_paylater_max_installments');
        $maxInstallments = $simulatorElement->getAttribute('value');
        $this->assertEquals($maxInstallments, $this->configuration['defaultMaxIns'], "PR11");

        $verify = WebDriverBy::id('woocommerce_paylater_min_amount');
        $condition = WebDriverExpectedCondition::visibilityOfElementLocated($verify);
        $this->waitUntil($condition);
        $this->assertTrue((bool) $condition, "PR12");

        $verify = WebDriverBy::id('woocommerce_paylater_extra_title');
        $condition = WebDriverExpectedCondition::visibilityOfElementLocated($verify);
        $this->waitUntil($condition);
        $this->assertTrue((bool) $condition, "PR14");

        $verify = WebDriverBy::id('woocommerce_paylater_ok_url');
        $condition = WebDriverExpectedCondition::visibilityOfElementLocated($verify);
        $this->waitUntil($condition);
        $this->assertTrue((bool) $condition, "PR15");

        $verify = WebDriverBy::id('woocommerce_paylater_ko_url');
        $condition = WebDriverExpectedCondition::visibilityOfElementLocated($verify);
        $this->waitUntil($condition);
        $this->assertTrue((bool) $condition, "PR15");

        $verify = WebDriverBy::id('paylater_logo');
        $condition = WebDriverExpectedCondition::visibilityOfElementLocated($verify);
        $this->waitUntil($condition);
        $this->assertTrue((bool) $condition, "PR17");
    }
}