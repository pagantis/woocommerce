<?php


class WcPgConfig
{


    /**
     * @param      $key
     * @param bool $unSerialized
     *
     * @return mixed
     */
    public static function getValueOfKey($key, $unSerialized = false)
    {
        include_once('class-wc-pagantis-logger.php');
        $config = self::getExtraConfig();
        $value  = $config[$key];
        if ($unSerialized = true) {
            return unserialize($value);
        } else {
            return $value;
        }
    }


    public static function getDefaultConfig()
    {
        return  array(
        'PAGANTIS_TITLE'                           => 'Pago en cuotas',
        'PAGANTIS_SIMULATOR_DISPLAY_TYPE'          => 'sdk.simulator.types.PRODUCT_PAGE',
        'PAGANTIS_SIMULATOR_DISPLAY_TYPE_CHECKOUT' => 'sdk.simulator.types.CHECKOUT_PAGE',
        'PAGANTIS_SIMULATOR_DISPLAY_SKIN'          => 'sdk.simulator.skins.BLUE',
        'PAGANTIS_SIMULATOR_DISPLAY_POSITION'      => 'hookDisplayProductButtons',
        'PAGANTIS_SIMULATOR_START_INSTALLMENTS'    => 3,
        'PAGANTIS_SIMULATOR_MAX_INSTALLMENTS'      => 12,
        'PAGANTIS_SIMULATOR_CSS_POSITION_SELECTOR' => 'default',
        'PAGANTIS_SIMULATOR_DISPLAY_CSS_POSITION'  => 'sdk.simulator.positions.INNER',
        'PAGANTIS_SIMULATOR_CSS_PRICE_SELECTOR'    => 'a:3:{i:0;s:48:"div.summary *:not(del)>.woocommerce-Price-amount";i:1;s:54:"div.entry-summary *:not(del)>.woocommerce-Price-amount";i:2;s:36:"*:not(del)>.woocommerce-Price-amount";}',
        'PAGANTIS_SIMULATOR_CSS_QUANTITY_SELECTOR' => 'a:2:{i:0;s:22:"div.quantity input.qty";i:1;s:18:"div.quantity>input";}',
        'PAGANTIS_FORM_DISPLAY_TYPE'               => 0,
        'PAGANTIS_DISPLAY_MIN_AMOUNT'              => 1,
        'PAGANTIS_DISPLAY_MAX_AMOUNT'              => 0,
        'PAGANTIS_URL_OK'                          => '',
        'PAGANTIS_URL_KO'                          => '',
        'PAGANTIS_ALLOWED_COUNTRIES'               => 'a:3:{i:0;s:2:"es";i:1;s:2:"it";i:2;s:2:"fr";}',
        'PAGANTIS_PROMOTION_EXTRA'                 => '<p>Finance this product <span class="pg-no-interest">without interest!</span></p>',
        'PAGANTIS_SIMULATOR_THOUSANDS_SEPARATOR'   => '.',
        'PAGANTIS_SIMULATOR_DECIMAL_SEPARATOR'     => ',',
        'PAGANTIS_SIMULATOR_DISPLAY_SITUATION'     => 'default',
        'PAGANTIS_SIMULATOR_SELECTOR_VARIATION'    => 'default'
    );
    }
    /**
     * Get extra config from WP DB
     * @global wpdb $wpdb WordPress database abstraction object.
     * @return array
     */
    public static function getExtraConfig()
    {
        global $wpdb;
        $tableName = $wpdb->prefix . PAGANTIS_CONFIG_TABLE;
        $response  = array();
        $dbResult  = $wpdb->get_results("select config, value from $tableName", ARRAY_A);
        foreach ($dbResult as $value) {
            $response[$value['config']] = $value['value'];
        }

        return $response;
    }

    /**
     * @return mixed
     */
    public static function getAllowedCountriesSerialized()
    {
        $allowedCountries = WcPgConfig::getValueOfKey('PAGANTIS_ALLOWED_COUNTRIES', true);

        return $allowedCountries;
    }
}


