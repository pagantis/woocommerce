<?php

namespace Test\Selenium\Buy;

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Pagantis\ModuleUtils\Exception\AlreadyProcessedException;
use Pagantis\ModuleUtils\Exception\NoIdentificationException;
use Pagantis\ModuleUtils\Exception\QuoteNotFoundException;
use Pagantis\ModuleUtils\Model\Response\JsonSuccessResponse;
use Test\Selenium\PagantisWoocommerceTest;
use Pagantis\SeleniumFormUtils\SeleniumHelper;
use Httpful\Request;
use Httpful\Mime;

/**
 * Class AbstractBuy
 * @package Test\Selenium\Buy
 *
 */
abstract class AbstractBuy extends PagantisWoocommerceTest
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

    /**
     *  Notification folder
     */
    const NOTIFICATION_FOLDER = '/?wc-api=wcpagantisgateway';

    /**
     *  Config folder
     */
    const CONFIG_FOLDER = '/?rest_route=/pagantis/v1/configController/';

    /**
     *  Log folder
     */
    const LOG_FOLDER = '/?rest_route=/pagantis/v1/logs/';

    /**
     *  Api folder
     */
    const API_FOLDER = '/?rest_route=/pagantis/v1/api/';

    /**
     *  Notification param1
     */
    const NOTIFICATION_PARAMETER1 = 'key';

    /**
     *  Notification param2
     */
    const NOTIFICATION_PARAMETER2 = 'order-received';

    /**
     *  Notification param3
     */
    const NOTIFICATION_PARAMETER3 = 'token';

    /**
     * Pagantis Order Title
     */
    const PAGANTIS_TITLE = 'Pagantis';

    /**
     * Already processed
     */
    const NOTFOUND_TITLE = 'Pagantis Order Not Found';

    /**
     * Wrong order
     */
    const NOORDER_TITLE = 'Cart already processed';

    /**
     * @var String $price
     */
    public $price;

    /**
     * @var String $orderUrl
     */
    public $orderUrl;

    /**
     * @var String $orderKey
     */
    public $orderKey;

    /**
     * @var String $orderReceived
     */
    public $orderReceived;

    /**
     * @var String $notifyUrl
     */
    public $notifyUrl;

    /**
     * @var array $configs
     */
    protected $configs = array(
        "PAGANTIS_TITLE",
        "PAGANTIS_SIMULATOR_DISPLAY_TYPE",
        "PAGANTIS_SIMULATOR_DISPLAY_SKIN",
        "PAGANTIS_SIMULATOR_DISPLAY_POSITION",
        "PAGANTIS_SIMULATOR_START_INSTALLMENTS",
        "PAGANTIS_SIMULATOR_CSS_POSITION_SELECTOR",
        "PAGANTIS_SIMULATOR_DISPLAY_CSS_POSITION",
        "PAGANTIS_SIMULATOR_CSS_PRICE_SELECTOR",
        "PAGANTIS_SIMULATOR_CSS_QUANTITY_SELECTOR",
        "PAGANTIS_FORM_DISPLAY_TYPE",
        "PAGANTIS_DISPLAY_MIN_AMOUNT",
        "PAGANTIS_DISPLAY_MAX_AMOUNT",
        "PAGANTIS_URL_OK",
        "PAGANTIS_URL_KO",
    );

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
        $this->checkSimulator();
        $this->goToCart();
        $this->goToCheckout();
    }

    /**
     * STEP2: Prepare checkout and check pagantis form
     *
     * @throws \Exception
     */
    public function makeCheckoutAndPagantis()
    {
        $this->checkCheckoutPage();
        $this->webDriver->takeScreenshot("/tmp/artifacts/makeCheckoutAndPagantis-Before-goToPagantis-".__LINE__."jpeg");
        $this->goToPagantis();
        $this->webDriver->takeScreenshot("/tmp/artifacts/makeCheckoutAndPagantis-Before-verifyPagantis-".__LINE__."jpeg");

        $this->verifyPagantis();
    }

    /**
     * STEP3: Order Validation
     */
    public function makeValidation()
    {
        $this->verifyOrderInformation();
        $this->orderUrl = $this->webDriver->getCurrentURL();
        $this->checkNotificationException();
    }

    /**
     * Go to the product page
     * @throws \Facebook\WebDriver\Exception\NoSuchElementException
     * @throws \Facebook\WebDriver\Exception\TimeOutException
     */
    public function goToProductPage()
    {
        $this->webDriver->get($this->woocommerceUrl);
        $this->findByLinkText(self::PRODUCT_NAME)->click();
        $condition = WebDriverExpectedCondition::titleContains(self::PRODUCT_NAME);
        $this->webDriver->wait()->until($condition);
        $this->assertTrue((bool) $condition);
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
        $buttonElementSearch = WebDriverBy::linkText('Checkout');
        $condition = WebDriverExpectedCondition::elementToBeClickable($buttonElementSearch);
        $this->waitUntil($condition);

        $this->findByLinkText('Checkout')->click();
        $condition = WebDriverExpectedCondition::titleContains(self::CHECKOUT_TITLE);
        $this->webDriver->wait()->until($condition);
        $this->assertTrue((bool) $condition);
    }

    /**
     * Verify payment method
     */
    public function checkCheckoutPage()
    {
        $validatorSearch = WebDriverBy::className('payment_method_pagantis');
        $actualString = $this->webDriver->findElement($validatorSearch)->getText();
        $compareString = (strstr($actualString, $this->configuration['checkoutTitle'])) === false ? false : true;
        $this->assertTrue($compareString, "PR25,PR26 - $actualString");

        $this->checkSimulator();

        $priceSearch = WebDriverBy::className('woocommerce-Price-amount');
        $priceElements = $this->webDriver->findElements($priceSearch);

        $this->setPrice($priceElements['2']->getText());
    }

    /**
     * Send ckeckout form
     */
    public function goToPagantis()
    {
        $this->findByName('checkout')->submit();
    }

    /**
     * Check simulator product
     */
    private function checkSimulator()
    {
        sleep(10);
        $simulatorElementSearch = WebDriverBy::className('pagantisSimulator');
        $condition = WebDriverExpectedCondition::visibilityOfElementLocated($simulatorElementSearch);
        $this->waitUntil($condition);
        $this->assertTrue((bool) $condition, "PR19");
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
     * Verify Pagantis
     *
     * @throws \Exception
     */
    public function verifyPagantis()
    {
        /*$condition = WebDriverExpectedCondition::titleContains(self::PAGANTIS_TITLE);
        $this->webDriver->wait(300)->until($condition, $this->webDriver->getCurrentURL());
        $this->assertTrue((bool)$condition, "PR32");*/

        $this->webDriver->takeScreenshot("/tmp/artifacts/verifyPagantis-".__LINE__."jpeg");
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
        $confString = ($this->woocommerceLanguage == 'EN') ? "Order received" : "Pedido recibido";
        $this->assertNotEmpty($confString, "PR45");
        $compareString = (strstr($actualString, $confString)) === false ? false : true;
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
        $this->assertTrue($compareString, $actualString, "PR49");
    }

    /**
     * Check the notifications
     *
     * @throws \Httpful\Exception\ConnectionErrorException
     */
    public function checkNotificationException()
    {
        //Get the confirmation page url
        $orderUrl = $this->orderUrl;
        $this->assertNotEmpty($orderUrl, $orderUrl);
        $orderArray = explode('&', $orderUrl);

        $orderReceived = explode("=", $orderArray['1']);
        $this->orderReceived = $orderReceived[1];

        $orderKey = explode("=", $orderArray['2']);
        $this->orderKey = $orderKey[1];

        $this->notifyUrl =  sprintf(
            "%s%s%s%s%s%s%s%s%s",
            $this->woocommerceUrl,
            self::NOTIFICATION_FOLDER,
            '&',
            self::NOTIFICATION_PARAMETER1,
            '=',
            $this->orderKey,
            '&',
            self::NOTIFICATION_PARAMETER2,
            '='
        );
        $this->checkConcurrency();
        $this->checkPagantisOrderId();
        $this->checkAlreadyProcessed();
    }

    /**
     * Check if with a empty parameter called order-received we can get a QuoteNotFoundException
     *
     * @throws \Httpful\Exception\ConnectionErrorException
     */
    protected function checkConcurrency()
    {
        $this->assertNotEmpty($this->notifyUrl, $this->notifyUrl);
        $response = Request::post($this->notifyUrl)->expects('json')->send();
        $this->assertNotEmpty($response->body->result, $response->body->result);
        $this->assertContains(QuoteNotFoundException::ERROR_MESSAGE, $response->body->result, "PR58=>".$response->body->result);
    }

    /**
     * Check if with a parameter called order-received set to a invalid identification, we can get a NoIdentificationException
     *
     * @throws \Httpful\Exception\ConnectionErrorException
     */
    protected function checkPagantisOrderId()
    {
        $notifyUrl = $this->notifyUrl.'0';
        $this->assertNotEmpty($notifyUrl, $notifyUrl);
        $response = Request::post($notifyUrl)->expects('json')->send();
        $this->assertNotEmpty($response->body->result);
//       var_dump($response->body ." ".PHP_EOL);
        echo $this->notifyUrl;
        $this->assertContains(NoIdentificationException::ERROR_MESSAGE, $response->body->result, "PR59=>".$response->body->result);
    }

    /**
     * Check if re-launching the notification we can get a AlreadyProcessedException
     *
     * @throws \Httpful\Exception\ConnectionErrorException
     */
    protected function checkAlreadyProcessed()
    {
        $notifyUrl = $this->notifyUrl.$this->orderReceived;
        $this->assertNotEmpty($notifyUrl, $notifyUrl);
        $response = Request::post($notifyUrl)->expects('json')->send();
        $this->assertNotEmpty($response->body->result);
        $this->assertContains(JsonSuccessResponse::RESULT, $response->body->result, "PR51=>".$response->body->result);
    }

    /**
     * @throws \Httpful\Exception\ConnectionErrorException
     */
    protected function checkExtraConfig()
    {
        $configUrl = $this->woocommerceUrl.self::CONFIG_FOLDER.$this->configuration['secretKey'];
        $response = Request::get($configUrl)->expects('json')->send();
        $content = $response->body;
        foreach ($this->configs as $config) {
            $this->assertArrayHasKey($config, (array) $content, "PR61=>".print_r($content, true));
        }

        $requestTitle = 'changed';
        $body = array('PAGANTIS_TITLE' => $requestTitle);
        $response = Request::post($configUrl)
                           ->body($body, Mime::FORM)
                           ->expectsJSON()
                           ->send();
        $title = $response->body->PAGANTIS_TITLE;
        $this->assertEquals($requestTitle, $title, "PR62=>".$configUrl." => ".$requestTitle ."!=".$title);
        $requestTitle = 'Pago en cuotas';
        $body = array('PAGANTIS_TITLE' => $requestTitle);
        $response = Request::post($configUrl)
                           ->body($body, Mime::FORM)
                           ->expectsJSON()
                           ->send();
        $title = $response->body->PAGANTIS_TITLE;
        $this->assertEquals($requestTitle, $title, "PR62b=>".$configUrl." => ".$requestTitle ."!=".$title);
    }

    /**
     * @throws \Httpful\Exception\ConnectionErrorException
     */
    protected function checkApi()
    {
        $dateFrom = date("Ymd", strtotime("-1 day"));
        $dateTo = date("Ymd", strtotime("+1 day"));
        $apiUrl = $this->woocommerceUrl.self::API_FOLDER.$this->configuration['secretKey']."/$dateFrom/$dateTo";
        $response = Request::get($apiUrl)->expects('json')->send();
        $this->assertNotEmpty($response->body->message);
        $numberOfPurchases = count((array)$response->body->message);
        $this->assertEquals(1, $numberOfPurchases, "PR63=>$apiUrl = $numberOfPurchases");
    }

    /**
     * @throws \Httpful\Exception\ConnectionErrorException
     */
    protected function checkLogs()
    {
        $dateFrom = date("Ymd", strtotime("-1 day"));
        $dateTo = date("Ymd", strtotime("+1 day"));
        $logUrl = $this->woocommerceUrl.self::LOG_FOLDER.$this->configuration['secretKey']."/$dateFrom/$dateTo";
        $response = Request::get($logUrl)->expects('json')->send();
        $this->assertEquals(2, count($response->body), "PR60=>".$logUrl." = ".count($response->body));
    }

}
