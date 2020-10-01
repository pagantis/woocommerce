<?php

function requireWPPluginFunctions()
{
    if (! function_exists('is_plugin_active')) {
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }
}

requireWPPluginFunctions();


/**
 * Get lowercase config value from WP DB
 *
 * @param string $configKey
 *
 * @return string
 * @global wpdb  $wpdb WordPress database abstraction object.
 */
function getConfigValue($configKey)
{
    global $wpdb;
    $tableName = $wpdb->prefix . PG_CONFIG_TABLE_NAME;
    $value     = $wpdb->get_var($wpdb->prepare("SELECT value FROM $tableName WHERE config= %s ", $configKey));

    return $value;
}

/**
 * Check if table exists in WordPress DB
 *
 * @param string $tableToCheck
 *
 * @return bool
 * @see wpdb::get_var()
 */
function isPgTableCreated($tableToCheck)
{
    global $wpdb;
    $tableName = $wpdb->prefix . $tableToCheck;
    if ($wpdb->get_var("SHOW TABLES LIKE '$tableName'") == $tableName) {
        return true;
    }

    return false;
}

/**
 * Check if orders table exists
 */
function createPgCartTable()
{
    global $wpdb;
    $tableName       = $wpdb->prefix . PG_CART_PROCESS_TABLE;
    $charset_collate = $wpdb->get_charset_collate();
    $sql             = "CREATE TABLE $tableName ( id int, order_id varchar(50), wc_order_id varchar(50),  
                  UNIQUE KEY id (id)) $charset_collate";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

function createLogsTable()
{
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $LogsTableName   = $wpdb->prefix . PG_LOGS_TABLE_NAME;
    $sqlQuery        = "CREATE TABLE $LogsTableName ( 
    id int NOT NULL AUTO_INCREMENT,
    log text NOT NULL, 
    createdAt timestamp DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY id (id)) 
    $charset_collate";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sqlQuery);
}

/**
 * @param null $exception
 * @param null $message
 */
function insertLogEntry($exception = null, $message = null)
{
    global $wpdb;
    if (! isPgTableCreated(PG_LOGS_TABLE_NAME)) {
        createLogsTable();
    }
    $logEntry = new Pagantis\ModuleUtils\Model\Log\LogEntry();
    if ($exception instanceof \Exception) {
        $logEntry = $logEntry->error($exception);
    } else {
        $logEntry = $logEntry->info($message);
    }
    $tableName = $wpdb->prefix . PG_LOGS_TABLE_NAME;
    $wpdb->insert($tableName, array('log' => $logEntry->toJson()));
}

/**
 * @return bool
 */
function areDecimalSeparatorEqual()
{
    $pgDecimalSeparator = getPgSimulatorDecimalSeparatorConfig();
    $wc_decimal_sep     = get_option('woocommerce_price_decimal_sep');
    if (stripslashes($wc_decimal_sep) == stripslashes($pgDecimalSeparator)) {
        return true;
    } else {
        return false;
    }
}


/**
 * @return bool
 */
function areThousandsSeparatorEqual()
{
    $pgThousandSeparator = getPgSimulatorThousandsSeparator();
    $wc_price_thousand   = get_option('woocommerce_price_thousand_sep');
    if (stripslashes($wc_price_thousand) == stripslashes($pgThousandSeparator)) {
        return true;
    } else {
        return false;
    }
}

/**
 * @return array|object|null
 */
function getPgSimulatorThousandsSeparator()
{
    global $wpdb;
    $tableName = $wpdb->prefix . PG_CONFIG_TABLE_NAME;
    $query     = "SELECT value FROM $tableName WHERE config='PAGANTIS_SIMULATOR_THOUSANDS_SEPARATOR'";
    $result    = $wpdb->get_row($query, ARRAY_A);

    return $result['value'];
}

/**
 * @return array|object|null
 */
function getPgSimulatorDecimalSeparatorConfig()
{
    global $wpdb;
    $tableName = $wpdb->prefix . PG_CONFIG_TABLE_NAME;
    $query     = "SELECT value FROM $tableName WHERE config='PAGANTIS_SIMULATOR_DECIMAL_SEPARATOR'";
    $result    = $wpdb->get_row($query, ARRAY_A);

    return $result['value'];
}

function updateThousandsSeparatorDbConfig()
{
    global $wpdb;
    if (areThousandsSeparatorEqual()) {
        return;
    }
    $tableName         = $wpdb->prefix . PG_CONFIG_TABLE_NAME;
    $thousandSeparator = get_option('woocommerce_price_thousand_sep');
    $wpdb->update(
        $tableName,
        array('value' => $thousandSeparator),
        array('config' => 'PAGANTIS_SIMULATOR_THOUSANDS_SEPARATOR'),
        array('%s'),
        array('%s')
    );
}

function updateDecimalSeparatorDbConfig()
{
    global $wpdb;
    if (areDecimalSeparatorEqual()) {
        return;
    }
    $tableName        = $wpdb->prefix . PG_CONFIG_TABLE_NAME;
    $decimalSeparator = get_option('woocommerce_price_decimal_sep');
    $wpdb->update(
        $tableName,
        array('value' => $decimalSeparator),
        array('config' => 'PAGANTIS_SIMULATOR_DECIMAL_SEPARATOR'),
        array('%s'),
        array('%s')
    );
}


/**
 * @param $simulatorType
 * @param $validSimulatorTypes array
 *
 * @return bool
 */
function isSimulatorTypeValid($simulatorType, $validSimulatorTypes)
{
    if (! in_array($simulatorType, $validSimulatorTypes)) {
        return false;
    }

    return true;
}

/**
 * @param $currentTemplateName
 *
 * @param $validTemplateNames array
 *
 * @return bool
 */
function isTemplatePresent($currentTemplateName, $validTemplateNames)
{
    if (in_array($currentTemplateName, $validTemplateNames)) {
        return true;
    }

    return false;
}


function areMerchantKeysSet()
{
    $settings   = get_option('woocommerce_pagantis_settings');
    $publicKey  = ! empty($settings['pagantis_public_key']) ? $settings['pagantis_public_key'] : '';
    $privateKey = ! empty($settings['pagantis_private_key']) ? $settings['pagantis_private_key'] : '';
    if ((empty($publicKey) && empty($privateKey)) || (empty($publicKey) || empty($privateKey))) {
        return false;
    }

    return true;
}

function areMerchantKeysSet4x()
{
    $settings   = get_option('woocommerce_pagantis_settings');
    $publicKey  = ! empty($settings['pagantis_public_key_4x']) ? $settings['pagantis_public_key_4x'] : '';
    $privateKey = ! empty($settings['pagantis_private_key_4x']) ? $settings['pagantis_private_key_4x'] : '';
    if ((empty($publicKey) && empty($privateKey)) || (empty($publicKey) || empty($privateKey))) {
        return false;
    }

    return true;
}

function isSimulatorEnabled()
{
    $settings = get_option('woocommerce_pagantis_settings');
    if ((! empty($settings['simulator']) && 'yes' === $settings['simulator']) ? true : false) {
        return true;
    }

    return false;
}

function isPluginEnabled()
{
    $settings = get_option('woocommerce_pagantis_settings');

    return (! empty($settings['enabled']) && 'yes' === $settings['enabled']);
}

function isPluginEnabled4x()
{
    $settings = get_option('woocommerce_pagantis_settings');
    return (! empty($settings['enabled_4x']) && 'yes' === $settings['enabled_4x']);
}


function isCountryShopContextValid()
{
    $locale           = strtolower(strstr(get_locale(), '_', true));
    $allowedCountries = maybe_unserialize(getConfigValue('PAGANTIS_ALLOWED_COUNTRIES'));
    if (! in_array(strtolower($locale), $allowedCountries)) {
        return false;
    }

    return true;
}

/**
 * @return bool
 */
function isProductAmountValid()
{
    $minAmount = getConfigValue('PAGANTIS_DISPLAY_MIN_AMOUNT');
    $maxAmount = getConfigValue('PAGANTIS_DISPLAY_MAX_AMOUNT');
    global $product;
    if (method_exists($product, 'get_price')) {
        $productPrice = $product->get_price();
        $validAmount  = ($productPrice >= $minAmount && ($productPrice <= $maxAmount || $maxAmount == '0'));
        if ($validAmount) {
            return true;
        }
    }

    return false;
}

/**
 * @return bool
 */
function isProductAmountValid4x()
{
    $minAmount = getConfigValue('PAGANTIS_DISPLAY_MIN_AMOUNT_4x');
    $maxAmount = getConfigValue('PAGANTIS_DISPLAY_MAX_AMOUNT_4x');
    global $product;
    if (method_exists($product, 'get_price')) {
        $productPrice = $product->get_price();
        $validAmount  = ($productPrice >= $minAmount && ($productPrice <= $maxAmount || $maxAmount == '0'));
        if ($validAmount) {
            return true;
        }
    }

    return false;
}
function getAllowedCurrencies()
{
    return array("EUR");
}

/**
 * @return array
 */
function getExtraConfig()
{
    global $wpdb;
    $tableName = $wpdb->prefix . PG_CONFIG_TABLE_NAME;
    $response  = array();
    $dbResult  = $wpdb->get_results("select config, value from $tableName", ARRAY_A);
    foreach ($dbResult as $value) {
        $response[$value['config']] = $value['value'];
    }

    return $response;
}

function getModuleVersion()
{

    $mainFile = plugin_dir_path(PG_WC_MAIN_FILE). '/WC_Pagantis.php';
    $version = get_file_data($mainFile, array('Version' => 'Version'), false);
    return $version['Version'];
}

/**
 * @param $order
 * @param $customerID
 * @return string
 * @throws \Exception
 */
function maybeGetIDNumber($order, $customerID)
{

    $number = '';
    $customer = new WC_Customer($customerID);

    if ($order instanceof WC_Order) {
        $metaKeys = array(
            'national_id',
            'dni',
            'nie',
            'shipping_nif',
            'billing_nif'
        );

        foreach ($metaKeys as $key) {

            $number = $order->get_meta($key);

            if (empty($number) && !empty($customer)) {
                $number = $customer->get_meta($key);
            }
            if (!empty($number)) {
                break;
            }
        }
    }
    return is_string($number) ? $number : '';
}

/**
 * @param $order
 * @param $customerID
 * @return string
 * @throws \Exception
 */
function maybeGetTaxIDNumber($order, $customerID)
{

    $number = '';
    $customer = new WC_Customer($customerID);

    if ($order instanceof WC_Order) {

        $metaKeys = array(
            'billing_cfpiva',
            '_vat_number',
            'fiscalcode',
            'tax_id',
            'cif',
            '_billing_vat_number',
            'billing_vat_number',
            'billing_eu_vat_number',
            'VAT Number',
            'vat_number',
            '_billing_wc_avatax_vat_id'
        );

        foreach ($metaKeys as $key) {
            $number = $order->get_meta($key);

            if (empty($number) && !empty($customer)) {
                $number = $customer->get_meta($key);
            }

            if (!empty($number)) {
                break;
            }
        }
    }

    return is_string($number) ? $number : '';
}


/**
 * @param $order
 * @param $customerID
 * @return string|void
 * @throws \Exception
 */
function maybeGetPhoneNumber($order, $customerID)
{

    $isWCVersionLT3 = version_compare(WC_VERSION, '3.0', '<');
    $customer = new WC_Customer($customerID);
    $number = '';

    if (!$order instanceof WC_Order || !$customer instanceof WC_Customer) {
        return;
    }

    if (!empty($customer)) {
        $number = $isWCVersionLT3 ? $customer->billing_phone : $customer->get_billing_phone();
    }
    if (empty($number)) {
        $number = $isWCVersionLT3 ? $order->billing_phone : $order->get_billing_phone();
    }

    return is_string($number) ? $number : '';
}

/**
 * @param $customerID
 * @return string|void
 * @throws \Exception
 */
function maybeGetDateOfBirth($customerID)
{

    $isWCVersionLT3 = version_compare(WC_VERSION, '3.0', '<');
    $customer = new WC_Customer($customerID);
    $date = '';

    if (!empty($customer)) {
    $metaKeys = array(
        'birthday',
        'date_of_birth',
        'dob',
    );

        foreach ($metaKeys as $key) {
            $date = empty($customer->get_meta($key)) ? $customer->get_meta($key) : get_user_meta( $customerID, '_automatewoo_birthday_full', true );
        }

    }

    return is_string($date) ? $date : '';
}

/**
 * @param $product_id
 *
 * @return string
 */
function isProductPromoted($product_id)
{
    $metaProduct = get_post_meta($product_id);

    return (array_key_exists('custom_product_pagantis_promoted', $metaProduct)
            && $metaProduct['custom_product_pagantis_promoted']['0'] === 'yes') ? 'true' : 'false';
}

/**
 * @return int
 */
function getPromotedAmount()
{
    $promotedAmount = 0;
    foreach (WC()->cart->get_cart() as $key => $item) {
        $promotedProduct = isProductPromoted($item['product_id']);
        if ($promotedProduct == 'true') {
            $promotedAmount += $item['line_total'] + $item['line_tax'];
        }
    }

    return $promotedAmount;
}

/**
 * @param $pagantisOrderId
 * @param $wcOrderID
 * @param $urlToken
 * @param $methodID
 */
function addOrderToProcessingQueue($pagantisOrderId, $wcOrderID, $urlToken ,$methodID)
{
    global $wpdb;
    checkCartProcessTable();
    $tableName = $wpdb->prefix . PG_CART_PROCESS_TABLE;

    //Check if id exists
    $resultsSelect = $wpdb->get_results("SELECT * FROM $tableName WHERE id='$pagantisOrderId'");
    $countResults  = count($resultsSelect);

    if ($countResults == 0) {
        $wpdb->insert($tableName, array(
            'order_id' => $pagantisOrderId,
            'wc_order_id' => $wcOrderID,
            'token'       => $urlToken
        ), array('%s', '%s', '%s'));

        $logEntry = "Cart Added to Processing Queue" .
            " cart hash: ".WC()->cart->get_cart_hash().
            " Merchant order id: ".$wcOrderID.
            " Pagantis order id: ".$pagantisOrderId.
            " Pagantis urlToken: ".$urlToken.
            " Pagantis Product: ".$methodID;
        insertLogEntry(null, $logEntry);
    } else {
        $wpdb->update($tableName,
            array('order_id' => $pagantisOrderId,'token' => $urlToken),
            array('wc_order_id' => $wcOrderID),
            array('%s,%s'),
            array('%s'));
    }

}

function alterCartProcessTable()
{
    global $wpdb;
    $tableName = $wpdb->prefix . PG_CART_PROCESS_TABLE;
    if (! $wpdb->get_var( "SHOW COLUMNS FROM `{$tableName}` LIKE 'token';" ) ) {
        $wpdb->query("ALTER TABLE $tableName ADD COLUMN `token` VARCHAR(32) NOT NULL AFTER `order_id`");
        $wpdb->query("ALTER TABLE $tableName DROP PRIMARY KEY, ADD PRIMARY KEY(order_id)");
        // OLDER VERSIONS OF MODULE USE UNIQUE KEY ON `id` MEANING THIS VALUE WAS NULLABLE
        $wpdb->query("ALTER TABLE $tableName MODIFY `id` INT AUTO_INCREMENT");
    }
}

/**
 * Check if cart processing table exists
 */
function checkCartProcessTable()
{
    global $wpdb;
    $tableName = $wpdb->prefix . PG_CART_PROCESS_TABLE;

    if ($wpdb->get_var("SHOW TABLES LIKE '$tableName'") != $tableName) {

        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS $tableName(
            `id` INT AUTO_INCREMENT, 
            `order_id` varchar(60),
            `wc_order_id` varchar(60),
            `token` varchar(32) NOT NULL,
             PRIMARY KEY (`id`, `order_id`)
            )$charset_collate";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}


/**
 * Get the orders of a customer
 *
 * @param WP_User $current_user
 * @param $billingEmail
 *
 * @return mixed
 * @uses Wordpress Core Post API
 */
function getOrders($current_user, $billingEmail)
{
    $sign_up         = '';
    $total_orders    = 0;
    $total_amt       = 0;
    $refund_amt      = 0;
    $total_refunds   = 0;
    $partial_refunds = 0;
    if ($current_user->user_login) {
        $is_guest        = "false";
        $sign_up         = substr($current_user->user_registered, 0, 10);
        $customer_orders = get_posts(array(
            'numberposts' => -1,
            'meta_key'    => '_customer_user',
            'meta_value'  => $current_user->ID,
            'post_type'   => array('shop_order'),
            'post_status' => array('wc-completed', 'wc-processing', 'wc-refunded'),
        ));
    } else {
        $is_guest        = "true";
        $customer_orders = get_posts(array(
            'numberposts' => -1,
            'meta_key'    => '_billing_email',
            'meta_value'  => $billingEmail,
            'post_type'   => array('shop_order'),
            'post_status' => array('wc-completed', 'wc-processing', 'wc-refunded'),
        ));
        foreach ($customer_orders as $customer_order) {
            if (trim($sign_up) == '' || strtotime(substr($customer_order->post_date, 0, 10)) <= strtotime($sign_up)) {
                $sign_up = substr($customer_order->post_date, 0, 10);
            }
        }
    }

    return $customer_orders;
}

