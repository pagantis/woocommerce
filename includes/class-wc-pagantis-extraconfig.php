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
        return $value;
    }

    /**
     * @return array
     */
    public static function getExtraConfig()
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


