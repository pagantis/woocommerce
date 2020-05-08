<?php

if (! defined('ABSPATH')) {
    exit;
}

class WC_Pagantis_Ajax extends WC_AJAX
{

    /**
     * Hook in ajax handlers.
     */
    public static function init()
    {
        self::add_ajax_events();
    }

    /**
     * Hook in methods - uses WordPress ajax handlers (admin-ajax).
     *
     * @see WC_AJAX
     */
    public static function add_ajax_events()
    {
        $ajax_events = array('pagantis_checkout');
        foreach ($ajax_events as $ajax_event => $nopriv) {
            add_action('wp_ajax_woocommerce_' . $ajax_event, array(__CLASS__, $ajax_event));
            if ($nopriv) {
                add_action(
                    'wp_ajax_nopriv_woocommerce_' . $ajax_event,
                    array(__CLASS__, $ajax_event)
                );
                // WC AJAX can be used for frontend ajax requests.
                add_action('wc_ajax_' . $ajax_event, array(__CLASS__, $ajax_event));
            }
        }
    }


    /**
     * Check for WC Ajax request and fire action.
     */
    public static function pagantis_checkout()
    {
    }
}

WC_Pagantis_Ajax::init();
