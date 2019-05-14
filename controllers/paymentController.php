<?php

//namespace empty
use Pagantis\ModuleUtils\Exception\OrderNotFoundException;
use Pagantis\OrdersApiClient\Model\Order\User\Address;

if (!defined('ABSPATH')) {
    exit;
}

define('__ROOT__', dirname(dirname(__FILE__)));


class WcPagantisGateway extends WC_Payment_Gateway
{
    const METHOD_ID = "pagantis";

    /** Orders tablename */
    const ORDERS_TABLE = 'cart_process';

    /** Concurrency tablename */
    const LOGS_TABLE = 'pagantis_logs';

    const NOT_CONFIRMED = 'No se ha podido confirmar el pago';

    const CONFIG_TABLE = 'pagantis_config';

    /** @var Array $extraConfig */
    public $extraConfig;

    /**
     * WcPagantisGateway constructor.
     */
    public function __construct()
    {
        //Mandatory vars for plugin
        $this->id = WcPagantisGateway::METHOD_ID;
        $this->icon = esc_url(plugins_url('../assets/images/logo.png', __FILE__));
        $this->has_fields = true;
        $this->method_title = ucfirst($this->id);
        $this->title = $this->extraConfig['PAGANTIS_TITLE'];

        //Useful vars
        $this->template_path = plugin_dir_path(__FILE__) . '../templates/';
        $this->allowed_currencies = array("EUR");
        $this->mainFileLocation = dirname(plugin_dir_path(__FILE__)) . '/WC_Pagantis.php';
        $this->plugin_info = get_file_data($this->mainFileLocation, array('Version' => 'Version'), false);

        //Panel form fields
        $this->form_fields = include(plugin_dir_path(__FILE__).'../includes/settings-pagantis.php');//Panel options
        $this->init_settings();

        $this->extraConfig = $this->getExtraConfig();

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
     * PANEL - Display admin panel -> Hook: woocommerce_update_options_payment_gateways_pagantis
     */
    public function admin_options()
    {
        $template_fields = array(
            'panel_header' => $this->title,
            'panel_description' => $this->method_description,
            'button1_label' => __('Login to panel of ', 'pagantis') . ucfirst(WcPagantisGateway::METHOD_ID),
            'button2_label' => __('Documentation', 'pagantis'),
            'logo' => $this->icon,
            'settings' => $this->generate_settings_html($this->form_fields, false)
        );
        wc_get_template('admin_header.php', $template_fields, '', $this->template_path);
    }

    /**
     * PANEL - Check admin panel fields -> Hook: admin_notices
     */
    public function pagantisCheckFields()
    {
        $error_string = '';
        if ($this->settings['enabled'] !== 'yes') {
            return;
        } elseif (!version_compare(phpversion(), '5.3.0', '>=')) {
            $error_string =  __(' is not compatible with your php and/or curl version', 'pagantis');
            $this->settings['enabled'] = 'no';
        } elseif ($this->settings['pagantis_public_key']=="" || $this->settings['pagantis_private_key']=="") {
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
     * CHECKOUT - Generate the pagantis form. "Return" iframe or redirect. - Hook: woocommerce_receipt_pagantis
     * @param $order_id
     *
     * @throws Exception
     */
    public function pagantisReceiptPage($order_id)
    {
        try {
            require_once(__ROOT__.'/vendor/autoload.php');
            global $woocommerce;
            $order = new WC_Order($order_id);
            $order->set_payment_method(ucfirst($this->id)); //Method showed in confirmation page.
            $order->save();

            if (!isset($order)) {
                throw new Exception(_("Order not found"));
            }

            $national_id = "";
            $tax_id = "";
            foreach ((array)$order->get_meta_data() as $mdObject) {
                $data = $mdObject->get_data();
                if ($data['key'] == 'vat_number') {
                    $national_id = $data['value'];
                } elseif ($data['key'] == 'billing_cfpiva') {
                    $tax_id = $data['value'];
                }
            }

            $shippingAddress = $order->get_address('shipping');
            $billingAddress = $order->get_address('billing');
            if ($shippingAddress['address_1'] == '') {
                $shippingAddress = $billingAddress;
            }

            $userAddress = new Address();
            $userAddress
                ->setZipCode($shippingAddress['postcode'])
                ->setFullName($shippingAddress['fist_name']." ".$shippingAddress['last_name'])
                ->setCountryCode('ES')
                ->setCity($shippingAddress['city'])
                ->setAddress($shippingAddress['address_1']." ".$shippingAddress['address_2'])
            ;
            $orderShippingAddress = new Address();
            $orderShippingAddress
                ->setZipCode($shippingAddress['postcode'])
                ->setFullName($shippingAddress['fist_name']." ".$shippingAddress['last_name'])
                ->setCountryCode('ES')
                ->setCity($shippingAddress['city'])
                ->setAddress($shippingAddress['address_1']." ".$shippingAddress['address_2'])
                ->setFixPhone($shippingAddress['phone'])
                ->setMobilePhone($shippingAddress['phone'])
            ;
            $orderBillingAddress =  new Address();
            $orderBillingAddress
                ->setZipCode($billingAddress['postcode'])
                ->setFullName($billingAddress['fist_name']." ".$billingAddress['last_name'])
                ->setCountryCode('ES')
                ->setCity($billingAddress['city'])
                ->setAddress($billingAddress['address_1']." ".$billingAddress['address_2'])
                ->setFixPhone($billingAddress['phone'])
                ->setMobilePhone($billingAddress['phone'])
            ;
            $orderUser = new \Pagantis\OrdersApiClient\Model\Order\User();
            $orderUser
                ->setAddress($userAddress)
                ->setFullName($billingAddress['fist_name']." ".$billingAddress['last_name'])
                ->setBillingAddress($orderBillingAddress)
                ->setEmail($billingAddress['email'])
                ->setFixPhone($billingAddress['phone'])
                ->setMobilePhone($billingAddress['phone'])
                ->setShippingAddress($orderShippingAddress)
            ;

            $previousOrders = $this->getOrders($order->get_user(), $billingAddress['email']);
            foreach ($previousOrders as $previousOrder) {
                $orderHistory = new \Pagantis\OrdersApiClient\Model\Order\User\OrderHistory();
                $orderElement = wc_get_order($previousOrder);
                $orderCreated = $orderElement->get_date_created();
                $orderHistory
                    ->setAmount(intval(100 * $orderElement->get_total()))
                    ->setDate(new \DateTime($orderCreated->date('Y-m-d H:i:s')))
                ;
                $orderUser->addOrderHistory($orderHistory);
            }

            $details = new \Pagantis\OrdersApiClient\Model\Order\ShoppingCart\Details();
            $shippingCost = $order->shipping_total;
            $details->setShippingCost(intval(strval(100 * $shippingCost)));
            $items = $woocommerce->cart->get_cart();
            foreach ($items as $key => $item) {
                $product = new \Pagantis\OrdersApiClient\Model\Order\ShoppingCart\Details\Product();
                $product
                    ->setAmount(intval(100 * $item['line_total']))
                    ->setQuantity($item['quantity'])
                    ->setDescription($item['data']->get_description());
                $details->addProduct($product);
            }

            $orderShoppingCart = new \Pagantis\OrdersApiClient\Model\Order\ShoppingCart();
            $orderShoppingCart
                ->setDetails($details)
                ->setOrderReference($order->get_id())
                ->setPromotedAmount(0)
                ->setTotalAmount(intval(strval(100 * $order->total)))
            ;
            $orderConfigurationUrls = new \Pagantis\OrdersApiClient\Model\Order\Configuration\Urls();
            $cancelUrl = $this->getKoUrl($order);
            $callback_arg = array(
                'wc-api'=>'wcpagantisgateway',
                'key'=>$order->get_order_key(),
                'order-received'=>$order->get_id());
            $callback_url = add_query_arg($callback_arg, home_url('/'));
            $orderConfigurationUrls
                ->setCancel($cancelUrl)
                ->setKo($callback_url)
                ->setAuthorizedNotificationCallback($callback_url)
                ->setRejectedNotificationCallback($callback_url)
                ->setOk($callback_url)
            ;
            $orderChannel = new \Pagantis\OrdersApiClient\Model\Order\Configuration\Channel();
            $orderChannel
                ->setAssistedSale(false)
                ->setType(\Pagantis\OrdersApiClient\Model\Order\Configuration\Channel::ONLINE)
            ;
            $orderConfiguration = new \Pagantis\OrdersApiClient\Model\Order\Configuration();
            $orderConfiguration
                ->setChannel($orderChannel)
                ->setUrls($orderConfigurationUrls)
            ;
            $metadataOrder = new \Pagantis\OrdersApiClient\Model\Order\Metadata();
            $metadata = array(
                'woocommerce' => WC()->version,
                'pagantis'         => $this->plugin_info['Version'],
                'php'         => phpversion()
            );
            foreach ($metadata as $key => $metadatum) {
                $metadataOrder->addMetadata($key, $metadatum);
            }
            $orderApiClient = new \Pagantis\OrdersApiClient\Model\Order();
            $orderApiClient
                ->setConfiguration($orderConfiguration)
                ->setMetadata($metadataOrder)
                ->setShoppingCart($orderShoppingCart)
                ->setUser($orderUser)
            ;

            if ($this->pagantis_public_key=='' || $this->pagantis_private_key=='') {
                throw new \Exception('Public and Secret Key not found');
            }
            $orderClient = new \Pagantis\OrdersApiClient\Client($this->pagantis_public_key, $this->pagantis_private_key);
            $pagantisOrder = $orderClient->createOrder($orderApiClient);
            if ($pagantisOrder instanceof \Pagantis\OrdersApiClient\Model\Order) {
                $url = $pagantisOrder->getActionUrls()->getForm();
                $this->insertRow($order->get_id(), $pagantisOrder->getId());
            } else {
                throw new OrderNotFoundException();
            }

            if ($url=="") {
                throw new Exception(_("No ha sido posible obtener una respuesta de Pagantis"));
            } elseif ($this->extraConfig['PAGANTIS_FORM_DISPLAY_TYPE']=='0') {
                wp_redirect($url);
                exit;
            } else {
                $template_fields = array(
                    'url' => $url,
                    'checkoutUrl'   => $cancelUrl
                );
                wc_get_template('iframe.php', $template_fields, '', $this->template_path);
            }
        } catch (\Exception $exception) {
            wc_add_notice(__('Payment error ', 'pagantis') . $exception->getMessage(), 'error');
            $checkout_url = get_permalink(wc_get_page_id('checkout'));
            wp_redirect($checkout_url);
            exit;
        }
    }

    /**
     * NOTIFICATION - Endpoint for Json notification - Hook: woocommerce_api_wcpagantisgateway
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
     * CHECKOUT - Check if payment method is available (called by woocommerce, can't apply cammel caps)
     * @return bool
     */
    public function is_available()
    {
        if ($this->enabled==='yes' && $this->pagantis_public_key!='' && $this->pagantis_private_key!='' &&
            (int)$this->get_order_total()>$this->extraConfig['PAGANTIS_DISPLAY_MIN_AMOUNT']) {
            return true;
        }

        return false;
    }

    /**
     * CHECKOUT - Checkout + admin panel title(method_title - get_title) (called by woocommerce,can't apply cammel caps)
     * @return string
     */
    public function get_title()
    {
        return $this->extraConfig['PAGANTIS_TITLE'];
    }

    /**
     * CHECKOUT - Called after push pagantis button on checkout(called by woocommerce, can't apply cammel caps
     * @param $order_id
     * @return array
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
     * CHECKOUT - simulator (called by woocommerce, can't apply cammel caps)
     */
    public function payment_fields()
    {
        $template_fields = array(
            'public_key' => $this->pagantis_public_key,
            'total' => WC()->session->cart_totals['total'],
            'enabled' =>  $this->settings['enabled'],
            'min_installments' => $this->extraConfig['PAGANTIS_DISPLAY_MIN_AMOUNT'],
            'message' => __($this->extraConfig['PAGANTIS_TITLE_EXTRA'])
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

    /**
     * Get the orders of a customer
     * @param $current_user
     * @param $billingEmail
     *
     * @return mixed
     */
    private function getOrders($current_user, $billingEmail)
    {
        $sign_up = '';
        $total_orders = 0;
        $total_amt = 0;
        $refund_amt = 0;
        $total_refunds = 0;
        $partial_refunds = 0;
        if ($current_user->user_login) {
            $is_guest = "false";
            $sign_up = substr($current_user->user_registered, 0, 10);
            $customer_orders = get_posts(array(
                'numberposts' => - 1,
                'meta_key'    => '_customer_user',
                'meta_value'  => $current_user->ID,
                'post_type'   => array( 'shop_order' ),
                'post_status' => array( 'wc-completed', 'wc-processing', 'wc-refunded' ),
            ));
        } else {
            $is_guest = "true";
            $customer_orders = get_posts(array(
                'numberposts' => - 1,
                'meta_key'    => '_billing_email',
                'meta_value'  => $billingEmail,
                'post_type'   => array( 'shop_order' ),
                'post_status' => array( 'wc-completed', 'wc-processing', 'wc-refunded'),
            ));
            foreach ($customer_orders as $customer_order) {
                if (trim($sign_up)=='' ||
                    strtotime(substr($customer_order->post_date, 0, 10)) <= strtotime($sign_up)) {
                    $sign_up = substr($customer_order->post_date, 0, 10);
                }
            }
        }

        return $customer_orders;
    }


    /**
     * @param $orderId
     * @param $pagantisOrderId
     *
     * @throws Exception
     */
    private function insertRow($orderId, $pagantisOrderId)
    {
        global $wpdb;
        $this->checkDbTable();
        $tableName = $wpdb->prefix.self::ORDERS_TABLE;

        //Check if id exists
        $resultsSelect = $wpdb->get_results("select * from $tableName where id='$orderId'");
        $countResults = count($resultsSelect);
        if ($countResults == 0) {
            $wpdb->insert(
                $tableName,
                array('id' => $orderId, 'order_id' => $pagantisOrderId),
                array('%d', '%s')
            );
        } else {
            $wpdb->update(
                $tableName,
                array('order_id' => $pagantisOrderId),
                array('id' => $orderId),
                array('%s'),
                array('%d')
            );
        }
    }

    /**
     * Check if orders table exists
     */
    private function checkDbTable()
    {
        global $wpdb;
        $tableName = $wpdb->prefix.self::ORDERS_TABLE;

        if ($wpdb->get_var("SHOW TABLES LIKE '$tableName'") != $tableName) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql             = "CREATE TABLE $tableName ( id int, order_id varchar(50), wc_order_id varchar(50),  
                  UNIQUE KEY id (id)) $charset_collate";

            require_once(ABSPATH.'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }

    /**
     * @return array
     */
    private function getExtraConfig()
    {
        global $wpdb;
        $tableName = $wpdb->prefix.self::CONFIG_TABLE;
        $response = array();
        $dbResult = $wpdb->get_results("select config, value from $tableName", ARRAY_A);
        foreach ($dbResult as $value) {
            $response[$value['config']] = $value['value'];
        }

        return $response;
    }
}
