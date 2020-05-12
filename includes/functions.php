<?php


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
    $allowedCountries = WcPgConfig::getAllowedCountriesSerialized();
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
    $allowedCountries = WcPgConfig::getAllowedCountriesSerialized();
    $allowedCountry   = (in_array(strtolower($locale), $allowedCountries));
    $minAmount        = WcPgConfig::getValueOfKey('PAGANTIS_DISPLAY_MIN_AMOUNT');
    $maxAmount        = WcPgConfig::getValueOfKey('PAGANTIS_DISPLAY_MAX_AMOUNT');
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
    $allowedCountries = WcPgConfig::getAllowedCountriesSerialized();
    $allowedCountry   = (in_array(strtolower($locale), $allowedCountries));
    $minAmount        = WcPgConfig::getValueOfKey('PAGANTIS_DISPLAY_MIN_AMOUNT');
    $maxAmount        = WcPgConfig::getValueOfKey('PAGANTIS_DISPLAY_MAX_AMOUNT');
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
 * @since 8.3.7
 * @return bool True, if in the active plugins list. False, not in the list.

 */
function pg_isPluginActive()
{
    return in_array('pagantis', (array)get_option('active_plugins', array()), true);
}


/**
 * @param $css_price_selector
 *
 * @return mixed|string
 */
function preparePriceSelector($css_price_selector)
{
    if ($css_price_selector == 'default' || $css_price_selector == '') {
        $css_price_selector = $this->defaultConfigs['PAGANTIS_SIMULATOR_CSS_PRICE_SELECTOR'];
    } elseif (!unserialize($css_price_selector)) { //in the case of a custom string selector, we keep it
        $css_price_selector = serialize(array($css_price_selector));
    }

    return $css_price_selector;
}
