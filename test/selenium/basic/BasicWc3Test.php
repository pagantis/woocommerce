<?php

namespace Test\Selenium\Basic;

use Facebook\WebDriver\WebDriverExpectedCondition;
use Test\Selenium\PagantisWoocommerceTest;

/**
 * Class BasicWc3Test
 * @package Test\Selenium\Basic
 *
 * @group woocommerce3-basic
 */
class BasicWc3Test extends PagantisWoocommerceTest
{
    /**
     * Const title
     */
    const TITLE = 'WooCommerce';

    /**
     * testTitleWoocommerce3
     */
    public function testTitleWoocommerce3()
    {
        $this->webDriver->get($this->woocommerceUrl);
        $condition = WebDriverExpectedCondition::titleContains(self::TITLE);
        $this->webDriver->wait()->until($condition);
        $this->assertTrue((bool) $condition);
        $this->quit();
    }

    /**
     * testBackOfficeTitleWoocommerce3
     */
    public function testBackOfficeTitleWoocommerce3()
    {
        $this->webDriver->get($this->woocommerceUrl.self::BACKOFFICE_FOLDER);
        $condition = WebDriverExpectedCondition::titleContains(self::TITLE);
        $this->webDriver->wait()->until($condition);
        $this->assertTrue((bool) $condition);
        $this->quit();
    }
}
