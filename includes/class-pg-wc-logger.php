<?php


defined('ABSPATH') || exit;

/**
 * Log all things!
 *
 * @since    8.3.8
 * @version  1.0.0
 */
class PG_WC_Logger
{

    public static $logger;
    const WC_LOG_FILENAME = PAGANTIS_PLUGIN_ID;

    /**
     * Utilize WC logger class
     *
     * @param      $message
     * @param null $start_time
     * @param null $end_time
     *
     * @version 1.0.0
     * @since   8.3.8
     */
    public static function log($message, $start_time = null, $end_time = null)
    {
        if (! class_exists('WC_Logger')) {
            return;
        }

        if (apply_filters('wc_pagantis_logging', true, $message)) {
            if (empty(self::$logger)) {
                self::$logger = wc_get_logger();
            }
        }

        $settings = get_option('woocommerce_pagantis_settings');

        if (empty($settings) || isset($settings['debug']) && 'yes' !== $settings['debug']) {
            return;
        }

        if (! is_null($start_time)) {
            $formatted_start_time = date_i18n(get_option('date_format') . ' g:ia', $start_time);
            $end_time             = is_null($end_time) ? current_time('timestamp') : $end_time;
            $formatted_end_time   = date_i18n(get_option('date_format') . ' g:ia', $end_time);
            $elapsed_time         = round(abs($end_time - $start_time) / 60, 2);

            $log_entry = "\n" . '====Pagantis Version: ' . PG_VERSION . '====' . "\n";
            $log_entry .= '====Start Log ' . $formatted_start_time . '====' . "\n" . $message . "\n";
            $log_entry .= '====End Log ' . $formatted_end_time . ' (' . $elapsed_time . ')====' . "\n\n";
        } else {
            $log_entry = "\n" . '====Pagantis Version: ' . PG_VERSION . '====' . "\n";
            $log_entry .= '====Start Log====' . "\n" . wp_json_encode($message) . "\n" . '====End Log====' . "\n\n";
        }

        self::$logger->debug($log_entry, array('source' => self::WC_LOG_FILENAME));
    }


    /**
     * @param      $message
     * @param null $start_time
     * @param null $end_time
     */
    public static function logToDB($message, $start_time = null, $end_time = null)
    {
        if (! class_exists('WC_Log_Handler_DB')) {
            return;
        }

        if (apply_filters('wc_pagantis_logging_to_db', true, $message)) {
            $handler = new WC_Log_Handler_DB();


            $settings = get_option('woocommerce_pagantis_settings');

            if (empty($settings) || isset($settings['debug']) && 'yes' !== $settings['debug']) {
                return;
            }
            $handler->handle(
                time(),
                'debug',
                wp_json_encode($message),
                array('source' => self::WC_LOG_FILENAME)
            );
        }
    }
}
