<?php


/**
 * Check if logs table exists
 */
function isPgLogsTableCreated()
{
    global $wpdb;
    $tableName = $wpdb->prefix . PG_LOGS_TABLE_NAME;
    if ($wpdb->get_var("SHOW TABLES LIKE '$tableName'") == $tableName) {
        return true;
    }

    return false;
}


function createPgLogsTable()
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
    if ( ! isPgLogsTableCreated()) {
        createPgLogsTable();
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
    $wpdb->update($tableName, array('value' => $thousandSeparator), array('config' => 'PAGANTIS_SIMULATOR_THOUSANDS_SEPARATOR'),
        array('%s'), array('%s'));
}

function updateDecimalSeparatorDbConfig()
{
    global $wpdb;
    if (areDecimalSeparatorEqual()) {
        return;
    }
    $tableName        = $wpdb->prefix . PG_CONFIG_TABLE_NAME;
    $decimalSeparator = get_option('woocommerce_price_decimal_sep');
    $wpdb->update($tableName, array('value' => $decimalSeparator), array('config' => 'PAGANTIS_SIMULATOR_DECIMAL_SEPARATOR'),
        array('%s'), array('%s'));
}


/**
 * @param $simulatorType
 * @param $validSimulatorTypes array
 *
 * @return bool
 */
function isSimulatorTypeValid($simulatorType, $validSimulatorTypes)
{
    if ( ! in_array($simulatorType, $validSimulatorTypes)) {
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
    if (( ! empty($settings['simulator']) && 'yes' === $settings['simulator']) ? true : false) {
        return true;
    }

    return false;
}

function isPluginEnabled()
{
    $settings = get_option('woocommerce_pagantis_settings');
    return (!empty($settings['enabled']) && 'yes' === $settings['enabled']);
}

function isPluginEnabled4x()
{
    $settings = get_option('woocommerce_pagantis_settings');
    return (!empty($settings['enabled_4x']) && 'yes' === $settings['enabled_4x']);
}


function isCountryShopContextValid()
{
    $locale           = strtolower(strstr(get_locale(), '_', true));
    $allowedCountries = maybe_unserialize(getConfigValue('PAGANTIS_ALLOWED_COUNTRIES'));
    if ( ! in_array(strtolower($locale), $allowedCountries)) {
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
