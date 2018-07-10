<?php

//namespace empty

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

        $this->settings['ok_url'] = ($this->settings['ok_url']!='')?$this->settings['ok_url']:$this->generateOkUrl();
        $this->settings['ko_url'] = ($this->settings['ko_url']!='')?$this->settings['ko_url']:$this->generateKoUrl();
        foreach ($this->settings as $setting_key => $setting_value) {
            $this->$setting_key = $setting_value;
        }
        $this->method_description = $this->checkout_title;

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
        } elseif ($this->settings['public_key']=="" || $this->settings['secret_key']=="") {
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
        } elseif ($this->min_installments<2 ||  $this->min_installments>12 ||
                  $this->max_installments<2 ||  $this->max_installments>12 ) {
            $error_string = __(' solo puede ser pagado de 2 a 12 plazos.', 'paylater');
            $this->settings['min_installments'] = 2;
            $this->settings['max_installments'] = 12;
        } elseif ($this->min_amount<0 || $this->max_amount<0) {
            $error_string = __(' el importe debe ser mayor a 0.', 'paylater');
            $this->settings['min_amount'] = 0;
            $this->settings['max_amount'] = 10000;
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

            $cart = $woocommerce->cart->get_cart();
            foreach ($cart as $item => $values) {
                $cart[$item]['product_name'] = $values['data']->get_name();
            }

            $currency = $order->get_currency();
            $cancelUrl = $this->getKoUrl($order);
            $include_simulator = ($this->simulator_checkout !== '0') ? '1' : '0';
            $callback_arg = array(
                'wc-api'=>'wcpaylatergateway',
                'key'=>$order->get_order_key(),
                'order-received'=>$order->get_id());
            $callback_url = add_query_arg($callback_arg, home_url('/'));

            $current_user = $order->get_user();
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
                    'meta_value'  => $order->billing_email,
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
            foreach ($customer_orders as $customer_order) {
                $tmp_order = wc_get_order($customer_order);
                $total_amt += $tmp_order->get_total();
                $total_orders++;
                $tmp_order = wc_get_order($customer_order);
                $refund_amt += $tmp_order->get_total_refunded();
                if ($tmp_order->get_total_refunded() != null) {
                    if ($tmp_order->get_total_refunded() >= $order->get_total()) {
                        $total_refunds++;
                    } else {
                        $partial_refunds += count($tmp_order->get_refunds());
                    }
                }
            }

            $customer = new stdClass();
            $customer->member_since           = $sign_up;
            $customer->num_orders             = $total_orders;
            $customer->amount_orders          = $total_amt;
            $customer->amount_refunded        = $refund_amt;
            $customer->num_full_refunds       = $total_refunds;
            $customer->num_partial_refunds    = $partial_refunds;

            $woocommerceObjectModule = new \ShopperLibrary\ObjectModule\WoocommerceObjectModule(WcPaylaterGateway::PAYLATER_SHOPPER_URL);
            $woocommerceObjectModule
                ->setPublicKey($this->public_key)
                ->setPrivateKey($this->secret_key)
                ->setCurrency($currency)
                ->setDiscount(false)
                ->setOkUrl($callback_url)
                ->setNokUrl($callback_url)
                ->setIFrame($this->iframe)
                ->setCallbackUrl($callback_url)
                ->setCancelledUrl($cancelUrl)
                ->setIncludeSimulator($include_simulator)
                ->setCart($cart)
                ->setOrder($order)
                ->setCustomer($customer)
                ->setWoShippingAddress($order)
                ->setWoBillingAddress($order)
                ->setMetadata(
                    array(
                        'woocommerce' => WC()->version,
                        'pmt'         => $this->plugin_info['Version'],
                        'php'         => phpversion()
                    )
                );

            $shopperClient = new \ShopperLibrary\ShopperClient(WcPaylaterGateway::PAYLATER_SHOPPER_URL);
            $shopperClient->setObjectModule($woocommerceObjectModule);
            $response = $shopperClient->getPaymentForm();
            $url = "";
            if ($response) {
                $paymentForm = json_decode($response);
                if (is_object($paymentForm) && is_object($paymentForm->data)) {
                    $url = $paymentForm->data->url;
                }
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
        } catch (Exception $e) {
            wc_add_notice(__('Error en el pago - ', 'paylater') . $e->getMessage(), 'error');
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
            $order_id = $_GET['order-received'];
            $origin = ($_SERVER['REQUEST_METHOD'] == 'POST') ? 'Notify' : 'Order';

            include_once('notifyController.php');
            $notify = new WcPaylaterNotify();
            $order = new WC_Order($order_id);
            $notify->setOrder($order);
            $notify->setOrigin($origin);
            $result = $notify->processInformation();
        } catch (Exception $exception) {
            $result['notification_message'] = $exception->getMessage();
            $result['notification_error'] = true;
        }

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $response = json_encode(array(
                'timestamp' => time(),
                'order_id' => $order_id,
                'result' => (!$result['notification_error']) ? 'success' : 'failed',
                'result_description' => $result['notification_message']
            ));

            if ($result['notification_error']) {
                header('HTTP/1.1 400 Bad Request', true, 400);
            } else {
                header('HTTP/1.1 200 Ok', true, 200);
            }
            header('Content-Type: application/json', true);
            header('Content-Length: ' . strlen($response));
            echo ($response);
            exit();
        } else {
            $returnUrl = $this->getOkUrl($order);
            wp_redirect($returnUrl);
            exit;
        }
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
        if ($order->get_payment_method() == WcPaylaterGateway::METHOD_ID && $order->get_status() == 'failed') {
            $status = 'processing';
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
        if ($this->enabled==='yes' && $this->public_key!='' && $this->secret_key!='' &&
            $this->get_order_total()>$this->min_amount && $this->get_order_total()<$this->max_amount) {
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
        return $this->extra_title;
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

            return array(
                'result'   => 'success',
                'redirect' => $order->get_checkout_payment_url(true) //paylaterReceiptPage function
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
            'message' => $this->checkout_title,
            'public_key' => $this->public_key,
            'total' => WC()->session->cart_totals['total'],
            'enabled' =>  $this->simulator_checkout,
            'min_installments' => $this->min_installments,
            'max_installments' => $this->max_installments
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
        if (count($arrayParams)) {
            foreach ($arrayParams as $keyParam => $valueParam) {
                preg_match('#\{{.*?}\}#', $valueParam, $match);
                if (count($match)) {
                    $key = str_replace(array('{{','}}'), array('',''), $match[0]);
                    $arrayParams[$keyParam] = $defaultFields[$key];
                }
            }
            return implode('/', $arrayParams);
        }
        else
            return $pathString;
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
