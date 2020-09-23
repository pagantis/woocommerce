<?php


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Log helper for debugging
 */
class pagantisLogger
{

    public static $logger;
    const PG_LOG_FILENAME = 'pagantis-woocommerce-gateway';

    /**
     * Utilize WC logger class
     *
     * @param      $message
     * @param null $start_time
     * @param null $end_time
     * @version 1.0.0
     * @since   8.6.9
     */
    public static function log($message, $start_time = null, $end_time = null)
    {

        if (!class_exists('WC_Logger')) {
            return;
        }
        if (empty(self::$logger)) {
            if (version_compare(WC_VERSION, 3.0, '<')) {
                self::$logger = new WC_Logger();
            } else {
                self::$logger = wc_get_logger();
            }


            if (!defined('WP_DEBUG')) {
                return;
            }

            if (!is_null($start_time)) {
                $formatted_start_time = date_i18n(get_option('date_format') . ' g:ia', $start_time);
                $end_time = is_null($end_time) ? current_time('timestamp') : $end_time;
                $formatted_end_time = date_i18n(get_option('date_format') . ' g:ia', $end_time);
                $elapsed_time = round(abs($end_time - $start_time) / 60, 2);

                $log_entry = "\n" . '====Pagantis Version: ' . '====' . "\n";
                $log_entry .= '====Start Log ' . $formatted_start_time . '====' . "\n" . $message . "\n";
                $log_entry .= '====End Log ' . $formatted_end_time . ' (' . $elapsed_time . ')====' . "\n\n";
            } else {
                $log_entry = "\n" . '====Pagantis LOG ====' . PHP_EOL;
                $log_entry .= date_i18n('M j, Y @ G:i' , strtotime( 'now' ), true ) . PHP_EOL;
                $log_entry .= json_encode($message, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) . PHP_EOL;
                $log_entry .= PHP_EOL;
            }

            if (version_compare(WC_VERSION, 3.0, '<')) {
                self::$logger->add(self::PG_LOG_FILENAME, json_encode($log_entry, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) );
            } else {
                self::$logger->debug($log_entry, array('source' => self::PG_LOG_FILENAME));
            }
        }
    }

    public static function pg_debug_backtrace($return = true, $html = false, $show_first = true)
    {

        $d = debug_backtrace();
        $out = '';
        if ($html) {
            $out .= "<pre>";
        }
        foreach ($d as $i => $r) {
            if (!$show_first && $i == 0) {
                continue;
            }
            // sometimes there is undefined index 'file'
            @$out .= "[$i] {$r['file']}:{$r['line']}\n";
        }
        if ($html) {
            $out .= "</pre>";
        }
        if ($return) {
            return $out;
        } else {
            echo $out;
        }
    }


    public static function pg_print_r($var)
    {
        echo '<pre>';
        print_r($var);
        echo '</pre>';
    }

    public static function toSnakeCase($str, $delimiter = '_')
    {
        $str = lcfirst($str);
        $lowerCase = strtolower($str);
        $result = '';
        $length = strlen($str);
        for ($i = 0; $i < $length; $i++) {
            $result .= ($str[$i] === $lowerCase[$i] ? '' : $delimiter) . $lowerCase[$i];
        }
        return $result;
    }

    public static function jsonSerialize($data)
    {
        $arrayProperties = array();

        foreach ($data as $key => $value) {
            $arrayProperties[self::toSnakeCase($key)] = $value;
        }

        return $arrayProperties;
    }

    public static function toJson($initialResponse)
    {
        if (!is_array($initialResponse)){
            return;
        }
        $response = self::jsonSerialize($initialResponse);

        return json_encode($response, JSON_UNESCAPED_SLASHES);

    }

}
