<?php

namespace Test\Selenium\Buy;

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;

/**
 * Class BuyUnregisteredWc3Test
 * @package Test\Selenium\Buy
 *
 * @group woocommerce3-buy-unregistered
 */
class BuyUnregisteredWc3Test extends AbstractBuy
{
    /**
     * Test to buy
     */
    public function testBuy()
    {
        $this->prepareProductAndCheckout();
        $this->prepareCheckout();
        $this->register();
        $this->makeCheckoutAndPmt();
    }

    /**
     * Register customer
     */
    public function register()
    {
        $checkboxSelector = WebDriverBy::id('createaccount');
        $condition = WebDriverExpectedCondition::elementToBeClickable($checkboxSelector);
        $this->waitUntil($condition);

        $this->webDriver->executeScript("document.getElementById('createaccount').click()");

        $validatorSearch = WebDriverBy::id('account_password');
        $condition = WebDriverExpectedCondition::visibilityOfElementLocated($validatorSearch);
        $this->waitUntil($condition);

        $this->findById('account_password')->clear()->sendKeys($this->configuration['password']);
    }
}
