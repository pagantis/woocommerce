<?php

namespace Test\Selenium\Basic;

use Facebook\WebDriver\WebDriverExpectedCondition;
use Test\Selenium\PaylaterWoocommerceTest;

/**
 * Class BasicWc3Test
 * @package Test\Selenium\Basic
 *
 * @group woocommerce3-basic
 */
class BasicWc3Test extends PaylaterWoocommerceTest
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
        $this->webDriver->get(self::WC3URL);
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
        $this->webDriver->get(self::WC3URL.self::BACKOFFICE_FOLDER);
        $condition = WebDriverExpectedCondition::titleContains(self::TITLE);
        $this->webDriver->wait()->until($condition);
        $this->assertTrue((bool) $condition);
        $this->quit();
    }
}