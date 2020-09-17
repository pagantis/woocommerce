<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Log helper for debugging
 */
class pagantisLogger {

    public static $logger;
    const PG_LOG_FILENAME = 'pagantis-woocommerce-gateway';

    /**
     * Utilize WC logger class
     *
     * @param      $message
     * @param null $start_time
     * @param null $end_time
     *
     * @version 1.0.0
     * @since   8.6.9
     */
    public static function log( $message, $start_time = null, $end_time = null ) {
        if ( ! class_exists( 'WC_Logger' ) ) {
            return;
        }

            if ( empty( self::$logger ) ) {
                if (version_compare( WC_VERSION, 3.0, '<' )) {
                    self::$logger = new WC_Logger();
                } else {
                    self::$logger = wc_get_logger();
                }


            if (!defined( 'WP_DEBUG' )) {
                return;
            }

            if ( ! is_null( $start_time ) ) {

                $formatted_start_time = date_i18n( get_option( 'date_format' ) . ' g:ia', $start_time );
                $end_time             = is_null( $end_time ) ? current_time( 'timestamp' ) : $end_time;
                $formatted_end_time   = date_i18n( get_option( 'date_format' ) . ' g:ia', $end_time );
                $elapsed_time         = round( abs( $end_time - $start_time ) / 60, 2 );

                $log_entry  = "\n" . '====Pagantis Version: ' . PG_VERSION . '====' . "\n";
                $log_entry .= '====Start Log ' . $formatted_start_time . '====' . "\n" . $message . "\n";
                $log_entry .= '====End Log ' . $formatted_end_time . ' (' . $elapsed_time . ')====' . "\n\n";

            } else {
                $log_entry  = "\n" . '====Pagantis Version: ' . PG_VERSION . '====' . "\n";
                $log_entry .= '====Start Log====' . "\n" . $message . "\n" . '====End Log====' . "\n\n";

            }

            if (version_compare( WC_VERSION, 3.0, '<' )) {
                self::$logger->add( self::PG_LOG_FILENAME, $log_entry );
            } else {
                self::$logger->debug( $log_entry, array( 'source' => self::PG_LOG_FILENAME ) );
            }
        }
    }
}
