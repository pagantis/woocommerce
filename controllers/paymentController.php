<?php

//namespace empty
use Pagantis\ModuleUtils\Exception\OrderNotFoundException;
use Pagantis\OrdersApiClient\Model\Order\User\Address;
use Pagantis\OrdersApiClient\Model\Order\User;
use Pagantis\OrdersApiClient\Model\Order\User\OrderHistory;
use Pagantis\OrdersApiClient\Model\Order\ShoppingCart\Details;
use Pagantis\OrdersApiClient\Model\Order\ShoppingCart;
use Pagantis\OrdersApiClient\Model\Order\ShoppingCart\Details\Product;
use Pagantis\OrdersApiClient\Model\Order\Metadata;
use Pagantis\OrdersApiClient\Model\Order\Configuration\Urls;
use Pagantis\OrdersApiClient\Model\Order\Configuration\Channel;
use Pagantis\OrdersApiClient\Model\Order\Configuration;
use Pagantis\OrdersApiClient\Client;
use Pagantis\OrdersApiClient\Model\Order;

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('__ROOT__')) {
    define('__ROOT__', dirname(dirname(__FILE__)));
}

class WcPagantisGateway extends WC_Payment_Gateway
{
    const METHOD_ID = "pagantis";

    const NOT_CONFIRMED = 'No se ha podido confirmar el pago';

    /** @var array $extraConfig */
    public $extraConfig;

    /** @var string $language */
    public $language;

    /**
     * @var string $template_path
     */

    private $template_path;
    /**
     * @var array $allowed_currencies
     */
    private $allowed_currencies;

    /**
     * WcPagantisGateway constructor.
     */
    public function __construct()
    {
        //Mandatory vars for plugin
        $this->id = WcPagantisGateway::METHOD_ID;
        $this->has_fields = true;
        $this->method_title = ucfirst($this->id);

        //Useful vars
        $this->template_path = plugin_dir_path(__FILE__) . '../templates/';
        $this->allowed_currencies = getAllowedCurrencies();
        $this->language = strstr(get_locale(), '_', true);
        if ($this->language=='') {
            $this->language = 'ES';
        }
        $this->icon = 'https://cdn.digitalorigin.com/assets/master/logos/pg-130x30.svg';

        //Panel form fields
        $this->form_fields = include(plugin_dir_path(__FILE__).'../includes/settings-pagantis.php');//Panel options
        $this->init_settings();

        $this->extraConfig = getExtraConfig();
        $this->title = __($this->extraConfig['PAGANTIS_TITLE'], 'pagantis');
        $this->method_description = "Financial Payment Gateway. Enable the possibility for your customers to pay their order in confortable installments with Pagantis.";

        $this->settings['ok_url'] = ($this->extraConfig['PAGANTIS_URL_OK']!='')?$this->extraConfig['PAGANTIS_URL_OK']:$this->generateOkUrl();
        $this->settings['ko_url'] = ($this->extraConfig['PAGANTIS_URL_KO']!='')?$this->extraConfig['PAGANTIS_URL_KO']:$this->generateKoUrl();
        foreach ($this->settings as $setting_key => $setting_value) {
            $this->$setting_key = $setting_value;
        }

        //Hooks
        add_action('woocommerce_update_options_payment_gateways_'.$this->id, array($this,'process_admin_options')); //Save plugin options
        add_action('admin_notices', array($this, 'pagantisCheckFields'));                          //Check config fields
        add_action('woocommerce_receipt_'.$this->id, array($this, 'pagantisReceiptPage'));          //Pagantis form
        add_action('woocommerce_api_wcpagantisgateway', array($this, 'pagantisNotification'));      //Json Notification
        add_filter('woocommerce_payment_complete_order_status', array($this,'pagantisCompleteStatus'), 10, 3);
        add_filter('load_textdomain_mofile', array($this, 'loadPagantisTranslation'), 10, 2);
    }

    /**
     * @param $mofile
     * @param $domain
     *
     * @return string
     */
    public function loadPagantisTranslation($mofile, $domain)
    {
        if ('pagantis' === $domain) {
            $mofile = WP_LANG_DIR . '/../plugins/pagantis/languages/pagantis-' . get_locale() . '.mo';
        }
        return $mofile;
    }

    /***********
     *
     * HOOKS
     *
     ***********/

    /**
     * PANEL - Display admin panel
     * @hooked woocommerce_update_options_payment_gateways_pagantis
     */
    public function admin_options()
    {
        $template_fields = array(
            'panel_description' => $this->method_description,
            'button1_label' => __('Login to your panel', 'pagantis'),
            'button2_label' => __('Documentation', 'pagantis'),
            'logo' => $this->icon,
            'settings' => $this->generate_settings_html($this->form_fields, false)
        );
        wc_get_template('admin_header.php', $template_fields, '', $this->template_path);
    }

    /**
     * PANEL - Check admin panel fields
     * @hooked  admin_notices
     */
    public function pagantisCheckFields()
    {
        $error_string = '';
        if ($this->settings['enabled'] !== 'yes') {
            return;
        } elseif (!version_compare(phpversion(), '5.3.0', '>=')) {
            $error_string =  __(' is not compatible with your php and/or curl version', 'pagantis');
            $this->settings['enabled'] = 'no';
        } elseif (($this->settings['pagantis_public_key']=="" || $this->settings['pagantis_private_key']=="")  && (empty($this->settings['pagantis_public_key_4x']) || empty($this->settings['pagantis_private_key_4x']))) {
            $error_string = __(' is not configured correctly, the fields Public Key and Secret Key are mandatory for use this plugin', 'pagantis');
            $this->settings['enabled'] = 'no';
        } elseif (!in_array(get_woocommerce_currency(), $this->allowed_currencies)) {
            $error_string =  __(' only can be used in Euros', 'pagantis');
            $this->settings['enabled'] = 'no';
        } elseif ($this->extraConfig['PAGANTIS_SIMULATOR_MAX_INSTALLMENTS']<'2'
                  || $this->extraConfig['PAGANTIS_SIMULATOR_MAX_INSTALLMENTS']>'12') {
            $error_string = __(' only can be payed from 2 to 12 installments', 'pagantis');
        } elseif ($this->extraConfig['PAGANTIS_SIMULATOR_START_INSTALLMENTS']<'2'
                  || $this->extraConfig['PAGANTIS_SIMULATOR_START_INSTALLMENTS']>'12') {
            $error_string = __(' only can be payed from 2 to 12 installments', 'pagantis');
        } elseif ($this->extraConfig['PAGANTIS_DISPLAY_MIN_AMOUNT']<0) {
            $error_string = __(' can not have a minimum amount less than 0', 'pagantis');
        }

        if ($error_string!='') {
            $template_fields = array(
                'error_msg' => ucfirst(WcPagantisGateway::METHOD_ID).' '.$error_string,
            );
            wc_get_template('error_msg.php', $template_fields, '', $this->template_path);
        }
    }


    /**
     * CHECKOUT - Generate the pagantis form. "Return" iframe or redirect.
     * @param $order_id
     *
     * @hooked woocommerce_receipt_pagantis
     * @throws Exception
     */
    public function pagantisReceiptPage($order_id)
    {
        try {
            require_once(__ROOT__.'/vendor/autoload.php');
            $order = new WC_Order($order_id);
            $order->set_payment_method(ucfirst($this->id));
            $order->save();

            if (!isset($order)) {
                throw new Exception(_("Order not found"));
            }

            $shippingAddress = $order->get_address('shipping');
            $billingAddress = $order->get_address('billing');
            if ($shippingAddress['address_1'] == '') {
                $shippingAddress = $billingAddress;
            }

            $national_id = getNationalId($order);
            $tax_id = getTaxId($order);

            $userAddress = new Address();
            $userAddress
                ->setZipCode($shippingAddress['postcode'])
                ->setFullName($shippingAddress['first_name']." ".$shippingAddress['last_name'])
                ->setCountryCode($shippingAddress['country']!='' ? strtoupper($shippingAddress['country']) : strtoupper($this->language))
                ->setCity($shippingAddress['city'])
                ->setAddress($shippingAddress['address_1']." ".$shippingAddress['address_2'])
            ;
            $orderShippingAddress = new Address();
            $orderShippingAddress
                ->setZipCode($shippingAddress['postcode'])
                ->setFullName($shippingAddress['first_name']." ".$shippingAddress['last_name'])
                ->setCountryCode($shippingAddress['country']!='' ? strtoupper($shippingAddress['country']) : strtoupper($this->language))
                ->setCity($shippingAddress['city'])
                ->setAddress($shippingAddress['address_1']." ".$shippingAddress['address_2'])
                ->setFixPhone($shippingAddress['phone'])
                ->setMobilePhone($shippingAddress['phone'])
                ->setNationalId($national_id)
                ->setTaxId($tax_id)
            ;
            $orderBillingAddress =  new Address();
            $orderBillingAddress
                ->setZipCode($billingAddress['postcode'])
                ->setFullName($billingAddress['first_name']." ".$billingAddress['last_name'])
                ->setCountryCode($billingAddress['country']!='' ? strtoupper($billingAddress['country']) : strtoupper($this->language))
                ->setCity($billingAddress['city'])
                ->setAddress($billingAddress['address_1']." ".$billingAddress['address_2'])
                ->setFixPhone($billingAddress['phone'])
                ->setMobilePhone($billingAddress['phone'])
                ->setNationalId($national_id)
                ->setTaxId($tax_id)
            ;
            $orderUser = new User();
            $orderUser
                ->setAddress($userAddress)
                ->setFullName($billingAddress['first_name']." ".$billingAddress['last_name'])
                ->setBillingAddress($orderBillingAddress)
                ->setEmail($billingAddress['email'])
                ->setFixPhone($billingAddress['phone'])
                ->setMobilePhone($billingAddress['phone'])
                ->setShippingAddress($orderShippingAddress)
                ->setNationalId($national_id)
                ->setTaxId($tax_id)
            ;

            $previousOrders = getOrders($order->get_user(), $billingAddress['email']);
            foreach ($previousOrders as $previousOrder) {
                $orderHistory = new OrderHistory();
                $orderElement = wc_get_order($previousOrder);
                $orderCreated = $orderElement->get_date_created();
                $orderHistory
                    ->setAmount(intval(100 * $orderElement->get_total()))
                    ->setDate(new \DateTime($orderCreated->date('Y-m-d H:i:s')))
                ;
                $orderUser->addOrderHistory($orderHistory);
            }

            $metadataOrder = new Metadata();
            $tokenString = $orderUser->getEmail().$orderUser->getFullName().time();
            $token = md5($tokenString);
var_dump($token);
            $metadata = array(
                'pg_module' => 'woocommerce',
                'pg_version' => PG_VERSION,
                'ec_module' => 'woocommerce',
                'ec_version' => WC()->version,
                'token' => $token
            );

            foreach ($metadata as $key => $metadatum) {
                $metadataOrder->addMetadata($key, $metadatum);
            }

            $details = new Details();
            $shippingCost = $order->shipping_total;
            $details->setShippingCost(intval(strval(100 * $shippingCost)));
            $items = $order->get_items();
            $promotedAmount = 0;
            foreach ($items as $key => $item) {
                $wcProduct = $item->get_product();
                $product = new Product();
                $productDescription = sprintf(
                    '%s %s %s',
                    $wcProduct->get_name(),
                    $wcProduct->get_description(),
                    $wcProduct->get_short_description()
                );
                $productDescription = substr($productDescription, 0, 9999);

                $product
                    ->setAmount(intval(100 * ($item->get_total() + $item->get_total_tax())))
                    ->setQuantity($item->get_quantity())
                    ->setDescription($productDescription)
                ;
                $details->addProduct($product);

                $promotedProduct = isProductPromoted($item->get_product_id());
                if ($promotedProduct == 'true') {
                    $promotedAmount+=$product->getAmount();
                    $promotedMessage = 'Promoted Item: ' . $wcProduct->get_name() .
                                       ' - Price: ' . $item->get_total() .
                                       ' - Qty: ' . $product->getQuantity() .
                                       ' - Item ID: ' . $item['id_product'];
                    $promotedMessage = substr($promotedMessage, 0, 999);
                    $metadataOrder->addMetadata('promotedProduct', $promotedMessage);
                }
            }

            $orderShoppingCart = new ShoppingCart();
            $orderShoppingCart
                ->setDetails($details)
                ->setOrderReference($order->get_id())
                ->setPromotedAmount($promotedAmount)
                ->setTotalAmount(intval(strval(100 * $order->total)))
            ;
            $orderConfigurationUrls = new Urls();
            $cancelUrl = $this->getKoUrl($order);
            $callback_arg = array('wc-api'=>'wcpagantisgateway',
                'key'=>$order->get_order_key(),
                'order-received'=>$order->get_id(),
                'origin' => ''
            );

            $callback_arg_user = $callback_arg;
            $callback_arg_user['origin'] = 'redirect';
            $callback_arg_user['product'] = Ucfirst(WcPagantisGateway::METHOD_ID);
            $callback_arg_user['token'] = $token;
            $callback_url_user = add_query_arg($callback_arg_user, home_url('/'));

            $callback_arg_notif = $callback_arg;
            $callback_arg_notif['origin'] = 'notification';
            $callback_arg_notif['product'] = Ucfirst(WcPagantisGateway::METHOD_ID);
            $callback_arg_notif['token'] = $token;
            $callback_url_notif = add_query_arg($callback_arg_notif, home_url('/'));

            $orderConfigurationUrls
                ->setCancel($cancelUrl)
                ->setKo($callback_url_user)
                ->setAuthorizedNotificationCallback($callback_url_notif)
                ->setRejectedNotificationCallback(null)
                ->setOk($callback_url_user)
            ;
            $orderChannel = new Channel();
            $orderChannel
                ->setAssistedSale(false)
                ->setType(Channel::ONLINE)
            ;

            $allowedCountries = unserialize($this->extraConfig['PAGANTIS_ALLOWED_COUNTRIES']);
            $purchaseCountry =
                in_array(strtolower($this->language), $allowedCountries) ? $this->language :
                in_array(strtolower($shippingAddress['country']), $allowedCountries) ? $shippingAddress['country'] :
                in_array(strtolower($billingAddress['country']), $allowedCountries) ? $billingAddress['country'] : null;

            $orderConfiguration = new Configuration();
            $orderConfiguration
                ->setChannel($orderChannel)
                ->setUrls($orderConfigurationUrls)
                ->setPurchaseCountry($purchaseCountry)
            ;

            $orderApiClient = new Order();
            $orderApiClient
                ->setConfiguration($orderConfiguration)
                ->setMetadata($metadataOrder)
                ->setShoppingCart($orderShoppingCart)
                ->setUser($orderUser)
            ;

            if ($this->pagantis_public_key=='' || $this->pagantis_private_key=='') {
                throw new \Exception('Public and Secret Key not found');
            }
            $orderClient = new Client($this->pagantis_public_key, $this->pagantis_private_key);
            $pagantisOrder = $orderClient->createOrder($orderApiClient);
            if ($pagantisOrder instanceof \Pagantis\OrdersApiClient\Model\Order) {
                $url = $pagantisOrder->getActionUrls()->getForm();
                addOrderToCartProcessingQueue($order->get_id(), $pagantisOrder->getId(), $token);
            } else {
                throw new OrderNotFoundException();
            }

            if ($url=="") {
                throw new Exception(_("No ha sido posible obtener una respuesta de Pagantis"));
            } elseif ($this->extraConfig['PAGANTIS_FORM_DISPLAY_TYPE']=='0') {
                wp_redirect($url);
                exit;
            } else {
                // @todo TM refactor with wp_localize_script - maybe put in other function
                $template_fields = array(
                    'url' => $url,
                    'checkoutUrl'   => $cancelUrl
                );
                wc_get_template('iframe.php', $template_fields, '', $this->template_path);
            }
        } catch (\Exception $exception) {
            wc_add_notice(__('Payment error ', 'pagantis') . $exception->getMessage(), 'error');
            insertLogEntry($exception);
            $checkout_url = get_permalink(wc_get_page_id('checkout'));
            wp_redirect($checkout_url);
            exit;
        }
    }

    /**
     * NOTIFICATION - Endpoint for Json notification
     * @hooked woocommerce_api_wcpagantisgateway
     */
    public function pagantisNotification()
    {
        try {
            $origin = ($_SERVER['REQUEST_METHOD'] == 'POST') ? 'Notify' : 'Order';

            include_once('notifyController.php');
            $notify = new WcPagantisNotify();
            $notify->setOrigin($origin);
            /** @var \Pagantis\ModuleUtils\Model\Response\AbstractJsonResponse $result */
            $result = $notify->processInformation();
        } catch (Exception $exception) {
            $result['notification_message'] = $exception->getMessage();
            $result['notification_error'] = true;
        }

        $paymentOrder = new WC_Order($result->getMerchantOrderId());
        if ($paymentOrder instanceof WC_Order) {
            $orderStatus = strtolower($paymentOrder->get_status());
        } else {
            $orderStatus = 'cancelled';
        }
        $acceptedStatus = array('processing', 'completed');
        if (in_array($orderStatus, $acceptedStatus)) {
            $returnUrl = $this->getOkUrl($paymentOrder);
        } else {
            $returnUrl = $this->getKoUrl($paymentOrder);
        }

        wp_redirect($returnUrl);
        exit;
    }

    /**
     * After failed status, set to processing not complete -> Hook: woocommerce_payment_complete_order_status
     * @param $status
     * @param $order_id
     * @param $order
     *
     * @return string
     */
    public function pagantisCompleteStatus($status, $order_id, $order)
    {
        if ($order->get_payment_method() == WcPagantisGateway::METHOD_ID) {
            if ($order->get_status() == 'failed') {
                $status = 'processing';
            } elseif ($order->get_status() == 'pending' && $status=='completed') {
                $status = 'processing';
            }
        }

        return $status;
    }

    /***********
     *
     * REDEFINED FUNCTIONS
     *
     ***********/

    /**
     * CHECKOUT - Check if payment method is available
     * @see WC_Payment_Gateway::is_available()
     * @return bool
     */
    public function is_available()
    {
        $locale = strtolower(strstr(get_locale(), '_', true));
        $allowedCountries = unserialize($this->extraConfig['PAGANTIS_ALLOWED_COUNTRIES']);
        $allowedCountry = (in_array(strtolower($locale), $allowedCountries));
        $minAmount = $this->extraConfig['PAGANTIS_DISPLAY_MIN_AMOUNT'];
        $maxAmount = $this->extraConfig['PAGANTIS_DISPLAY_MAX_AMOUNT'];
        $totalPrice = (int)$this->get_order_total();
        $validAmount = ($totalPrice>=$minAmount && ($totalPrice<=$maxAmount || $maxAmount=='0'));
        if ($this->enabled==='yes' && $this->pagantis_public_key!='' && $this->pagantis_private_key!='' &&
            $validAmount && $allowedCountry) {
            return true;
        }

        return false;
    }

    /**
     * CHECKOUT - Checkout + admin panel title(method_title - get_title) (called by woocommerce,can't apply camel caps)
     * @see WC_Payment_Gateway::get_title()
     * @return string
     */
    public function get_title()
    {
        return __($this->extraConfig['PAGANTIS_TITLE'], 'pagantis');
    }

    /**
     * CHECKOUT - Called after push pagantis button on checkout
     * @param $order_id
     *
     * @return array
     * @see WC_Payment_Gateway::process_payment()
     */
    public function process_payment($order_id)
    {
        try {
            $order = new WC_Order($order_id);

            $redirectUrl = $order->get_checkout_payment_url(true); //pagantisReceiptPage function
            if (strpos($redirectUrl, 'order-pay=')===false) {
                $redirectUrl.="&order-pay=".$order_id;
            }

            return array(
                'result'   => 'success',
                'redirect' => $redirectUrl
            );
        } catch (Exception $e) {
            wc_add_notice(__('Payment error ', 'pagantis') . $e->getMessage(), 'error');
            return array();
        }
    }

    /**
     * CHECKOUT - simulator
     * @see WC_Payment_Gateway::payment_fields()
     */
    public function payment_fields()
    {
        $locale = strtolower(strstr(get_locale(), '_', true));
        $allowedCountries = unserialize($this->extraConfig['PAGANTIS_ALLOWED_COUNTRIES']);
        $allowedCountry = (in_array(strtolower($locale), $allowedCountries));
        $promotedAmount = getPromotedAmount();

        $template_fields = array(
            'public_key' => $this->pagantis_public_key,
            'total' => WC()->session->cart_totals['total'],
            'enabled' =>  $this->settings['enabled'],
            'min_installments' => $this->extraConfig['PAGANTIS_DISPLAY_MIN_AMOUNT'],
            'max_installments' => $this->extraConfig['PAGANTIS_DISPLAY_MAX_AMOUNT'],
            'simulator_enabled' => $this->settings['simulator'],
            'locale' => $locale,
            'country' => $locale,
            'allowed_country' => $allowedCountry,
            'simulator_type' => $this->extraConfig['PAGANTIS_SIMULATOR_DISPLAY_TYPE_CHECKOUT'],
            'promoted_amount' => $promotedAmount,
            'thousandSeparator' => $this->extraConfig['PAGANTIS_SIMULATOR_THOUSANDS_SEPARATOR'],
            'decimalSeparator' => $this->extraConfig['PAGANTIS_SIMULATOR_DECIMAL_SEPARATOR'],
            'pagantisSimulatorSkin' => $this->extraConfig['PAGANTIS_SIMULATOR_DISPLAY_SKIN']
        );



        wc_get_template('checkout_description.php', $template_fields, '', $this->template_path);
    }

    /***********
     *
     * UTILS FUNCTIONS
     *
     ***********/

    /**
     * PANEL KO_URL FIELD
     * CHECKOUT PAGE => ?page_id=91 // ORDER-CONFIRMATION PAGE => ?page_id=91&order-pay=<order_id>&key=<order_key>
     */
    private function generateOkUrl()
    {
        return $this->generateUrl($this->get_return_url());
    }

    /**
     * PANEL OK_URL FIELD
     */
    private function generateKoUrl()
    {
        return $this->generateUrl(get_permalink(wc_get_page_id('checkout')));
    }

    /**
     * Replace empty space by {{var}}
     * @param $url
     *
     * @return string
     */
    private function generateUrl($url)
    {
        $parsed_url = parse_url($url);
        if ($parsed_url !== false) {
            $parsed_url['query'] = !isset($parsed_url['query']) ? '' : $parsed_url['query'];
            parse_str($parsed_url['query'], $arrayParams);
            foreach ($arrayParams as $keyParam => $valueParam) {
                if ($valueParam=='') {
                    $arrayParams[$keyParam] = '{{'.$keyParam.'}}';
                }
            }
            $parsed_url['query'] = http_build_query($arrayParams);
            $return_url = $this->unparseUrl($parsed_url);
            return urldecode($return_url);
        } else {
            return $url;
        }
    }

    /**
     * Replace {{}} by vars values inside ok_url
     * @param $order
     *
     * @return string
     */
    private function getOkUrl($order)
    {
        return $this->getKeysUrl($order, $this->ok_url);
    }

    /**
     * Replace {{}} by vars values inside ko_url
     * @param $order
     *
     * @return string
     */
    private function getKoUrl($order)
    {
        return $this->getKeysUrl($order, $this->ko_url);
    }

    /**
     * Replace {{}} by vars values
     * @param $order
     * @param $url
     *
     * @return string
     */
    private function getKeysUrl($order, $url)
    {
        $defaultFields = (get_class($order)=='WC_Order') ?
            array('order-received'=>$order->get_id(), 'key'=>$order->get_order_key()) :
            array();

        $parsedUrl = parse_url($url);
        if ($parsedUrl !== false) {
            //Replace parameters from url
            $parsedUrl['query'] = $this->getKeysParametersUrl($parsedUrl['query'], $defaultFields);

            //Replace path from url
            $parsedUrl['path'] = $this->getKeysPathUrl($parsedUrl['path'], $defaultFields);

            $returnUrl = $this->unparseUrl($parsedUrl);
            return $returnUrl;
        }
        return $url;
    }

    /**
     * Replace {{}} by vars values inside parameters
     * @param $queryString
     * @param $defaultFields
     *
     * @return string
     */
    private function getKeysParametersUrl($queryString, $defaultFields)
    {
        parse_str(html_entity_decode($queryString), $arrayParams);
        $commonKeys = array_intersect_key($arrayParams, $defaultFields);
        if (count($commonKeys)) {
            $arrayResult = array_merge($arrayParams, $defaultFields);
        } else {
            $arrayResult = $arrayParams;
        }
        return urldecode(http_build_query($arrayResult));
    }

    /**
     * Replace {{}} by vars values inside path
     * @param $pathString
     * @param $defaultFields
     *
     * @return string
     */
    private function getKeysPathUrl($pathString, $defaultFields)
    {
        $arrayParams = explode("/", $pathString);
        foreach ($arrayParams as $keyParam => $valueParam) {
            preg_match('#\{{.*?}\}#', $valueParam, $match);
            if (count($match)) {
                $key = str_replace(array('{{','}}'), array('',''), $match[0]);
                $arrayParams[$keyParam] = $defaultFields[$key];
            }
        }
        return implode('/', $arrayParams);
    }

    /**
     * Replace {{var}} by empty space
     * @param $parsed_url
     *
     * @return string
     */
    private function unparseUrl($parsed_url)
    {
        $scheme   = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
        $host     = isset($parsed_url['host']) ? $parsed_url['host'] : '';
        $port     = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
        $query    = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
        $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
        $path     = $parsed_url['path'];
        return $scheme . $host . $port . $path . $query . $fragment;
    }
}
