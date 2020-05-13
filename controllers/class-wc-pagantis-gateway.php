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
use Pagantis\ModuleUtils\Model\Log\LogEntry;

if (! defined('ABSPATH')) {
    exit;
}

define('__ROOT__', dirname(dirname(__FILE__)));

class WC_Pagantis_Gateway extends WC_Payment_Gateway
{
    const METHOD_ID = 'pagantis';

    /**
     * Customizable configuration options
     *
     * @var array $extraConfig
     */
    private $extraConfig;

    /**
     * Language to facilitate localization
     *
     * @var string $language
     */
    public $language;


    /**
     * Array of allowed currencies with Pagantis
     *
     * @var array $allowed_currencies
     */
    private $allowed_currencies;


    /**
     * WcPagantisGateway constructor.
     */
    public function __construct()
    {
        require_once dirname(__FILE__) . '/../includes/class-wc-pagantis-config.php';
        require_once dirname(__FILE__) . '/../includes/class-wc-pagantis-logger.php';
        require_once dirname(__FILE__) . '/../includes/functions.php';
        //Mandatory vars for plugin
        $this->id           = PAGANTIS_PLUGIN_ID;
        $this->has_fields   = true;
        $this->method_title = ucfirst($this->id);
        //Useful vars
        $this->template_path = plugin_dir_path(__FILE__) . '../templates/';

        $this->allowed_currencies = array('EUR');
        $this->language           = strstr(get_locale(), '_', true);
        if ($this->language === '') {
            $this->language = 'ES';
        }
        $this->icon = 'https://cdn.digitalorigin.com/assets/master/logos/pg-130x30.svg';

        //Panel form fields
        $this->init_form_fields();
        $this->init_settings();

        $this->extraConfig = WC_Pagantis_Config::getExtraConfig();
        $this->title       = __($this->extraConfig['PAGANTIS_TITLE'], 'pagantis');

        $this->method_description =
            __('Give the flexibility to your clients to pay in installments with Pagantis!', 'pagantis');
        $this->check_deprecated_arguments();
        $this->set_payment_urls();
        //Hooks
        add_action(
            'woocommerce_update_options_payment_gateways_' . $this->id,
            array($this, 'process_admin_options')
        ); //Save plugin options
        add_action(
            'admin_notices',
            array($this, 'check_plugin_settings')
        );                          //Check config fields
        add_action(
            'woocommerce_receipt_' . $this->id,
            array($this, 'get_wc_order_received_page')
        );          //Pagantis form
        add_action('woocommerce_api_wcpagantisgateway', array($this, 'pagantisNotification'));      //Json Notification
        add_filter('woocommerce_payment_complete_order_status', array($this, 'pagantisCompleteStatus'), 10, 3);
        add_filter('load_textdomain_mofile', array($this, 'loadPagantisTranslation'), 10, 2);
        //add_action('wp_enqueue_scripts', array($this, 'enqueue_ajax_scripts'));
    }

    public function enqueue_ajax_scripts()
    {
        if ($this->enabled !== 'yes' || ! is_checkout() || is_order_received_page()
            || is_wc_endpoint_url('order-pay')
        ) {
            return;
        }

        wp_register_script(
            'pagantis-checkout',
            plugins_url('../assets/js/pagantis-checkout.js', __FILE__),
            array('jquery', 'woocommerce', 'wc-checkout', 'wc-country-select', 'wc-address-i18n'),
            PAGANTIS_VERSION,
            true
        );
        wp_enqueue_script('pagantis-checkout');

        $checkout_localize_params                        = array();
        $checkout_localize_params['place_order_url']     = WC_AJAX::get_endpoint('pagantis_checkout');
        $checkout_localize_params['place_order_nonce']   = wp_create_nonce('pagantis_checkout');
        $checkout_localize_params['wc_ajax_url']         = WC_AJAX::get_endpoint('%%endpoint%%');
        $checkout_localize_params['i18n_checkout_error'] =
            esc_attr__('Error processing pagantis checkout. Please try again.', 'pagantis');

        wp_localize_script('pagantis-checkout', 'pagantis_params', $checkout_localize_params);

        wp_enqueue_script('pagantis_params');
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

    /**
     * Initialise Gateway Settings Form Fields.
     *
     * @see WC_Settings_API
     */
    public function init_form_fields()
    {
        $this->form_fields = include(plugin_dir_path(__FILE__) . '../includes/settings-pagantis.php');
    }

    /***********
     * HOOKS
     ***********/

    /**
     * Check dependencies.
     *
     * @hook   admin_notices
     * @throws Exception
     */
    public function check_plugin_settings()
    {
        if (! pg_wc_is_screen_correct()) {
            return;
        }

        if ($this->settings['enabled'] !== 'yes') {
            WC_Admin_Notices::add_custom_notice(
                PAGANTIS_PLUGIN_ID . 'first_setup',
                sprintf(// translators: 1:  URL to WP plugin page.
                    __(
                        'Activate Pagantis to start offering comfortable payments in installments to your clients. <a class="button button-primary" href="%1$s">Activate Pagantis now!</a>',
                        'pagantis'
                    ),
                    pg_wc_get_pagantis_admin_url()
                )
            );
        }
        if (WC_Admin_Notices::has_notice(PAGANTIS_PLUGIN_ID . 'first_setup') && $this->settings['enabled'] === 'yes') {
            WC_Admin_Notices::remove_notice(PAGANTIS_PLUGIN_ID . 'first_setup');
        }

        if ($this->settings['pagantis_public_key'] === '' xor $this->settings['pagantis_private_key'] === ''
                                                              && $this->settings['enabled'] === 'yes'
        ) {
            WC_Admin_Notices::add_custom_notice(
                PAGANTIS_PLUGIN_ID . 'keys_setup',
                sprintf(// translators: 1:  URL to WP plugin page.
                    __(
                        'Set your Pagantis merchant keys to start offering comfortable payments in installments  <a class="button button-primary" href="%1$s">Go to keys setup</a></p>',
                        'pagantis'
                    ),
                    pg_wc_get_pagantis_admin_url()
                )
            );
        }

        if ($this->settings['pagantis_public_key'] === '' xor $this->settings['pagantis_private_key'] === ''
                                                              || $this->settings['enabled'] === 'yes'
        ) {
            WC_Admin_Notices::add_custom_notice(
                PAGANTIS_PLUGIN_ID . 'keys_check',
                sprintf(// translators: 1:  URL to WP plugin page.
                    __(
                        'Set your Pagantis merchant Api keys to start offering comfortable payments in installments.  <a class="button button-primary" href="%1$s">Go to keys setup</a>',
                        'pagantis'
                    ),
                    pg_wc_get_pagantis_admin_url()
                )
            );
        }

        if ($this->settings['pagantis_public_key'] !== '' && $this->settings['pagantis_private_key'] !== ''
            || $this->settings['enabled'] === 'no'
        ) {
            WC_Admin_Notices::add_custom_notice(
                PAGANTIS_PLUGIN_ID . 'finish_setup',
                sprintf(// translators: 1:  URL to WP plugin page.
                    __(
                        'It seems that your merchant keys are setup but the plugin is not active Activate the Pagantis plugin to start offering comfortable payments in installments to your clients.  <a class="button button-primary" href="%1$s">Go to Pagantis settings</a>',
                        'pagantis'
                    ),
                    pg_wc_get_pagantis_admin_url()
                )
            );
        }
        if (WC_Admin_Notices::has_notice(PAGANTIS_PLUGIN_ID . 'keys_check'
                                         && $this->settings['pagantis_public_key'] !== ''
                                         && $this->settings['pagantis_private_key'] !== ''
                                         && $this->settings['enabled'] === 'yes')
        ) {
            WC_Admin_Notices::remove_notice(PAGANTIS_PLUGIN_ID . 'keys_setup');
        }


        if (WC_Admin_Notices::has_notice(PAGANTIS_PLUGIN_ID . 'first_setup'
                                         && $this->settings['pagantis_public_key'] === ''
                                         && $this->settings['pagantis_private_key'] === '')
        ) {
            WC_Admin_Notices::remove_notice(PAGANTIS_PLUGIN_ID . 'keys_setup');
        }


        if (WC_Admin_Notices::has_notice(PAGANTIS_PLUGIN_ID . 'keys_check')
            xor WC_Admin_Notices::has_notice(PAGANTIS_PLUGIN_ID . 'first_setup')
                && $this->settings['pagantis_public_key'] !== ''
                || $this->settings['pagantis_private_key'] !== ''
        ) {
            WC_Admin_Notices::remove_notice(PAGANTIS_PLUGIN_ID . 'first_setup');
            WC_Admin_Notices::remove_notice(PAGANTIS_PLUGIN_ID . 'keys_check');
        }

        if (! in_array(get_woocommerce_currency(), $this->allowed_currencies, true)) {
            WC_Admin_Settings::add_error(__('Error: Pagantis only can be used in Euros.', 'pagantis'));
            $this->settings['enabled'] = 'no';
        }

        if ($this->extraConfig['PAGANTIS_SIMULATOR_MAX_INSTALLMENTS'] < '2'
            || $this->extraConfig['PAGANTIS_SIMULATOR_MAX_INSTALLMENTS'] > '12'
        ) {
            $this->settings['enabled'] = 'no';

            WC_Admin_Settings::add_error(__(
                'Error: Pagantis can be used up to 12 installments please contact your account manager',
                'pagantis'
            ));
        }

        if ($this->extraConfig['PAGANTIS_SIMULATOR_START_INSTALLMENTS'] < '2'
            || $this->extraConfig['PAGANTIS_SIMULATOR_START_INSTALLMENTS'] > '12'
        ) {
            WC_Admin_Settings::add_error(__(
                'Error: Pagantis can be used from 2 installments please contact your account manager',
                'pagantis'
            ));
        }
        if ($this->extraConfig['PAGANTIS_DISPLAY_MIN_AMOUNT'] < 0) {
            WC_Admin_Settings::add_error(__('Error: Pagantis can not be used for free products', 'pagantis'));
        }
    }

    /**
     * PANEL - Display admin setting panel
     *
     * @hook woocommerce_update_options_payment_gateways_pagantis
     */
    public function admin_options()
    {
        $template_fields = array(
            'panel_description' => $this->method_description,
            'button1_label'     => __('Login to your panel', 'pagantis'),
            'button2_label'     => __('Documentation', 'pagantis'),
            'logo'              => $this->icon,
            'settings'          => $this->generate_settings_html($this->form_fields, false),
        );
        wc_get_template('admin_header.php', $template_fields, '', $this->template_path);
    }


    /**
     * PANEL - Check admin panel fields -> Hook: admin_notices
     *
     * @deprecated since 8.3.7
     */
    public function pagantisCheckFields()
    {
        _deprecated_function(__METHOD__, '8.3.7', 'check_dependencies');
    }

    private function check_deprecated_arguments()
    {
        _deprecated_argument(
            $this->mainFileLocation,
            '8.3.7',
            '$this->mainFileLocation has been deprecated please use PAGANTIS_VERSION'
        );
        _deprecated_argument(
            $this->plugin_info,
            '8.3.7',
            ' $this->plugin_info has been deprecated please use PAGANTIS_VERSION'
        );
        _deprecated_argument(
            PAGANTIS_PLUGIN_ID,
            '8.3.7',
            ' METHOD_ID has been deprecated please use PAGANTIS_PLUGIN_ID'
        );
    }

    /**
     * CHECKOUT - Generate the pagantis form. "Return" iframe or redirect. - Hook: woocommerce_receipt_pagantis
     *
     * @param $order_id
     *
     * @hook woocommerce_receipt_pagantis
     * @throws Exception
     */
    public function get_wc_order_received_page($order_id)
    {
        try {
            require_once(__ROOT__ . '/vendor/autoload.php');
            global $woocommerce;
            $order = wc_get_order($order_id);
            $order->set_payment_method(ucfirst($this->id));
            $order->save();

            if (! isset($order)) {
                throw new Exception(__('Order not found', 'pagantis'));
            }

            $shippingAddress = $order->get_address('shipping');
            $billingAddress  = $order->get_address('billing');
            if ($shippingAddress['address_1'] === '') {
                $shippingAddress = $billingAddress;
            }

            $national_id = $this->getNationalId($order);
            $tax_id      = $this->getTaxId($order);

            $userAddress = new Address();
            $userAddress->setZipCode($shippingAddress['postcode'])->setFullName($shippingAddress['first_name'] . ' '
                                                                                . $shippingAddress['last_name'])
                        ->setCountryCode($shippingAddress['country'] !== '' ? strtoupper($shippingAddress['country'])
                            : strtoupper($this->language))->setCity($shippingAddress['city'])
                        ->setAddress($shippingAddress['address_1'] . ' ' . $shippingAddress['address_2']);
            $orderShippingAddress = new Address();
            $orderShippingAddress->setZipCode($shippingAddress['postcode'])->setFullName($shippingAddress['first_name']
                                                                                         . ' '
                                                                                         . $shippingAddress['last_name'])
                                 ->setCountryCode($shippingAddress['country'] !== ''
                                     ? strtoupper($shippingAddress['country']) : strtoupper($this->language))
                                 ->setCity($shippingAddress['city'])->setAddress($shippingAddress['address_1'] . ' '
                                                                                 . $shippingAddress['address_2'])
                                 ->setFixPhone($shippingAddress['phone'])->setMobilePhone($shippingAddress['phone'])
                                 ->setNationalId($national_id)->setTaxId($tax_id);
            $orderBillingAddress = new Address();
            $orderBillingAddress->setZipCode($billingAddress['postcode'])->setFullName($billingAddress['first_name']
                                                                                       . ' '
                                                                                       . $billingAddress['last_name'])
                                ->setCountryCode($billingAddress['country'] !== ''
                                    ? strtoupper($billingAddress['country']) : strtoupper($this->language))
                                ->setCity($billingAddress['city'])->setAddress($billingAddress['address_1'] . ' '
                                                                               . $billingAddress['address_2'])
                                ->setFixPhone($billingAddress['phone'])->setMobilePhone($billingAddress['phone'])
                                ->setNationalId($national_id)->setTaxId($tax_id);
            $orderUser = new User();
            $orderUser->setAddress($userAddress)->setFullName($billingAddress['first_name'] . ' '
                                                              . $billingAddress['last_name'])
                      ->setBillingAddress($orderBillingAddress)->setEmail($billingAddress['email'])
                      ->setFixPhone($billingAddress['phone'])->setMobilePhone($billingAddress['phone'])
                      ->setShippingAddress($orderShippingAddress)->setNationalId($national_id)->setTaxId($tax_id);

            $previousOrders = $this->getOrders($order->get_user(), $billingAddress['email']);
            foreach ($previousOrders as $previousOrder) {
                $orderHistory = new OrderHistory();
                $orderElement = wc_get_order($previousOrder);
                $orderCreated = $orderElement->get_date_created();
                $orderHistory->setAmount(intval(100 * $orderElement->get_total()))
                             ->setDate(new \DateTime($orderCreated->date('Y-m-d H:i:s')));
                $orderUser->addOrderHistory($orderHistory);
            }

            $metadataOrder = new Metadata();
            $metadata      = array(
                'pg_module'  => 'woocommerce',
                'pg_version' => PAGANTIS_VERSION,
                'ec_module'  => 'woocommerce',
                'ec_version' => WC()->version,
            );

            foreach ($metadata as $key => $metadatum) {
                $metadataOrder->addMetadata($key, $metadatum);
            }

            $details      = new Details();
            $shippingCost = $order->shipping_total;
            $details->setShippingCost(intval(strval(100 * $shippingCost)));
            $items          = $order->get_items();
            $promotedAmount = 0;
            foreach ($items as $key => $item) {
                $wcProduct          = $item->get_product();
                $product            = new Product();
                $productDescription = sprintf(
                    '%s %s %s',
                    $wcProduct->get_name(),
                    $wcProduct->get_description(),
                    $wcProduct->get_short_description()
                );
                $productDescription = substr($productDescription, 0, 9999);

                $product->setAmount(intval(100 * ($item->get_total() + $item->get_total_tax())))
                        ->setQuantity($item->get_quantity())->setDescription($productDescription);
                $details->addProduct($product);

                $promotedProduct = $this->isPromoted($item->get_product_id());
                if ($promotedProduct === 'true') {
                    $promotedAmount  += $product->getAmount();
                    $promotedMessage =
                        'Promoted Item: ' . $wcProduct->get_name() . ' - Price: ' . $item->get_total() . ' - Qty: '
                        . $product->getQuantity() . ' - Item ID: ' . $item['id_product'];
                    $promotedMessage = substr($promotedMessage, 0, 999);
                    $metadataOrder->addMetadata('promotedProduct', $promotedMessage);
                }
            }

            $orderShoppingCart = new ShoppingCart();
            $orderShoppingCart->setDetails($details)->setOrderReference($order->get_id())
                              ->setPromotedAmount($promotedAmount)->setTotalAmount(intval(strval(100 * $order->total)));
            $orderConfigurationUrls = new Urls();
            $cancelUrl              = $this->getKoUrl($order);
            $callback_arg           = array(
                'wc-api'         => 'wcpagantisgateway',
                'key'            => $order->get_order_key(),
                'order-received' => $order->get_id(),
                'origin'         => '',
            );

            $callback_arg_user           = $callback_arg;
            $callback_arg_user['origin'] = 'redirect';
            $callback_url_user           = add_query_arg($callback_arg_user, home_url('/'));

            $callback_arg_notif           = $callback_arg;
            $callback_arg_notif['origin'] = 'notification';
            $callback_url_notif           = add_query_arg($callback_arg_notif, home_url('/'));

            $orderConfigurationUrls->setCancel($cancelUrl)->setKo($callback_url_user)
                                   ->setAuthorizedNotificationCallback($callback_url_notif)
                                   ->setRejectedNotificationCallback(null)->setOk($callback_url_user);
            $orderChannel = new Channel();
            $orderChannel->setAssistedSale(false)->setType(Channel::ONLINE);

            $allowedCountries = unserialize($this->extraConfig['PAGANTIS_ALLOWED_COUNTRIES']);
            $purchaseCountry  = in_array(strtolower($this->language), $allowedCountries, true)
                ? $this->language
                : in_array(strtolower($shippingAddress['country']), $allowedCountries, true)
                    ? $shippingAddress['country'] : in_array(
                        strtolower($billingAddress['country']),
                        $allowedCountries,
                        true
                    ) ? $billingAddress['country'] : null;

            $orderConfiguration = new Configuration();
            $orderConfiguration->setChannel($orderChannel)->setUrls($orderConfigurationUrls)
                               ->setPurchaseCountry($purchaseCountry);

            $orderApiClient = new Order();
            $orderApiClient->setConfiguration($orderConfiguration)->setMetadata($metadataOrder)
                           ->setShoppingCart($orderShoppingCart)->setUser($orderUser);

            if ($this->pagantis_public_key === '' || $this->pagantis_private_key === '') {
                throw new \Exception('Public and Secret Key not found');
            }
            $orderClient   = new Client($this->pagantis_public_key, $this->pagantis_private_key);
            $pagantisOrder = $orderClient->createOrder($orderApiClient);
            if ($pagantisOrder instanceof \Pagantis\OrdersApiClient\Model\Order) {
                $url = $pagantisOrder->getActionUrls()->getForm();
                $this->insert_row_in_wc_orders_table($order->get_id(), $pagantisOrder->getId());
            } else {
                throw new OrderNotFoundException();
            }

            if ($url === '') {
                throw new Exception(__('No ha sido posible obtener una respuesta de Pagantis', 'pagantis'));
            } elseif ($this->extraConfig['PAGANTIS_FORM_DISPLAY_TYPE'] === '0') {
                wp_redirect($url);
                exit;
            } else {
                $template_fields = array(
                    'url'         => $url,
                    'checkoutUrl' => $cancelUrl,
                );
                wc_get_template('iframe.php', $template_fields, '', $this->template_path);
            }
        } catch (\Exception $exception) {
            wc_add_notice(__('Payment error ', 'pagantis') . $exception->getMessage(), 'error');
            WC_Pagantis_Logger::insert_log_entry_in_wpdb($exception);
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
            $origin = ($_SERVER['REQUEST_METHOD'] === 'POST') ? 'Notify' : 'Order';

            include_once('class-pg-notification-handler.php');
            $notify = new WC_PG_Notification_Handler();

            $notify->setOrigin($origin);
            /**
             * @var \Pagantis\ModuleUtils\Model\Response\AbstractJsonResponse $result
             */
            $result = $notify->processInformation();
        } catch (Exception $exception) {
            $result['notification_message'] = $exception->getMessage();
            $result['notification_error']   = true;
        }

        $paymentOrder = wc_get_order($result->getMerchantOrderId());
        if ($paymentOrder instanceof WC_Order) {
            $orderStatus = strtolower($paymentOrder->get_status());
        } else {
            $orderStatus = 'cancelled';
        }
        $acceptedStatus = array('processing', 'completed');
        if (in_array($orderStatus, $acceptedStatus, true)) {
            $returnUrl = $this->getOkUrl($paymentOrder);
        } else {
            $returnUrl = $this->getKoUrl($paymentOrder);
        }

        wp_redirect($returnUrl);
        exit;
    }

    /**
     * After failed status, set to processing not complete -> Hook: woocommerce_payment_complete_order_status
     *
     * @param $status
     * @param $order_id
     * @param $order
     *
     * @return string
     */
    public function pagantisCompleteStatus($status, $order_id, $order)
    {
        if ($order->get_payment_method() === PAGANTIS_PLUGIN_ID) {
            if ($order->get_status() === 'failed') {
                $status = 'processing';
            } elseif ($order->get_status() === 'pending' && $status === 'completed') {
                $status = 'processing';
            }
        }

        return $status;
    }

    /***********
     * REDEFINED FUNCTIONS
     ***********/

    /**
     * CHECKOUT - Check if payment method is available (called by woocommerce, can't apply camel caps)
     *
     * @return bool
     */
    public function is_available()
    {
        $locale           = strtolower(strstr(get_locale(), '_', true));
        $allowedCountries = unserialize($this->extraConfig['PAGANTIS_ALLOWED_COUNTRIES']);
        $allowedCountry   = (in_array(strtolower($locale), $allowedCountries, true));
        $minAmount        = $this->extraConfig['PAGANTIS_DISPLAY_MIN_AMOUNT'];
        $maxAmount        = $this->extraConfig['PAGANTIS_DISPLAY_MAX_AMOUNT'];
        $totalPrice       = (int)$this->get_order_total();
        $validAmount      = ($totalPrice >= $minAmount && ($totalPrice <= $maxAmount || $maxAmount === '0'));
        if ($this->enabled === 'yes' && $this->pagantis_public_key !== '' && $this->pagantis_private_key !== ''
            && $validAmount
            && $allowedCountry
        ) {
            return true;
        }

        return false;
    }

    /**
     * CHECKOUT - Checkout + admin panel title(method_title - get_title) (called by woocommerce,can't apply camel caps)
     *
     * @return string
     */
    public function get_title()
    {
        return __($this->extraConfig['PAGANTIS_TITLE'], 'pagantis');
    }

    /**
     * CHECKOUT - Called after push pagantis button on checkout(called by woocommerce, can't apply camel caps
     *
     * @param $order_id
     *
     * @return array
     */
    public function process_payment($order_id)
    {
        try {
            $order = new WC_Order($order_id);

            $redirectUrl = $order->get_checkout_payment_url(true); //pagantisReceiptPage function
            if (strpos($redirectUrl, 'order-pay=') === false) {
                $redirectUrl .= '&order-pay=' . $order_id;
            }

            return array(
                'result'   => 'success',
                'redirect' => $redirectUrl,
            );
        } catch (Exception $e) {
            wc_add_notice(__('Payment error ', 'pagantis') . $e->getMessage(), 'error');

            return array();
        }
    }

    /**
     * CHECKOUT - simulator (called by woocommerce, can't apply camel caps)
     */
    public function payment_fields()
    {
        $locale           = strtolower(strstr(get_locale(), '_', true));
        $allowedCountries = unserialize($this->extraConfig['PAGANTIS_ALLOWED_COUNTRIES']);
        $allowedCountry   = (in_array(strtolower($locale), $allowedCountries, true));
        $promotedAmount   = $this->getPromotedAmount();

        $template_fields = array(
            'public_key'            => $this->pagantis_public_key,
            'total'                 => WC()->cart->get_total(),
            'enabled'               => $this->settings['enabled'],
            'min_installments'      => $this->extraConfig['PAGANTIS_DISPLAY_MIN_AMOUNT'],
            'max_installments'      => $this->extraConfig['PAGANTIS_DISPLAY_MAX_AMOUNT'],
            'simulator_enabled'     => $this->settings['simulator'],
            'locale'                => $locale,
            'country'               => $locale,
            'allowed_country'       => $allowedCountry,
            'simulator_type'        => $this->extraConfig['PAGANTIS_SIMULATOR_DISPLAY_TYPE_CHECKOUT'],
            'promoted_amount'       => $promotedAmount,
            'thousandSeparator'     => $this->extraConfig['PAGANTIS_SIMULATOR_THOUSANDS_SEPARATOR'],
            'decimalSeparator'      => $this->extraConfig['PAGANTIS_SIMULATOR_DECIMAL_SEPARATOR'],
            'pagantisSimulatorSkin' => $this->extraConfig['PAGANTIS_SIMULATOR_DISPLAY_SKIN'],
        );
        try {
            wc_get_template('checkout_description.php', $template_fields, '', $this->template_path);
        } catch (Exception $exception) {
            $exception->getMessage();
        }
    }


    /***********
     * UTILS FUNCTIONS
     ***********/

    /**
     * set payment module callback urls
     */
    private function set_payment_urls()
    {
        $this->settings['ok_url'] =
            ($this->extraConfig['PAGANTIS_URL_OK'] !== '') ? $this->extraConfig['PAGANTIS_URL_OK']
                : $this->generateOkUrl();
        $this->settings['ko_url'] =
            ($this->extraConfig['PAGANTIS_URL_KO'] !== '') ? $this->extraConfig['PAGANTIS_URL_KO']
                : $this->generateKoUrl();
        foreach ($this->settings as $setting_key => $setting_value) {
            $this->$setting_key = $setting_value;
        }
    }

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
     *
     * @param $url
     *
     * @return string
     */
    private function generateUrl($url)
    {
        $parsed_url = parse_url($url);
        if ($parsed_url !== false) {
            $parsed_url['query'] = ! isset($parsed_url['query']) ? '' : $parsed_url['query'];
            parse_str($parsed_url['query'], $arrayParams);
            foreach ($arrayParams as $keyParam => $valueParam) {
                if ($valueParam === '') {
                    $arrayParams[$keyParam] = '{{' . $keyParam . '}}';
                }
            }
            $parsed_url['query'] = http_build_query($arrayParams);
            $return_url          = $this->get_unparsed_url($parsed_url);

            return urldecode($return_url);
        } else {
            return $url;
        }
    }

    /**
     * Replace {{}} by vars values inside ok_url
     *
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
     *
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
     *
     * @param $order
     * @param $url
     *
     * @return string
     */
    private function getKeysUrl($order, $url)
    {
        $defaultFields = (get_class($order) === 'WC_Order') ? array(
            'order-received' => $order->get_id(),
            'key'            => $order->get_order_key()
        ) : array();

        $parsedUrl = parse_url($url);
        if ($parsedUrl !== false) {
            //Replace parameters from url
            $parsedUrl['query'] = $this->getKeysParametersUrl($parsedUrl['query'], $defaultFields);

            //Replace path from url
            $parsedUrl['path'] = $this->getKeysPathUrl($parsedUrl['path'], $defaultFields);

            $returnUrl = $this->get_unparsed_url($parsedUrl);

            return $returnUrl;
        }

        return $url;
    }

    /**
     * Replace {{}} by vars values inside parameters
     *
     * @param $queryString
     * @param $defaultFields
     *
     * @return string
     */
    private function getKeysParametersUrl($queryString, $defaultFields)
    {
        parse_str(html_entity_decode($queryString), $arrayParams);
        var_export($queryString);
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
     *
     * @param $pathString
     * @param $defaultFields
     *
     * @return string
     */
    private function getKeysPathUrl($pathString, $defaultFields)
    {
        $arrayParams = explode('/', $pathString);
        foreach ($arrayParams as $keyParam => $valueParam) {
            preg_match('#\{{.*?}\}#', $valueParam, $match);
            if (count($match)) {
                $key                    = str_replace(array('{{', '}}'), array('', ''), $match[0]);
                $arrayParams[$keyParam] = $defaultFields[$key];
            }
        }

        return implode('/', $arrayParams);
    }

    /**
     * Replace {{var}} by empty space
     *
     * @param $parsed_url
     *
     * @return string
     */
    private function get_unparsed_url($parsed_url)
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
     *
     * @param $current_user
     * @param $billingEmail
     *
     * @return mixed
     */
    private function getOrders($current_user, $billingEmail)
    {
        $sign_up         = '';
        $total_orders    = 0;
        $total_amt       = 0;
        $refund_amt      = 0;
        $total_refunds   = 0;
        $partial_refunds = 0;
        if ($current_user->user_login) {
            $is_guest        = 'false';
            $sign_up         = substr($current_user->user_registered, 0, 10);
            $customer_orders = get_posts(array(
                'numberposts' => -1,
                'meta_key'    => '_customer_user',
                'meta_value'  => $current_user->ID,
                'post_type'   => array('shop_order'),
                'post_status' => array('wc-completed', 'wc-processing', 'wc-refunded'),
            ));
        } else {
            $is_guest        = 'true';
            $customer_orders = get_posts(array(
                'numberposts' => -1,
                'meta_key'    => '_billing_email',
                'meta_value'  => $billingEmail,
                'post_type'   => array('shop_order'),
                'post_status' => array('wc-completed', 'wc-processing', 'wc-refunded'),
            ));
            foreach ($customer_orders as $customer_order) {
                if (trim($sign_up) === ''
                    || strtotime(substr($customer_order->post_date, 0, 10)) <= strtotime($sign_up)
                ) {
                    $sign_up = substr($customer_order->post_date, 0, 10);
                }
            }
        }

        return $customer_orders;
    }


    /**
     *
     * @param       $wc_order_id
     * @param       $pg_order_id
     *
     * @throws Exception
     * @global wpdb $wpdb WordPress database abstraction object.
     */
    private function insert_row_in_wc_orders_table($wc_order_id, $pg_order_id)
    {
        global $wpdb;
        $this->check_if_wc_orders_db_table_exist();
        $tableName = $wpdb->prefix . PAGANTIS_WC_ORDERS_TABLE;

        //Check if id exists
        $resultsSelect = $wpdb->get_results("SELECT * FROM $tableName WHERE id='$wc_order_id'");
        $countResults  = count($resultsSelect);
        if ($countResults === 0) {
            $wpdb->insert($tableName, array('id' => $wc_order_id, 'order_id' => $pg_order_id), array('%d', '%s'));
        } else {
            $wpdb->update(
                $tableName,
                array('order_id' => $pg_order_id),
                array('id' => $wc_order_id),
                array('%s'),
                array('%d')
            );
        }
    }

    /**
     * Check if orders table exists
     *
     * @global wpdb $wpdb WordPress database abstraction object.
     */
    private function check_if_wc_orders_db_table_exist()
    {
        global $wpdb;
        $tableName = $wpdb->prefix . PAGANTIS_WC_ORDERS_TABLE;

        if ($wpdb->get_var("SHOW TABLES LIKE '$tableName'") !== $tableName) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql             = "CREATE TABLE $tableName ( id int, order_id varchar(50), wc_order_id varchar(50),  
                  UNIQUE KEY id (id)) $charset_collate";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }


    /**
     * @param $order
     *
     * @return null
     */
    private function getNationalId($order)
    {
        foreach ((array)$order->get_meta_data() as $mdObject) {
            $data = $mdObject->get_data();
            if ($data['key'] === 'vat_number') {
                return $data['value'];
            }
        }

        return null;
    }

    /**
     * @param $order
     *
     * @return mixed
     */
    private function getTaxId($order)
    {
        foreach ((array)$order->get_meta_data() as $mdObject) {
            $data = $mdObject->get_data();
            if ($data['key'] === 'billing_cfpiva') {
                return $data['value'];
            }
        }
    }

    /**
     * @param null $exception
     * @param null $message
     *
     * @deprecated 8.3.7
     */
    private function insertLog($exception = null, $message = null)
    {
        wc_deprecated_function('insertLog', '8.3.7', 'WC_Pagantis_Logger::insert_log_entry_in_wpdb');

        if ($exception instanceof \Exception) {
            WC_Pagantis_Logger::insert_log_entry_in_wpdb($exception);
        } else {
            WC_Pagantis_Logger::insert_log_entry_in_wpdb($message);
        }
    }


    /**
     * Check if logs table exists
     *
     * @deprecated 8.3.7
     */
    private function checkDbLogTable()
    {
        wc_deprecated_function('checkDbLogTable', '8.3.7', 'pg_wc_check_db_log_table');
    }

    /**
     * @param $product_id
     *
     * @return string
     */
    private function isPromoted($product_id)
    {
        $metaProduct = get_post_meta($product_id);

        return (array_key_exists('custom_product_pagantis_promoted', $metaProduct)
                && $metaProduct['custom_product_pagantis_promoted']['0'] === 'yes') ? 'true' : 'false';
    }

    /**
     * @return int
     */
    private function getPromotedAmount()
    {
        //https://wordpress.stackexchange.com/questions/275905/whats-the-difference-between-wc-and-woocommerce
        $items          = WC()->cart->get_cart();
        $promotedAmount = 0;
        foreach ($items as $key => $item) {
            $promotedProduct = $this->isPromoted($item['product_id']);
            if ($promotedProduct === 'true') {
                $promotedAmount += $item['line_total'] + $item['line_tax'];
            }
        }

        return $promotedAmount;
    }
}
