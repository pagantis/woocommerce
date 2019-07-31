<?php

namespace Test\Selenium;

use Facebook\WebDriver\Interactions\WebDriverActions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverElement;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Faker\Factory;
use PHPUnit\Framework\TestCase;

/**
 * Class PagantisWoocommerceTest
 * @package Test\Selenium
 */
abstract class PagantisWoocommerceTest extends TestCase
{
    const WOOCOMMERCE_URL_TEST = 'http://woocommerce-test.docker:8091';

    const WOOCOMMERCE_URL_DEV = 'http://woocommerce-dev.docker:8090';

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
        'methodName'    => 'Pagantis',
        'checkoutTitle' => 'Financiación Instantánea',
        'defaultMinIns' => 3,
        'defaultMaxIns' => 12,
        'defaultSimulatorOpt' => 6,
        'checkoutDescription'=> 'Pay up to 12 comfortable installments with Pagantis'
    );

    /**
     * @var string URL for test
     */
    protected $woocommerceUrl;

    /**
     * @var language of the installation
     */
    protected $woocommerceLanguage;

    /**
     * WooCommerce constructor.
     *
     * @param null   $name
     * @param array  $data
     * @param string $dataName
     */
    public function __construct($name = null, array $data = array(), $dataName = '')
    {
        $this->woocommerceUrl = $this->getUrl();
        $faker = Factory::create();
        $this->configuration['dni'] = $this->getDNI();
        $this->configuration['birthdate'] =
            $faker->numberBetween(1, 28) . '/' .
            $faker->numberBetween(1, 12). '/1975'
        ;
        $this->configuration['firstname'] = $faker->firstName;
        $this->configuration['lastname'] = $faker->lastName . ' ' . $faker->lastName;
        $this->configuration['company'] = $faker->company;
        $this->configuration['zip'] = '28'.$faker->randomNumber(3, true);
        $this->configuration['street'] = $faker->streetAddress;
        $this->configuration['phone'] = '6' . $faker->randomNumber(8);
        $this->configuration['email'] = date('ymd') . '@pagantis.com';
        parent::__construct($name, $data, $dataName);
    }
    /**
     * @return string
     */
    protected function getDNI()
    {
        $dni = '0000' . rand(pow(10, 4-1), pow(10, 4)-1);
        $value = (int) ($dni / 23);
        $value *= 23;
        $value= $dni - $value;
        $letter= "TRWAGMYFPDXBNJZSQVHLCKEO";
        $dniLetter= substr($letter, $value, 1);
        return $dni.$dniLetter;
    }

    /**
     * @var RemoteWebDriver
     */
    protected $webDriver;

    /**
     * Configure selenium
     */
    protected function setUp()
    {
        $this->webDriver = PagantisWebDriver::create(
            'http://localhost:4444/wd/hub',
            DesiredCapabilities::chrome(),
            90000,
            90000
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

    /**
     * @return mixed
     */
    protected function getUrl()
    {
        $env = getenv('WOOCOMMERCE_TEST_ENV');
        if ($env == 'dev') {
            $this->woocommerceLanguage = "EN";
            return self::WOOCOMMERCE_URL_DEV;
        }
        return self::WOOCOMMERCE_URL_TEST;
    }
}
