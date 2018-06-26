<?php

namespace Test\Selenium;

use Facebook\WebDriver\Interactions\WebDriverActions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverElement;
use Facebook\WebDriver\WebDriverExpectedCondition;
use PHPUnit\Framework\TestCase;

/**
 * Class PaylaterWoocommerceTest
 * @package Test\Selenium
 */
abstract class PaylaterWoocommerceTest extends TestCase
{
    const WC3URL = 'http://woocommerce-test';

    const BACKOFFICE_FOLDER = '/wp-admin';

    /**
     * @var array
     */
    protected $configuration = array(
        'username'      => 'demo@woocommerceshop.com',
        'password'      => 'woocommerceshop_demo',
        'publicKey'     => 'tk_fd53cd467ba49022e4f8215e',
        'secretKey'     => '21e57baa97459f6a',
        'birthdate'     => '05/05/2005',
        'firstname'     => 'Jøhn',
        'lastname'      => 'Dōè',
        'email'         => 'john_wc@digitalorigin.com',
        'company'       => 'Digital Origin SL',
        'zip'           => '08023',
        'city'          => 'Barcelona',
        'phone'         => '600123123',
        'dni'           => '09422447Z',
        'extra'         => 'Free Finance',
        'address'       => 'Av.Diagonal 579',
        'methodName'    => 'Financiación instantánea - 100% online',
        'defaultMinIns' => 2,
        'defaultMaxIns' => 12,
        'defaultSimulatorOpt' => 2
    );

    /**
     * @var RemoteWebDriver
     */
    protected $webDriver;

    /**
     * Configure selenium
     */
    protected function setUp()
    {
        $this->webDriver = RemoteWebDriver::create(
            'http://selenium:4444/wd/hub',
            DesiredCapabilities::chrome(),
            60000,
            60000
        );
    }

    /**
     * @param $name
     *
     * @return \Facebook\WebDriver\Remote\RemoteWebElement
     */
    public function findByName($name)
    {
        return $this->webDriver->findElement(WebDriverBy::name($name));
    }

    /**
     * @param $id
     *
     * @return \Facebook\WebDriver\Remote\RemoteWebElement
     */
    public function findById($id)
    {
        return $this->webDriver->findElement(WebDriverBy::id($id));
    }

    /**
     * @param $className
     *
     * @return \Facebook\WebDriver\Remote\RemoteWebElement
     */
    public function findByClass($className)
    {
        return $this->webDriver->findElement(WebDriverBy::className($className));
    }

    /**
     * @param $css
     *
     * @return \Facebook\WebDriver\Remote\RemoteWebElement
     */
    public function findByCss($css)
    {
        return $this->webDriver->findElement(WebDriverBy::cssSelector($css));
    }

    /**
     * @param $xpath
     *
     * @return \Facebook\WebDriver\Remote\RemoteWebElement
     */
    public function findByXpath($xpath)
    {
        return $this->webDriver->findElement(WebDriverBy::xpath($xpath));
    }

    /**
     * @param $link
     *
     * @return \Facebook\WebDriver\Remote\RemoteWebElement
     */
    public function findByLinkText($link)
    {
        return $this->webDriver->findElement(WebDriverBy::partialLinkText($link));
    }

    /**
     * @param WebDriverExpectedCondition $condition
     * @return mixed
     * @throws \Facebook\WebDriver\Exception\NoSuchElementException
     * @throws \Facebook\WebDriver\Exception\TimeOutException
     */
    public function waitUntil(WebDriverExpectedCondition $condition)
    {
        return $this->webDriver->wait()->until($condition);
    }

    /**
     * @param WebDriverElement $element
     *
     * @return WebDriverElement
     */
    public function moveToElementAndClick(WebDriverElement $element)
    {
        $action = new WebDriverActions($this->webDriver);
        $action->moveToElement($element);
        $action->click($element);
        $action->perform();

        return $element;
    }

    /**
     * @param WebDriverElement $element
     *
     * @return WebDriverElement
     */
    public function getParent(WebDriverElement $element)
    {
        return $element->findElement(WebDriverBy::xpath(".."));
    }

    /**
     * Quit browser
     */
    protected function quit()
    {
        $this->webDriver->quit();
    }
}