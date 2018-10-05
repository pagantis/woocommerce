<?php

namespace Test\Selenium\Buy;

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;

/**
 * Class BuyUnregisteredWc3Test
 * @package Test\Selenium\Buy
 *
 * @group woocommerce3-buy-registered
 */
class BuyRegisteredWc3Test extends AbstractBuy
{
    /**
     * Test to buy
     */
    public function testBuy()
    {
        $this->prepareProductAndCheckout();
        $this->login();
        $this->prepareCheckout();
        $this->makeCheckoutAndPmt();
        $this->makeValidation();
        $this->quit();
    }

    /**
     * Login customer
     */
    public function login()
    {
        $this->findByLinkText('Haz clic aquí para acceder')->click();
        $checkboxSelector = WebDriverBy::id('username');
        $condition = WebDriverExpectedCondition::elementToBeClickable($checkboxSelector);
        $this->waitUntil($condition);

        $this->findById('username')->clear()->sendKeys($this->configuration['email']);
        $this->findById('password')->clear()->sendKeys($this->configuration['password']);
        $this->findByName('login')->click();

        $loginElements = $this->webDriver->findElements(WebDriverBy::linkText('Haz clic aquí para acceder'));
        $this->assertEquals(0, count($loginElements), "USER NOT LOGGED ON");

        $condition = WebDriverExpectedCondition::titleContains(self::CHECKOUT_TITLE);
        $this->webDriver->wait()->until($condition);
        $this->assertTrue((bool) $condition);
    }
}
