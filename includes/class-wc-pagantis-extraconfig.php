<?php



class WcPagantisExtraConfig
{

    /** @var array $extraConfig */
    public $extraConfig;



    public static function getExtraConfigValue($key)
    {
        include_once('class-wc-pagantis-logger.php');
        $config = self::getExtraConfig();
        $value = $config[$key];
        WCPagantisLogger::writeLog($value);
        return $value;
    }

    /**
     * @return array
     */
    private static function getExtraConfig()
    {
        global $wpdb;
        $tableName = $wpdb->prefix.PAGANTIS_CONFIG_TABLE;
        $response = array();
        $dbResult = $wpdb->get_results("select config, value from $tableName", ARRAY_A);
        foreach ($dbResult as $value) {
            $response[$value['config']] = $value['value'];
        }

        return $response;
    }
}

/**
 * @todo remove this is for debug
 */
WcPagantisExtraConfig::getExtraConfigValue('PAGANTIS_PROMOTION_EXTRA');
WcPagantisExtraConfig::getExtraConfigValue('PAGANTIS_SIMULATOR_CSS_PRICE_SELECTOR');
WcPagantisExtraConfig::getExtraConfigValue('PAGANTIS_SIMULATOR_DISPLAY_SKIN');
WCPagantisLogger::writeLog(WcPagantisExtraConfig::getExtraConfigValue('PAGANTIS_SIMULATOR_DISPLAY_SKIN'));
WCPagantisLogger::writeLog(WcPagantisExtraConfig::getExtraConfigValue('PAGANTIS_SIMULATOR_CSS_PRICE_SELECTOR'));
