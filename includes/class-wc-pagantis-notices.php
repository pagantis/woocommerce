<?php


class WC_Pagantis_Notices
{


    /**
     * The reference the *Singleton* instance of this class.
     *
     * @var $instance
     */
    protected static $instance;


    /**
     * Checks if WC_Pagantis_Gateway is enabled.
     *
     * @var $enabled
     */
    protected $enabled;

    /**
     * Customizable configuration options
     *
     * @var array $extraConfig
     */
    public $settings = array();


    /**
     * Customizable configuration options
     *
     * @var array $extraConfig
     */

    private $extraConfig;



    /**
     * Array of allowed currencies with Pagantis
     *
     * @var array $allowed_currencies
     */
    private $allowed_currencies;


    /**
     * WC_Pagantis_Notices constructor.
     */
    public function __construct()
    {
        $this->settings = get_option('woocommerce_pagantis_settings');
        $this->enabled  = $this->settings['enabled'];
        require_once dirname(__FILE__) . '/../includes/class-wc-pagantis-config.php';
        require_once dirname(__FILE__) . '/../includes/functions.php';

        $name = dirname(__FILE__) . '/../includes/functions.php';
        var_export($name);
        $this->extraConfig        = WC_Pagantis_Config::getExtraConfig();
        $this->allowed_currencies = array('EUR');
        //add_action('admin_init', array( $this, 'check_plugin_settings' ));
        add_action('admin_notices', array($this, 'check_plugin_settings'));
    }



    /**
     * Returns the *Singleton* instance of this class.
     *
     * @return self::$instance The *Singleton* instance.
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }
    /**
     * Check dependencies.
     *
     * @hook   admin_notices
     * @throws Exception
     */
    public function check_plugin_settings()
    {
        //        if (!pg_wc_is_screen_correct()) {
        //            return;
        //        }
        if ($this->settings['enabled'] !== 'yes') {
            WC_Admin_Settings::add_error(__('Activate Pagantis to start offering comfortable payments in installments to your clients. <a class="button button-primary" href="'. pg_wc_get_pagantis_admin_url().'">Activate Pagantis now!</a>', 'pagantis'));

            WC_Admin_Settings::add_message(__('Activate Pagantis to start offering comfortable payments in installments to your clients. <a class="button button-primary" href="'. pg_wc_get_pagantis_admin_url().'">Activate Pagantis now!</a>', 'pagantis'));

            WC_Admin_Notices::add_custom_notice(
                PAGANTIS_PLUGIN_ID.'keys_setup',
                sprintf('Activate Pagantis to start offering comfortable payments in installments to your clients. <a class="button button-primary" href="'. pg_wc_get_pagantis_admin_url().'">Activate Pagantis now!</a>', 'pagantis')
            );
        }

        if ($this->settings['pagantis_public_key'] === '' xor $this->settings['pagantis_private_key'] === ''
                                                              || $this->settings['enabled'] === 'yes'
        ) {
            WC_Admin_Settings::add_message(
                __('Set your Pagantis merchant keys to start offering comfortable payments in installments  <a class="button button-primary" href="'. pg_wc_get_pagantis_admin_url().'">Activate Pagantis now!</a>', 'pagantis')
            );
                        WC_Admin_Notices::add_custom_notice(
                            PAGANTIS_PLUGIN_ID.'keys_setup',
                            sprintf(
                            // translators: 1:  URL to WP plugin page.
                                __('Set your Pagantis merchant keys to start offering comfortable payments in installments  <a class="button button-primary" href="%1$s">Go to keys setup</a></p>', 'pagantis'),
                                pg_wc_get_pagantis_admin_url()
                            )
                        );
        }


        if ($this->settings['pagantis_public_key'] === '' xor $this->settings['pagantis_private_key'] === '') {
            WC_Admin_Settings::add_error(__('Check your Pagantis merchant keys to start offering Pagantis as a payment method . <a class="button button-primary" href="'. pg_wc_get_pagantis_admin_url().'">Go to keys setup</a>', 'pagantis'));

            //            WC_Admin_Notices::add_custom_notice(
            //                PAGANTIS_PLUGIN_ID,
            //                sprintf(
            //                // translators: 1:  URL to WP plugin page.
            //                    __('Check your Pagantis merchant keys to start offering Pagantis as a payment method .  <a class="button button-primary" href="%1$s">Go to keys setup</a>', 'pagantis'),
            //                    pg_wc_get_pagantis_admin_url()
            //                )
            //            );
        }

        if ($this->settings['pagantis_public_key'] === '' && $this->settings['pagantis_private_key'] === '') {
            WC_Admin_Settings::add_error(__('Set your Pagantis merchant Api keys to start offering comfortable payments in installments. <a class="button button-primary" href="'. pg_wc_get_pagantis_admin_url().'">Go to keys setup</a>', 'pagantis'));

            //            WC_Admin_Notices::add_custom_notice(
            //                PAGANTIS_PLUGIN_ID.'keys_error',
            //                sprintf(
            //                // translators: 1:  URL to WP plugin page.
            //                    __('Set your Pagantis merchant Api keys to start offering comfortable payments in installments.  <a class="button button-primary" href="%1$s">Go to keys setup.</a>', 'pagantis'),
            //                    pg_wc_get_pagantis_admin_url()
            //                )
            //            );
        }
        //
        //        if ($this->settings['pagantis_public_key'] !== '' xor $this->settings['pagantis_private_key'] !== '') {
        //            WC_Admin_Notices::remove_notice(PAGANTIS_PLUGIN_ID.'keys_setup');
        //        }
        //
        //        if ($this->settings['pagantis_public_key'] !== '' || $this->settings['pagantis_private_key'] !== '') {
        //            WC_Admin_Notices::remove_notice(PAGANTIS_PLUGIN_ID.'keys_error');
        //        }

        if (! in_array(get_woocommerce_currency(), $this->allowed_currencies, true)) {
            WC_Admin_Settings::add_error(__('Error: Pagantis only can be used in Euros.', 'pagantis'));
            $this->settings['enabled'] = 'no';
        }

        if ($this->extraConfig['PAGANTIS_SIMULATOR_MAX_INSTALLMENTS'] < '2'
            || $this->extraConfig['PAGANTIS_SIMULATOR_MAX_INSTALLMENTS'] > '12'
        ) {
            $this->settings['enabled'] = 'no';

            WC_Admin_Settings::add_error(__(
                'Error: Pagantis can be used up to 12 installments please contact your account manager',
                'pagantis'
            ));
        }

        if ($this->extraConfig['PAGANTIS_SIMULATOR_START_INSTALLMENTS'] < '2'
            || $this->extraConfig['PAGANTIS_SIMULATOR_START_INSTALLMENTS'] > '12'
        ) {
            WC_Admin_Settings::add_error(__(
                'Error: Pagantis can be used from 2 installments please contact your account manager',
                'pagantis'
            ));
        }
        if ($this->extraConfig['PAGANTIS_DISPLAY_MIN_AMOUNT'] < 0) {
            WC_Admin_Settings::add_error(__('Error: Pagantis can not be used for free products', 'pagantis'));
        }
    }
}
