<?php


require_once(PG_ABSPATH . '/includes/class-pg-wc-logger.php');


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
    if (!isPgLogsTableCreated()) {
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
    $wc_decimal_sep= get_option('woocommerce_price_decimal_sep');
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
    $wc_price_thousand = get_option('woocommerce_price_thousand_sep');
    if (stripslashes($wc_price_thousand)== stripslashes($pgThousandSeparator)) {
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
    $wpdb->update($tableName, array('value' => $thousandSeparator), array('config' => 'PAGANTIS_SIMULATOR_THOUSANDS_SEPARATOR'), array('%s'), array('%s'));

}

function updateDecimalSeparatorDbConfig()
{
    global $wpdb;
    if (areDecimalSeparatorEqual()) {
        return;
    }
    $tableName        = $wpdb->prefix . PG_CONFIG_TABLE_NAME;
    $decimalSeparator = get_option('woocommerce_price_decimal_sep');
    $wpdb->update($tableName, array('value' => $decimalSeparator), array('config' => 'PAGANTIS_SIMULATOR_DECIMAL_SEPARATOR'), array('%s'), array('%s'));
}