<?php


use Pagantis\ModuleUtils\Model\Log\LogEntry;

require_once dirname(__FILE__) . '/class-wc-pagantis-config.php';

function pg_areKeysSet()
{
    $settings = pg_get_plugin_settings();
    if ($settings['pagantis_public_key'] == '' || $settings['pagantis_private_key'] == '') {
        return false;
    }

    return true;
}

function pg_isSimulatorSettingEnabled()
{
    $settings = pg_get_plugin_settings();

    if ($settings['simulator'] !== 'yes') {
        WC_Admin_Settings::add_error(__('Error: PG Simulator is not enabled', 'pagantis'));

        return false;
    }

    return true;
}

function pg_IsCountryAllowed()
{
    $locale           = strtolower(strstr(get_locale(), '_', true));
    $allowedCountries = WC_Pagantis_Config::getAllowedCountriesSerialized();
    $allowedCountry   = (in_array(strtolower($locale), $allowedCountries));

    if (! $allowedCountry) {
        return false;
    }

    return true;
}

function pg_IsAmountValid()
{
    global $product;
    $locale           = pg_GetLocaleString();
    $allowedCountries = WC_Pagantis_Config::getAllowedCountriesSerialized();
    $allowedCountry   = (in_array(strtolower($locale), $allowedCountries));
    $minAmount        = WC_Pagantis_Config::getValueOfKey('PAGANTIS_DISPLAY_MIN_AMOUNT');
    $maxAmount        = WC_Pagantis_Config::getValueOfKey('PAGANTIS_DISPLAY_MAX_AMOUNT');
    $totalPrice       = $product->get_price();
    $validAmount      = ($totalPrice >= $minAmount && ($totalPrice <= $maxAmount || $maxAmount == '0'));
    if (! $validAmount) {
        return false;
    }

    return true;
}

function pg_get_plugin_settings()
{
    $settings = get_option('woocommerce_pagantis_settings');

    return $settings;
}

function pg_GetLocaleString()
{
    $locale = strtolower(strstr(get_locale(), '_', true));

    return $locale;
}

function pg_canProductSimulatorLoad()
{
    global $product;

    $settings         = get_option('woocommerce_pagantis_settings');
    $locale           = strtolower(strstr(get_locale(), '_', true));
    $allowedCountries = WC_Pagantis_Config::getAllowedCountriesSerialized();
    $allowedCountry   = (in_array(strtolower($locale), $allowedCountries));
    $minAmount        = WC_Pagantis_Config::getValueOfKey('PAGANTIS_DISPLAY_MIN_AMOUNT');
    $maxAmount        = WC_Pagantis_Config::getValueOfKey('PAGANTIS_DISPLAY_MAX_AMOUNT');
    $totalPrice       = $product->get_price();
    $validAmount      = ($totalPrice >= $minAmount && ($totalPrice <= $maxAmount || $maxAmount == '0'));
    if ($settings['enabled'] !== 'yes' || pg_areKeysSet() || pg_isSimulatorSettingEnabled()
        || ! pg_IsCountryAllowed()
        || ! pg_IsAmountValid()
    ) {
        return;
    }
}

/**
 * @param $product_id
 *
 * @return string
 */
function pg_isProductPromoted($product_id)
{
    $metaProduct = get_post_meta($product_id);

    return (array_key_exists('custom_product_pagantis_promoted', $metaProduct)
            && $metaProduct['custom_product_pagantis_promoted']['0'] === 'yes') ? 'true' : 'false';
}

/**
 * Determines if the plugin is active.*
 *
 * @return bool True, if in the active plugins list. False, not in the list.
 * @since 8.3.7
 */
function pg_isPluginActive()
{
    return in_array('pagantis', (array) get_option('active_plugins', array()), true);
}


/**
 * @param $css_price_selector
 *
 * @return mixed|string
 */
function preparePriceSelector($css_price_selector)
{
    if ($css_price_selector === 'default' || $css_price_selector === '') {
        $css_price_selector === $this->defaultConfigs['PAGANTIS_SIMULATOR_CSS_PRICE_SELECTOR'];
    } elseif (! unserialize($css_price_selector)) { //in the case of a custom string selector, we keep it
        $css_price_selector = serialize(array($css_price_selector));
    }

    return $css_price_selector;
}

function pg_wc_is_screen_correct()
{
    $current_screen = get_current_screen();
    if ('shop_order' === $current_screen->id || 'plugins' === $current_screen->id
        || 'woocommerce_page_wc-settings' === $current_screen->id
    ) {
        return true;
    }

    return false;
}


function pg_wc_get_pagantis_admin_url()
{
    return admin_url('admin.php?page=wc-settings&tab=checkout&section=pagantis');
}

/**
 * @global wpdb $wpdb WordPress database abstraction object.
 */
function pg_wc_check_db_log_table()
{
    global $wpdb;
    $tableName = $wpdb->prefix . PAGANTIS_LOGS_TABLE;
    if ($wpdb->get_var("SHOW TABLES LIKE '$tableName'") !== $tableName) {
        $charset_collate = $wpdb->get_charset_collate();
        $sql             = "CREATE TABLE $tableName ( id int NOT NULL AUTO_INCREMENT, log text NOT NULL, 
                    createdAt timestamp DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY id (id)) $charset_collate";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    return;
}


/**
 * Determine if the store is running SSL.
 *
 * @return bool Flag SSL enabled.
 * @since  8.3.7
 */
function pg_wc_is_ssl_active()
{
    $shop_page = wc_get_page_permalink('shop');

    return (is_ssl() && 'https' === substr($shop_page, 0, 5));
}
