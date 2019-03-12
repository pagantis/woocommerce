<?php

namespace Test\Selenium\Buy;

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;

/**
 * Class BuyUnregisteredWc3Test
 * @package Test\Selenium\Buy
 *
 * @group woocommerce3-buy
 */
class BuyWc3Test extends AbstractBuy
{
    /**
     * Test to buy
     */
    public function testBuy()
    {
        $this->prepareProductAndCheckout();
        $this->prepareCheckout();
        $this->makeCheckoutAndPmt();
        $this->makeValidation();
        $this->quit();
    }
}
