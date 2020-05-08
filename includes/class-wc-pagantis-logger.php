<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}


class WCPagantisLogger
{

    public static $logger;
    const WC_LOG_FILENAME = 'pagantis-wc';

    /**
     * Utilize WC logger class
     *
     * @param $message
     * @param null $start_time
     * @param null $end_time
     * @since 8.6.7
     */
    public static function writeLog($message, $start_time = null, $end_time = null)
    {
        if (! class_exists('WC_Logger')) {
            return;
        }

        if (apply_filters('wc_stripe_logging', true, $message)) {
            if (empty(self::$logger)) {
                    self::$logger = wc_get_logger();
            }

            $settings = get_option('woocommerce_stripe_settings');

            if (empty($settings) || isset($settings['logging']) && 'yes' !== $settings['logging']) {
                return;
            }

            if (! is_null($start_time)) {
                $formatted_start_time = date_i18n(get_option('date_format') . ' g:ia', $start_time);
                $end_time             = is_null($end_time) ? current_time('timestamp') : $end_time;
                $formatted_end_time   = date_i18n(get_option('date_format') . ' g:ia', $end_time);
                $elapsed_time         = round(abs($end_time - $start_time) / 60, 2);

                $log_entry  = "\n" . '====Pagantis Version: ' . PAGANTIS_VERSION . '====' . "\n";
                $log_entry .= '====Start Log ' . $formatted_start_time . '====' . "\n" . $message . "\n";
                $log_entry .= '====End Log ' . $formatted_end_time . ' (' . $elapsed_time . ')====' . "\n\n";
            } else {
                $log_entry  = "\n" . '====Pagantis Version: ' . PAGANTIS_VERSION . '====' . "\n";
                $log_entry .= '====Start Log====' . "\n" . $message . "\n" . '====End Log====' . "\n\n";
            }
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log($log_entry);
            }
                print_r($log_entry);
                self::$logger->debug($log_entry, array( 'source' => self::WC_LOG_FILENAME ));
        }
    }
}
