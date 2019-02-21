<?php

//namespace empty
use PagaMasTarde\OrdersApiClient\Model\Order\User\Address;
use PagaMasTarde\ModuleUtils\Exception\OrderNotFoundException;

if (!defined('ABSPATH')) {
    exit;
}

define('__ROOT__', dirname(dirname(__FILE__)));

class WcPaylaterGateway extends WC_Payment_Gateway
{
    const METHOD_ID             = "paylater";
    const METHOD_TITLE          = "Paga Más Tarde";
    const METHOD_ABREV          = "Paga+Tarde";
    const PAGA_MAS_TARDE        = 'pagamastarde';
    const PAYLATER_SHOPPER_URL  = 'https://shopper.pagamastarde.com/woocommerce/';

    /** Orders tablename */
    const ORDERS_TABLE = 'cart_process';

    /** Concurrency tablename */
    const LOGS_TABLE = 'pmt_logs';

    const NOT_CONFIRMED = 'No se ha podido confirmar el pago';

    /**
     * WcPaylaterGateway constructor.
     */
    public function __construct()
    {
        //Mandatory vars for plugin
        $this->id = WcPaylaterGateway::METHOD_ID;
        $this->icon = esc_url(plugins_url('../assets/images/logo.png', __FILE__));
        $this->has_fields = true;
        $this->method_title = WcPaylaterGateway::METHOD_TITLE;
        $this->title = WcPaylaterGateway::METHOD_TITLE;

        //Useful vars
        $this->template_path = plugin_dir_path(__FILE__) . '../templates/';
        $this->allowed_currencies = array("EUR");
        $this->allowed_languages  = array("es_ES");
        $this->mainFileLocation = dirname(plugin_dir_path(__FILE__)) . '/WC_Paylater.php';
        $this->plugin_info = get_file_data($this->mainFileLocation, array('Version' => 'Version'), false);

        //Panel form fields
        $this->form_fields = include(plugin_dir_path(__FILE__).'../includes/settings-paylater.php');//Panel options
        $this->init_settings();

        $this->settings['ok_url'] = (getenv('PMT_URL_OK')!='')?getenv('PMT_URL_OK'):$this->generateOkUrl();
        $this->settings['ko_url'] = (getenv('PMT_URL_KO')!='')?getenv('PMT_URL_KO'):$this->generateKoUrl();
        foreach ($this->settings as $setting_key => $setting_value) {
            $this->$setting_key = $setting_value;
        }

        //Hooks
        add_action('woocommerce_update_options_payment_gateways_'.$this->id, array($this,'process_admin_options')); //Save plugin options
        add_action('admin_notices', array($this, 'paylaterCheckFields'));                          //Check config fields
        add_action('woocommerce_receipt_'.$this->id, array($this, 'paylaterReceiptPage'));          //Pmt form
        add_action('woocommerce_api_wcpaylatergateway', array($this, 'paylaterNotification'));      //Json Notification
        add_filter('woocommerce_payment_complete_order_status', array($this,'paylaterCompleteStatus'), 10, 3);
    }

    /***********
     *
     * HOOKS
     *
     ***********/

    /**
     * PANEL - Display admin panel -> Hook: woocommerce_update_options_payment_gateways_paylater
     */
    public function admin_options()
    {
        $template_fields = array(
            'panel_header' => $this->title,
            'panel_description' => $this->method_description,
            'button1_label' => __('Login al panel de ', 'paylater') . WcPaylaterGateway::METHOD_TITLE,
            'button2_label' => __('Documentación', 'paylater'),
            'logo' => $this->icon,
            'settings' => $this->generate_settings_html($this->form_fields, false)
        );
        wc_get_template('admin_header.php', $template_fields, '', $this->template_path);
    }

    /**
     * PANEL - Check admin panel fields -> Hook: admin_notices
     */
    public function paylaterCheckFields()
    {
        $error_string = '';
        if ($this->settings['enabled'] !== 'yes') {
            return;
        } elseif (!version_compare(phpversion(), '5.3.0', '>=')) {
            $error_string =  __(' no es compatible con su versión de php y/o curl', 'paylater');
            $this->settings['enabled'] = 'no';
        } elseif ($this->settings['pmt_public_key']=="" || $this->settings['pmt_private_key']=="") {
            $keys_error =  <<<EOD
no está configurado correctamente, los campos Public Key y Secret Key son obligatorios para su funcionamiento
EOD;
            $error_string = __($keys_error, 'paylater');
            $this->settings['enabled'] = 'no';
        } elseif (!in_array(get_woocommerce_currency(), $this->allowed_currencies)) {
            $error_string =  __(' solo puede ser usado en Euros', 'paylater');
            $this->settings['enabled'] = 'no';
        } elseif (!in_array(get_locale(), $this->allowed_languages)) {
            $error_string = __(' solo puede ser usado en Español', 'paylater');
            $this->settings['enabled'] = 'no';
        } elseif (getenv('PMT_SIMULATOR_MAX_INSTALLMENTS')<'2'
                  || getenv('PMT_SIMULATOR_MAX_INSTALLMENTS')>'12') {
            $error_string = __(' solo puede ser pagado de 2 a 12 plazos.', 'paylater');
        } elseif (getenv('PMT_SIMULATOR_START_INSTALLMENTS')<'2'
                  || getenv('PMT_SIMULATOR_START_INSTALLMENTS')>'12') {
            $error_string = __(' solo puede ser pagado de 2 a 12 plazos.', 'paylater');
        } elseif (getenv('PMT_DISPLAY_MIN_AMOUNT')<0) {
            $error_string = __(' el importe debe ser mayor a 0.', 'paylater');
        }

        if ($error_string!='') {
            $template_fields = array(
                'error_msg' => WcPaylaterGateway::METHOD_TITLE .' '.$error_string,
            );
            wc_get_template('error_msg.php', $template_fields, '', $this->template_path);
        }
    }


    /**
     * CHECKOUT - Generate the pmt form. "Return" iframe or redirect. - Hook: woocommerce_receipt_paylater
     * @param $order_id
     *
     * @throws Exception
     */
    public function paylaterReceiptPage($order_id)
    {
        try {
            require_once(__ROOT__.'/vendor/autoload.php');
            global $woocommerce;
            $order = new WC_Order($order_id);

            if (!isset($order)) {
                throw new Exception(_("Order not found"));
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
            $orderUser = new \PagaMasTarde\OrdersApiClient\Model\Order\User();
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
                $orderHistory = new \PagaMasTarde\OrdersApiClient\Model\Order\User\OrderHistory();
                $orderElement = wc_get_order($previousOrder);
                $orderCreated = $orderElement->get_date_created();
                $orderHistory
                    ->setAmount(intval(100 * $orderElement->get_total()))
                    ->setDate(new \DateTime($orderCreated->date('Y-m-d H:i:s')))
                ;
                $orderUser->addOrderHistory($orderHistory);
            }

            $details = new \PagaMasTarde\OrdersApiClient\Model\Order\ShoppingCart\Details();
            $shippingCost = $order->shipping_total;
            $details->setShippingCost(intval(strval(100 * $shippingCost)));
            $items = $woocommerce->cart->get_cart();
            foreach ($items as $key => $item) {
                $product = new \PagaMasTarde\OrdersApiClient\Model\Order\ShoppingCart\Details\Product();
                $product
                    ->setAmount(intval(100 * $item['line_total']))
                    ->setQuantity($item['quantity'])
                    ->setDescription($item['data']->get_description());
                $details->addProduct($product);
            }

            $orderShoppingCart = new \PagaMasTarde\OrdersApiClient\Model\Order\ShoppingCart();
            $orderShoppingCart
                ->setDetails($details)
                ->setOrderReference($order->get_id())
                ->setPromotedAmount(0)
                ->setTotalAmount(intval(strval(100 * $order->total)))
            ;
            $orderConfigurationUrls = new \PagaMasTarde\OrdersApiClient\Model\Order\Configuration\Urls();
            $cancelUrl = $this->getKoUrl($order);
            $callback_arg = array(
                'wc-api'=>'wcpaylatergateway',
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
            $orderChannel = new \PagaMasTarde\OrdersApiClient\Model\Order\Configuration\Channel();
            $orderChannel
                ->setAssistedSale(false)
                ->setType(\PagaMasTarde\OrdersApiClient\Model\Order\Configuration\Channel::ONLINE)
            ;
            $orderConfiguration = new \PagaMasTarde\OrdersApiClient\Model\Order\Configuration();
            $orderConfiguration
                ->setChannel($orderChannel)
                ->setUrls($orderConfigurationUrls)
            ;
            $metadataOrder = new \PagaMasTarde\OrdersApiClient\Model\Order\Metadata();
            $metadata = array(
                'woocommerce' => WC()->version,
                'pmt'         => $this->plugin_info['Version'],
                'php'         => phpversion()
            );
            foreach ($metadata as $key => $metadatum) {
                $metadataOrder->addMetadata($key, $metadatum);
            }
            $orderApiClient = new \PagaMasTarde\OrdersApiClient\Model\Order();
            $orderApiClient
                ->setConfiguration($orderConfiguration)
                ->setMetadata($metadataOrder)
                ->setShoppingCart($orderShoppingCart)
                ->setUser($orderUser)
            ;

            if ($this->pmt_public_key=='' || $this->pmt_private_key=='') {
                throw new \Exception('Public and Secret Key not found');
            }
            $orderClient = new \PagaMasTarde\OrdersApiClient\Client($this->pmt_public_key, $this->pmt_private_key);
            $pmtOrder = $orderClient->createOrder($orderApiClient);
            if ($pmtOrder instanceof \PagaMasTarde\OrdersApiClient\Model\Order) {
                $url = $pmtOrder->getActionUrls()->getForm();
                $this->insertRow($order->get_id(), $pmtOrder->getId());
            } else {
                throw new OrderNotFoundException();
            }

            if ($url=="") {
                throw new Exception(_("No ha sido posible obtener una respuesta de PagaMasTarde"));
            } elseif ($this->iframe !== 'true') {
                wp_redirect($url);
                exit;
            } else {
                $template_fields = array(
                    'css' => 'https://shopper.pagamastarde.com/css/paylater-modal.min.css',
                    'prestashopCss' => 'https://shopper.pagamastarde.com/css/paylater-prestashop.min.css',
                    'url' => $url,
                    'spinner' => esc_url(plugins_url('../assets/images/spinner.gif', __FILE__)),
                    'checkoutUrl'   => $cancelUrl
                );
                wc_get_template('iframe.php', $template_fields, '', $this->template_path);
            }
        } catch (\Exception $exception) {
            wc_add_notice(__('Error en el pago - ', 'paylater') . $exception->getMessage(), 'error');
            $checkout_url = get_permalink(wc_get_page_id('checkout'));
            wp_redirect($checkout_url);
            exit;
        }
    }

    /**
     * NOTIFICATION - Endpoint for Json notification - Hook: woocommerce_api_wcpaylatergateway
     */
    public function paylaterNotification()
    {
        try {
            $origin = ($_SERVER['REQUEST_METHOD'] == 'POST') ? 'Notify' : 'Order';

            include_once('notifyController.php');
            $notify = new WcPaylaterNotify();
            $notify->setOrigin($origin);
            /** @var \PagaMasTarde\ModuleUtils\Model\Response\AbstractJsonResponse $result */
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
    public function paylaterCompleteStatus($status, $order_id, $order)
    {
        if ($order->get_payment_method() == WcPaylaterGateway::METHOD_ID) {
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
        if ($this->enabled==='yes' && $this->pmt_public_key!='' && $this->pmt_private_key!='' &&
            $this->get_order_total()>getenv('PMT_DISPLAY_MIN_AMOUNT')) {
            return true;
        }

        return false;
    }

    /**
     * CHECKOUT - Get the checkout title (called by woocommerce, can't apply cammel caps)
     * @return string
     */
    public function get_title()
    {
        return getenv('PMT_TITLE');
    }

    /**
     * CHECKOUT - Called after push pmt button on checkout(called by woocommerce, can't apply cammel caps
     * @param $order_id
     * @return array
     */
    public function process_payment($order_id)
    {
        try {
            $order = new WC_Order($order_id);

            $redirectUrl = $order->get_checkout_payment_url(true); //paylaterReceiptPage function
            if (strpos($redirectUrl, 'order-pay=')===false) {
                $redirectUrl = "&order-pay=".$order->getId();
            }

            return array(
                'result'   => 'success',
                'redirect' => $redirectUrl
            );

        } catch (Exception $e) {
            wc_add_notice(__('Error en el pago ', 'paylater') . $e->getMessage(), 'error');
            return array();
        }
    }

    /**
     * CHECKOUT - simulator (called by woocommerce, can't apply cammel caps)
     */
    public function payment_fields()
    {

        $template_fields = array(
            'public_key' => $this->pmt_public_key,
            'total' => WC()->session->cart_totals['total'],
            'enabled' =>  $this->settings['enabled'],
            'min_installments' => getenv('PMT_DISPLAY_MIN_AMOUNT'),
            'message' => getenv('PMT_TITLE_EXTRA')
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
     * @param $pmtOrderId
     *
     * @throws Exception
     */
    private function insertRow($orderId, $pmtOrderId)
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
                array('id' => $orderId, 'order_id' => $pmtOrderId),
                array('%d', '%s')
            );
        } else {
            $wpdb->update(
                $tableName,
                array('order_id' => $pmtOrderId),
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
}
