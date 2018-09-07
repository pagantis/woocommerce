<?php

namespace Test\Selenium\Buy;

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Test\Selenium\PaylaterWoocommerceTest;
use PagaMasTarde\SeleniumFormUtils\SeleniumHelper;

/**
 * Class AbstractBuy
 * @package Test\Selenium\Buy
 *
 */
abstract class AbstractBuy extends PaylaterWoocommerceTest
{
    /**
     * Product name
     */
    //const PRODUCT_NAME = 'Logo Collection'; //To check grouped products
    const PRODUCT_NAME = 'Album';

    /**
     * Product quantity
     */
    const PRODUCT_QTY = 1;

    /**
     * Product quantity after
     */
    const PRODUCT_QTY_AFTER = 3;

    /**
     * Cart title page
     */
    const CART_TITLE = 'Carro';

    /**
     * Checkout title page
     */
    const CHECKOUT_TITLE = 'Checkout';

    /**
     * Logo file
     */
    const LOGO_FILE = 'logo.png';

    public $price;

    /**
     * @return mixed
     */
    public function getPrice()
    {
        return $this->price;
    }

    /**
     * @param mixed $price
     */
    public function setPrice($price)
    {
        $this->price = $price;
    }

    /**
     * STEP1: Prepare product and go to checkout
     */
    public function prepareProductAndCheckout()
    {
        $this->goToProductPage();
        $this->configureProduct(self::PRODUCT_QTY);
        $this->checkProductPage();
        $this->goToCart();
        $this->goToCheckout();
    }

    /**
     * STEP2: Prepare checkout and check pmt form
     */
    public function makeCheckoutAndPmt()
    {
        $this->checkCheckoutPage();
        $this->goToPmt();
        $this->verifyPaylater();
    }

    /**
     * STEP3: Order Validation
     */
    public function makeValidation()
    {
        $this->verifyOrderInformation();
        $this->quit();
    }

    /**
     * Go to the product page
     * @throws \Facebook\WebDriver\Exception\NoSuchElementException
     * @throws \Facebook\WebDriver\Exception\TimeOutException
     */
    public function goToProductPage()
    {
        $this->webDriver->get(self::WC3URL);
        $this->findByLinkText(self::PRODUCT_NAME)->click();
        $condition = WebDriverExpectedCondition::titleContains(self::PRODUCT_NAME);
        $this->webDriver->wait()->until($condition);
        $this->assertTrue((bool) $condition);
    }

    /**
     * Config product quantity
     *
     * @param $qty
     */
    public function configureProduct($qty)
    {
        $qtyElements = $this->webDriver->findElements(WebDriverBy::className('qty'));
        foreach ($qtyElements as $qtyElement) {
            $qtyElement->clear()->sendKeys($qty);
        }
    }

    /**
     * Configure product
     */
    public function checkProductPage()
    {
        $this->checkSimulator();

        $simulatorElement = $this->findByClass('PmtSimulator');
        $currentSimulatorPrice = $simulatorElement->getAttribute('data-pmt-amount');
        $this->configureProduct(self::PRODUCT_QTY_AFTER);
        sleep(3);
        $simulatorElement = $this->findByClass('PmtSimulator');
        $newPrice = $simulatorElement->getAttribute('data-pmt-amount');
        $newSimulatorPrice = $currentSimulatorPrice * self::PRODUCT_QTY_AFTER;
        $this->assertEquals($newPrice, $newSimulatorPrice, "PR22,PR23");
    }

    /**
     * Add to cart + redirect to Cart page
     * @throws \Facebook\WebDriver\Exception\NoSuchElementException
     * @throws \Facebook\WebDriver\Exception\TimeOutException
     */
    public function goToCart()
    {
        $simulatorElementSearch = WebDriverBy::name('add-to-cart');
        $condition = WebDriverExpectedCondition::visibilityOfElementLocated($simulatorElementSearch);
        $this->waitUntil($condition);

        $this->webDriver->findElement($simulatorElementSearch)->click();
        $condition = WebDriverExpectedCondition::titleContains(self::CART_TITLE);
        $this->webDriver->wait()->until($condition);
        $this->assertTrue((bool) $condition);
    }

    /**
     * Go to the product page
     * @throws \Facebook\WebDriver\Exception\NoSuchElementException
     * @throws \Facebook\WebDriver\Exception\TimeOutException
     */
    public function goToCheckout()
    {
        $this->findByClass('checkout-button')->click();
        $condition = WebDriverExpectedCondition::titleContains(self::CHECKOUT_TITLE);
        $this->webDriver->wait()->until($condition);
        $this->assertTrue((bool) $condition);
    }

    /**
     * Verify payment method
     */
    public function checkCheckoutPage()
    {
        $validatorSearch = WebDriverBy::className('payment_method_paylater');
        $actualString = $this->webDriver->findElement($validatorSearch)->getText();
        $compareString = (strstr($actualString, $this->configuration['methodName'])) === false ? false : true;
        $this->assertTrue($compareString, $actualString, "PR25,PR26");

        //$compareString = (strstr($actualString, self::LOGO_FILE)) === false ? false : true;
        //$this->assertTrue($compareString, $actualString, "PR27 // ".$actualString);

        $this->checkSimulator();

        $priceSearch = WebDriverBy::className('woocommerce-Price-amount');
        $priceElements = $this->webDriver->findElements($priceSearch);

        $this->setPrice($priceElements['2']->getText());
    }

    /**
     * Send ckeckout form
     * @throws \Facebook\WebDriver\Exception\NoSuchElementException
     * @throws \Facebook\WebDriver\Exception\TimeOutException
     */
    public function goToPmt()
    {
        $this->findByName('checkout')->submit();
    }

    /**
     * @throws \Facebook\WebDriver\Exception\NoSuchElementException
     * @throws \Facebook\WebDriver\Exception\TimeOutException
     */
    /*public function checkPmtPage()
    {
        $paymentFormElement = WebDriverBy::className('FieldsPreview-desc');
        $condition = WebDriverExpectedCondition::visibilityOfElementLocated($paymentFormElement);
        $this->waitUntil($condition);
        $this->assertTrue((bool) $condition, "PR32");

        $this->assertSame(
            $this->configuration['firstname'] . ' ' . $this->configuration['lastname'],
            $this->findByClass('FieldsPreview-desc')->getText(),
            "PR34"
        );

        $priceSearch = WebDriverBy::className('LoanSummaryList-desc');
        $priceElements = $this->webDriver->findElements($priceSearch);
        $totalPrice = $this->setPrice($priceElements['0']->getText());
        $this->assertEquals($this->getPrice(), $totalPrice, "PR35");

        $this->webDriver->executeScript("var button = document.getElementsByName('back_to_store_button');button[0].click();");
        $condition = WebDriverExpectedCondition::titleContains(self::CHECKOUT_TITLE);
        $this->webDriver->wait()->until($condition);
        $this->assertTrue((bool) $condition, "PR36");
    }*/

    /**
     * Check simulator product and/or checkout
     */
    private function checkSimulator()
    {
        $simulatorElementSearch = WebDriverBy::className('PmtSimulator');
        $condition = WebDriverExpectedCondition::visibilityOfElementLocated($simulatorElementSearch);
        $this->waitUntil($condition);
        $this->assertTrue((bool) $condition, "PR19//PR28");

        $simulatorElement = $this->webDriver->findElement(WebDriverBy::className('PmtSimulator'));
        $minInstallments = $simulatorElement->getAttribute('data-pmt-num-quota');
        $this->assertEquals($minInstallments, $this->configuration['defaultMinIns'], "PR20//PR29");

        $maxInstallments = $simulatorElement->getAttribute('data-pmt-max-ins');
        $this->assertEquals($maxInstallments, $this->configuration['defaultMaxIns'], "PR20//PR29");
    }

    /**
     * Prepare checkout, called from BuyRegistered and BuyUnregistered
     */
    public function prepareCheckout()
    {
        $condition = WebDriverExpectedCondition::titleContains(self::CHECKOUT_TITLE);
        $this->webDriver->wait()->until($condition);
        $this->assertTrue((bool) $condition);

        $this->findById('billing_first_name')->clear()->sendKeys($this->configuration['firstname']);
        $this->findById('billing_last_name')->clear()->sendKeys($this->configuration['lastname']);
        $this->findById('billing_address_1')->clear()->sendKeys($this->configuration['address']);
        $this->findById('billing_postcode')->clear()->sendKeys($this->configuration['zip']);
        $this->findById('billing_city')->clear()->sendKeys($this->configuration['city']);
        $this->webDriver->findElement(WebDriverBy::id('billing_state'))
                        ->findElement(WebDriverBy::cssSelector("option[value='B']"))
                        ->click();

        $this->findById('billing_phone')->clear()->sendKeys($this->configuration['phone']);
        $this->findById('billing_email')->clear()->sendKeys($this->configuration['email']);
    }

    /**
     * Verify Paylater
     *
     * @throws \Exception
     */
    public function verifyPaylater()
    {
        SeleniumHelper::finishForm($this->webDriver);
    }

    /**
     * Verify Order Information
     */
    public function verifyOrderInformation()
    {
        $messageElementSearch = WebDriverBy::className('entry-title');
        $condition = WebDriverExpectedCondition::visibilityOfElementLocated($messageElementSearch);
        $this->waitUntil($condition);
        $actualString = $this->webDriver->findElement($messageElementSearch)->getText();
        $this->assertNotEmpty($actualString, "PR45");
        $this->assertNotEmpty($this->configuration['confirmationMsg'], "PR45");
        $compareString = (strstr($actualString, $this->configuration['confirmationMsg'])) === false ? false : true;
        $this->assertTrue($compareString, $actualString." PR45");

        $menuSearch = WebDriverBy::cssSelector("li.woocommerce-order-overview__total > strong > span.woocommerce-Price-amount");
        $menuElement = $this->webDriver->findElement($menuSearch);
        $actualString = $menuElement->getText();
        $compareString = (strstr($actualString, $this->getPrice())) === false ? false : true;
        $this->assertNotEmpty($compareString, "PR46");
        $this->assertNotEmpty($this->getPrice(), "PR46");
        $this->assertTrue($compareString, $actualString . $this->getPrice() ." PR46");

        $validatorSearch = WebDriverBy::className('woocommerce-order-overview__payment-method');
        $actualString = $this->webDriver->findElement($validatorSearch)->getText();
        $compareString = (strstr($actualString, $this->configuration['methodName'])) === false ? false : true;
        $this->assertTrue($compareString, $actualString, "PR25,PR26");
    }
}
