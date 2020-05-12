<?php
/**
 * Plugin Name: Pagantis
 * Plugin URI: https://www.pagantis.com/
 * Description: Adds Pagantis as payment method to WooCommerce.
 * Version: 8.3.7
 * Author: Pagantis
 * Domain Path: /languages
 * WC requires at least: 3.0
 */

//namespace Gateways;


if (! defined('ABSPATH')) {
    exit;
}
define('PAGANTIS_VERSION', '8.3.7');

define('PAGANTIS_WC_MAIN_FILE', __FILE__);
define(
    'PAGANTIS_PLUGIN_URL',
    untrailingslashit(plugins_url(basename(plugin_dir_path(PAGANTIS_WC_MAIN_FILE)), basename(PAGANTIS_WC_MAIN_FILE)))
);
define('PAGANTIS_PLUGIN_PATH', untrailingslashit(plugin_dir_path(PAGANTIS_WC_MAIN_FILE)));
define('PAGANTIS_ORDERS_TABLE', 'cart_process');
define('PAGANTIS_WC_ORDERS_TABLE', 'posts');
define('PAGANTIS_LOGS_TABLE', 'pagantis_logs');
define('PAGANTIS_NOT_CONFIRMED_MESSAGE', 'No se ha podido confirmar el pago');
define('PAGANTIS_CONFIG_TABLE', 'pagantis_config');
define('PAGANTIS_CONCURRENCY_TABLE', 'pagantis_concurrency');
define('PAGANTIS_GIT_HUB_URL', 'https://github.com/pagantis/woocommerce');
define('PAGANTIS_DOC_URL', 'https://developer.pagantis.com');
define('PAGANTIS_SUPPORT_EMAIL', 'mailto:integrations@pagantis.com?Subject=woocommerce_plugin');
define('PAGANTIS_PLUGIN_ID', 'pagantis');


class WC_Pagantis_Plugin
{


    /**
     * The reference the *Singleton* instance of this class.
     *
     * @var $instance
     */
    private static $instance;


    /**
     * @var array $defaultConfig
     */
    private $defaultConfig;

    /**
     * @var array $extraConfig
     */
    private $extraConfig;


    /**
     * WC_Pagantis constructor.
     */
    public function __construct()
    {
        require_once(plugin_dir_path(__FILE__) . 'vendor/autoload.php');
        require_once dirname(__FILE__) . '/includes/class-wc-pagantis-config.php';
        require_once dirname(__FILE__) . '/includes/functions.php';

        $this->template_path = plugin_dir_path(__FILE__) . 'templates/';

        $this->prepare_wpdb_tables();
        $this->initialConfig = WC_Pagantis_Config::getDefaultConfig();

        $this->extraConfig = WC_Pagantis_Config::getExtraConfig();
        add_action('plugins_loaded', array($this, 'bootstrap'));
        load_plugin_textdomain('pagantis', false, basename(dirname(__FILE__)) . '/languages');
        add_filter('woocommerce_payment_gateways', array($this, 'add_pagantis_gateway'));
        add_filter('woocommerce_available_payment_gateways', array($this, 'check_if_pg_is_in_available_gateways'), 9999);
        add_filter('plugin_row_meta', array($this, 'get_plugin_row_meta_links'), 10, 2);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'get_plugin_action_links'));

        add_action('wp_enqueue_scripts', 'add_pagantis_widget_js');
        add_action('rest_api_init', array($this, 'register_pg_rest_routes')); //Endpoint
        add_filter('load_textdomain_mofile', array($this, 'loadPagantisTranslation'), 10, 2);
        register_activation_hook(__FILE__, array($this, 'prepare_wpdb_tables'));
        add_action('woocommerce_product_options_general_product_data', array($this, 'pagantisPromotedProductTpl'));
        add_action('woocommerce_process_product_meta', array($this, 'pagantisPromotedVarSave'));
        add_action('woocommerce_product_bulk_edit_start', array($this, 'pagantis_promoted_bulk_template'));
        add_action('woocommerce_product_bulk_edit_save', array($this, 'save_pg_promoted_bulk_template'));
        add_action('woocommerce_after_add_to_cart_form', array($this, 'pagantisAddProductSimulator'));
        //add_action('wp_enqueue_scripts', array($this, 'enqueue_simulator_scripts'));
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
     * Private clone method to prevent cloning of the instance of the
     * *Singleton* instance.
     *
     * @return void
     */
    private function __clone()
    {
    }

    /**
     * Private unserialize method to prevent unserializing of the *Singleton*
     * instance.
     *
     * @return void
     */
    private function __wakeup()
    {
    }

    public function bootstrap()
    {
        try {
            $this->check_dependencies();
        } catch (Exception $e) {
            $e->getMessage();
        }
    }

    /**
     * @throws Exception
     */
    public function check_dependencies()
    {
        if (version_compare(WC()->version, '3.0', '<')) {
            throw new Exception(__('Pagantis requires WooCommerce version 3.0 or greater', 'pagantis'));
        }

        if (! function_exists('curl_init')) {
            throw new Exception(__('Pagantis requires cURL to be installed on your server', 'pagantis'));
        }
        if (! version_compare(phpversion(), '5.3.0', '>=')) {
            throw new Exception(__('Pagantis requires PHP 5.3 or greater to be installed on your server', 'pagantis'));
        }
    }

    /**
     * Piece of html code to insert into BULK admin edit
     */
    public function pagantis_promoted_bulk_template()
    {
        echo '<div class="inline-edit-group">
			<label class="alignleft">
				<span class="title">Pagantis promoted</span>
				<span class="input-text-wrap">
                    <input type="checkbox" id="pagantis_promoted" name="pagantis_promoted"/>
				</span>
			</label>
		</div>';
    }

    /**
     * Php code to save our meta after a bulk admin edit
     *
     * @param $product
     */
    public function save_pg_promoted_bulk_template($product)
    {
        $post_id                 = $product->get_id();
        $pagantis_promoted_value = $_REQUEST['pagantis_promoted'];
        if ($pagantis_promoted_value === 'on') {
            $pagantis_promoted_value = 'yes';
        } else {
            $pagantis_promoted_value = 'no';
        }

        update_post_meta($post_id, 'custom_product_pagantis_promoted', esc_attr($pagantis_promoted_value));
    }

    /**
     * Piece of html code to insert into PRODUCT admin edit
     */
    public function pagantisPromotedProductTpl()
    {
        global $post;
        $_product = get_post_meta($post->ID);
        woocommerce_wp_checkbox(array(
            'id'      => 'pagantis_promoted',
            'label'   => __('Pagantis promoted', 'woocommerce'), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
            'value'   => $_product['custom_product_pagantis_promoted']['0'],
            'cbvalue' => 'yes',
            'echo'    => true,
        ));
    }

    /**
     *  Php code to save our meta after a PRODUCT admin edit
     *
     * @param $post_id
     */
    public function pagantisPromotedVarSave($post_id)
    {
        $pagantis_promoted_value = $_POST['pagantis_promoted'];
        if ($pagantis_promoted_value === null) {
            $pagantis_promoted_value = 'no';
        }
        update_post_meta($post_id, 'custom_product_pagantis_promoted', esc_attr($pagantis_promoted_value));
    }

    /*
     * Replace 'textdomain' with your plugin's textdomain. e.g. 'woocommerce'.
     * File to be named, for example, yourtranslationfile-en_GB.mo
     * File to be placed, for example, wp-content/languages/textdomain/yourtranslationfile-en_GB.mo
     */
    public function loadPagantisTranslation($mofile, $domain)
    {
        if ('pagantis' === $domain) {
            $mofile = WP_LANG_DIR . '/../plugins/pagantis/languages/pagantis-' . get_locale() . '.mo';
        }

        return $mofile;
    }

    /**
     * Sql table
     */
    public function prepare_wpdb_tables()
    {
        global $wpdb;

        $tableName = $wpdb->prefix . PAGANTIS_CONCURRENCY_TABLE;
        if ($wpdb->get_var("SHOW TABLES LIKE '$tableName'") !== $tableName) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql             = "CREATE TABLE $tableName ( order_id int NOT NULL,  
                    createdAt timestamp DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY id (order_id)) $charset_collate";
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        $tableName = $wpdb->prefix . PAGANTIS_CONFIG_TABLE;

        //Check if table exists
        $tableExists = $wpdb->get_var("SHOW TABLES LIKE '$tableName'") !== $tableName;
        if ($tableExists) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql             = "CREATE TABLE IF NOT EXISTS $tableName (
                                id int NOT NULL AUTO_INCREMENT, 
                                config varchar(60) NOT NULL, 
                                value varchar(1000) NOT NULL, 
                                UNIQUE KEY id(id)) $charset_collate";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        } else {
            //Updated value field to adapt to new length < v8.0.1
            $query   = "select COLUMN_TYPE FROM information_schema.COLUMNS where TABLE_NAME='$tableName' AND COLUMN_NAME='value'";
            $results = $wpdb->get_results($query, ARRAY_A);
            if ($results['0']['COLUMN_TYPE'] === 'varchar(100)') {
                $sql = "ALTER TABLE $tableName MODIFY value varchar(1000)";
                $wpdb->query($sql);
            }

            //Adapting selector to array < v8.1.1
            $query           = "select * from $tableName where config='PAGANTIS_SIMULATOR_CSS_QUANTITY_SELECTOR' 
                               or config='PAGANTIS_SIMULATOR_CSS_PRICE_SELECTOR'";
            $dbCurrentConfig = $wpdb->get_results($query, ARRAY_A);
            foreach ($dbCurrentConfig as $item) {
                if ($item['config'] === 'PAGANTIS_SIMULATOR_CSS_PRICE_SELECTOR') {
                    $css_price_selector = $this->preparePriceSelector($item['value']);
                    if ($item['value'] !== $css_price_selector) {
                        $wpdb->update(
                            $tableName,
                            array('value' => stripslashes($css_price_selector)),
                            array('config' => 'PAGANTIS_SIMULATOR_CSS_PRICE_SELECTOR'),
                            array('%s'),
                            array('%s')
                        );
                    }
                } elseif ($item['config'] === 'PAGANTIS_SIMULATOR_CSS_QUANTITY_SELECTOR') {
                    $css_quantity_selector = $this->prepareQuantitySelector($item['value']);
                    if ($item['value'] !== $css_quantity_selector) {
                        $wpdb->update(
                            $tableName,
                            array('value' => stripslashes($css_quantity_selector)),
                            array('config' => 'PAGANTIS_SIMULATOR_CSS_QUANTITY_SELECTOR'),
                            array('%s'),
                            array('%s')
                        );
                    }
                }
            }
        }

        //Adapting selector to array < v8.2.2
        $tableName = $wpdb->prefix . PAGANTIS_CONFIG_TABLE;
        $query     = "select * from $tableName where config='PAGANTIS_SIMULATOR_THOUSANDS_SEPARATOR'";
        $results   = $wpdb->get_results($query, ARRAY_A);
        if (count($results) === 0) {
            $wpdb->insert(
                $tableName,
                array('config' => 'PAGANTIS_SIMULATOR_THOUSANDS_SEPARATOR', 'value' => '.'),
                array('%s', '%s')
            );
            $wpdb->insert(
                $tableName,
                array('config' => 'PAGANTIS_SIMULATOR_DECIMAL_SEPARATOR', 'value' => ','),
                array('%s', '%s')
            );
        }

        //Adding new selector < v8.3.0
        $tableName = $wpdb->prefix . PAGANTIS_CONFIG_TABLE;
        $query     = "select * from $tableName where config='PAGANTIS_DISPLAY_MAX_AMOUNT'";
        $results   = $wpdb->get_results($query, ARRAY_A);
        if (count($results) === 0) {
            $wpdb->insert($tableName, array('config' => 'PAGANTIS_DISPLAY_MAX_AMOUNT', 'value' => '0'), array('%s', '%s'));
        }

        //Adding new selector < v8.3.2
        $tableName = $wpdb->prefix . PAGANTIS_CONFIG_TABLE;
        $query     = "select * from $tableName where config='PAGANTIS_SIMULATOR_DISPLAY_SITUATION'";
        $results   = $wpdb->get_results($query, ARRAY_A);
        if (count($results) === 0) {
            $wpdb->insert(
                $tableName,
                array('config' => 'PAGANTIS_SIMULATOR_DISPLAY_SITUATION', 'value' => 'default'),
                array('%s', '%s')
            );
            $wpdb->insert(
                $tableName,
                array('config' => 'PAGANTIS_SIMULATOR_SELECTOR_VARIATION', 'value' => 'default'),
                array('%s', '%s')
            );
        }

        //Adding new selector < v8.3.3
        $tableName = $wpdb->prefix . PAGANTIS_CONFIG_TABLE;
        $query     = "select * from $tableName where config='PAGANTIS_SIMULATOR_DISPLAY_TYPE_CHECKOUT'";
        $results   = $wpdb->get_results($query, ARRAY_A);
        if (count($results) === 0) {
            $wpdb->insert($tableName, array(
                'config' => 'PAGANTIS_SIMULATOR_DISPLAY_TYPE_CHECKOUT',
                'value'  => 'sdk.simulator.types.CHECKOUT_PAGE',
            ), array('%s', '%s'));
            $wpdb->update(
                $tableName,
                array('value' => 'sdk.simulator.types.PRODUCT_PAGE'),
                array('config' => 'PAGANTIS_SIMULATOR_DISPLAY_TYPE'),
                array('%s'),
                array('%s')
            );
        }

        //Adapting to variable selector < v8.3.6
        $variableSelector = 'div.summary div.woocommerce-variation.single_variation > div.woocommerce-variation-price span.price';
        $tableName        = $wpdb->prefix . PAGANTIS_CONFIG_TABLE;
        $query            = "select * from $tableName where config='PAGANTIS_SIMULATOR_SELECTOR_VARIATION' and value='default'";
        $results          = $wpdb->get_results($query, ARRAY_A);
        if (count($results) === 0) {
            $wpdb->update(
                $tableName,
                array('value' => $variableSelector),
                array('config' => 'PAGANTIS_SIMULATOR_SELECTOR_VARIATION'),
                array('%s'),
                array('%s')
            );
        }

        $dbConfigs = $wpdb->get_results("select * from $tableName", ARRAY_A);

        // Convert a multiple dimension array for SQL insert statements into a simple key/value
        $simpleDbConfigs = array();
        foreach ($dbConfigs as $config) {
            $simpleDbConfigs[$config['config']] = $config['value'];
        }
        $newConfigs = array_diff_key(WC_Pagantis_Config::getDefaultConfig(), $simpleDbConfigs);
        if (! empty($newConfigs)) {
            foreach ($newConfigs as $key => $value) {
                $wpdb->insert($tableName, array('config' => $key, 'value' => $value), array('%s', '%s'));
            }
        }

        //Current plugin config: pagantis_public_key => New field --- public_key => Old field
        $settings = get_option('woocommerce_pagantis_settings');

        if (! isset($settings['pagantis_public_key']) && $settings['public_key']) {
            $settings['pagantis_public_key'] = $settings['public_key'];
            unset($settings['public_key']);
        }

        if (! isset($settings['pagantis_private_key']) && $settings['secret_key']) {
            $settings['pagantis_private_key'] = $settings['secret_key'];
            unset($settings['secret_key']);
        }

        update_option('woocommerce_pagantis_settings', $settings);
    }


    public function enqueue_simulator_scripts()
    {
        if (! pg_isPluginActive()) {
            return;
        }

        wp_register_script(
            'pagantis-simulator',
            plugins_url('assets/js/pagantis-simulator.js', PAGANTIS_PLUGIN_ID),
            array('jquery'),
            ''
        );
        wp_enqueue_script('pagantis-simulator');

        global $product;

        pg_canProductSimulatorLoad();
        $locale   = pg_GetLocaleString();
        $settings = pg_get_plugin_settings();

        $post_id                    = $product->get_id();
        $simulator_localized_params = array(
            'total'                     => is_numeric($product->get_price()) ? $product->get_price() : 0,
            'public_key'                => $settings['pagantis_public_key'],
            'simulator_type'            => WC_Pagantis_Config::getValueOfKey('PAGANTIS_SIMULATOR_DISPLAY_TYPE'),
            'positionSelector'          => WC_Pagantis_Config::getValueOfKey('PAGANTIS_SIMULATOR_CSS_POSITION_SELECTOR'),
            'quantitySelector'          => WC_Pagantis_Config::getValueOfKey('PAGANTIS_SIMULATOR_CSS_QUANTITY_SELECTOR', true),
            'priceSelector'             => WC_Pagantis_Config::getValueOfKey('PAGANTIS_SIMULATOR_CSS_PRICE_SELECTOR', true),
            'totalAmount'               => is_numeric($product->get_price()) ? $product->get_price() : 0,
            'locale'                    => $locale,
            'country'                   => $locale,
            'isProductPromoted'         => pg_isProductPromoted($post_id),
            'promotedMessage'           => WC_Pagantis_Config::getValueOfKey('PAGANTIS_PROMOTION_EXTRA'),
            'thousandSeparator'         => WC_Pagantis_Config::getValueOfKey('PAGANTIS_SIMULATOR_THOUSANDS_SEPARATOR'),
            'decimalSeparator'          => WC_Pagantis_Config::getValueOfKey('PAGANTIS_SIMULATOR_DECIMAL_SEPARATOR'),
            'pagantisQuotesStart'       => WC_Pagantis_Config::getValueOfKey('PAGANTIS_SIMULATOR_START_INSTALLMENTS'),
            'pagantisSimulatorSkin'     => WC_Pagantis_Config::getValueOfKey('PAGANTIS_SIMULATOR_DISPLAY_SKIN'),
            'pagantisSimulatorPosition' => WC_Pagantis_Config::getValueOfKey('PAGANTIS_SIMULATOR_DISPLAY_CSS_POSITION'),
            'finalDestination'          => WC_Pagantis_Config::getValueOfKey('PAGANTIS_SIMULATOR_DISPLAY_SITUATION'),
            'variationSelector'         => WC_Pagantis_Config::getValueOfKey('PAGANTIS_SIMULATOR_SELECTOR_VARIATION'),
            'productType'               => $product->get_type(),
        );

        wp_localize_script('pagantis-simulator', 'pg_sim_params', $simulator_localized_params);

        wp_enqueue_script('pg_sim_params');
    }


    /**
     * Product simulator
     */
    public function addProductSimulatorTemplate()
    {
        global $product;

        pg_canProductSimulatorLoad();

        $post_id            = $product->get_id();
        $template_arguments = array(
            'isProductPromoted' => pg_isProductPromoted($post_id),
            'promotedMessage'   => WC_Pagantis_Config::getValueOfKey('PAGANTIS_PROMOTION_EXTRA'),
        );

        wc_get_template('product_simulator.php', $template_arguments, '', $this->template_path);
    }

    /**
     * Product simulator
     *
     * @global WC_Product $product Product object.
     */
    public function pagantisAddProductSimulator()
    {
        global $product;

        $cfg              = get_option('woocommerce_pagantis_settings');
        $locale           = strtolower(strstr(get_locale(), '_', true));
        $allowedCountries = unserialize($this->extraConfig['PAGANTIS_ALLOWED_COUNTRIES']);
        $allowedCountry   = (in_array(strtolower($locale), $allowedCountries));
        $minAmount        = $this->extraConfig['PAGANTIS_DISPLAY_MIN_AMOUNT'];
        $maxAmount        = $this->extraConfig['PAGANTIS_DISPLAY_MAX_AMOUNT'];
        $totalPrice       = $product->get_price();
        $validAmount      = ($totalPrice >= $minAmount && ($totalPrice <= $maxAmount || $maxAmount === '0'));
        if ($cfg['enabled'] !== 'yes' || $cfg['pagantis_public_key'] === '' || $cfg['pagantis_private_key'] === ''
            || $cfg['simulator'] !== 'yes'
            || ! $allowedCountry
            || ! $validAmount
        ) {
            return;
        }

        $post_id         = $product->get_id();
        $template_fields = array(
            'total'                     => is_numeric($product->get_price()) ? $product->get_price() : 0,
            'public_key'                => $cfg['pagantis_public_key'],
            'simulator_type'            => $this->extraConfig['PAGANTIS_SIMULATOR_DISPLAY_TYPE'],
            'positionSelector'          => $this->extraConfig['PAGANTIS_SIMULATOR_CSS_POSITION_SELECTOR'],
            'quantitySelector'          => unserialize($this->extraConfig['PAGANTIS_SIMULATOR_CSS_QUANTITY_SELECTOR']),
            'priceSelector'             => unserialize($this->extraConfig['PAGANTIS_SIMULATOR_CSS_PRICE_SELECTOR']),
            'totalAmount'               => is_numeric($product->get_price()) ? $product->get_price() : 0,
            'locale'                    => $locale,
            'country'                   => $locale,
            'promoted'                  => pg_isProductPromoted($post_id),
            'promotedMessage'           => $this->extraConfig['PAGANTIS_PROMOTION_EXTRA'],
            'thousandSeparator'         => $this->extraConfig['PAGANTIS_SIMULATOR_THOUSANDS_SEPARATOR'],
            'decimalSeparator'          => $this->extraConfig['PAGANTIS_SIMULATOR_DECIMAL_SEPARATOR'],
            'pagantisQuotesStart'       => $this->extraConfig['PAGANTIS_SIMULATOR_START_INSTALLMENTS'],
            'pagantisSimulatorSkin'     => $this->extraConfig['PAGANTIS_SIMULATOR_DISPLAY_SKIN'],
            'pagantisSimulatorPosition' => $this->extraConfig['PAGANTIS_SIMULATOR_DISPLAY_CSS_POSITION'],
            'finalDestination'          => $this->extraConfig['PAGANTIS_SIMULATOR_DISPLAY_SITUATION'],
            'variationSelector'         => $this->extraConfig['PAGANTIS_SIMULATOR_SELECTOR_VARIATION'],
            'productType'               => $product->get_type(),
        );
        WC_Pagantis_Logger::insert_log_entry_in_wpdb($template_fields);
        wc_get_template('product_simulator.php', $template_fields, '', $this->template_path);
    }


    /**
     * Add Pagantis to payments list.
     *
     * @param $methods
     *
     * @return array
     * @hook woocommerce_payment_gateways
     */
    public function add_pagantis_gateway($methods)
    {
        if (! class_exists('WC_Payment_Gateway')) {
            return $methods;
        }

        include_once('controllers/class-wc-pagantis-gateway.php');
        $methods[] = 'WC_Pagantis_Gateway';

        return $methods;
    }

    /**
     * Initialize WC_Pagantis class
     *
     * @param $methods
     *
     * @return mixed
     */
    public function check_if_pg_is_in_available_gateways($methods)
    {
        $pagantis = new WC_Pagantis_Gateway();
        if (! $pagantis->is_available()) {
            unset($methods['pagantis']);
        }

        return $methods;
    }

    /**
     * Add links to Plugin description in WP Plugins panel
     *
     * @param $links
     *
     * @return mixed
     * @hook plugin_action_links_pagantis
     */
    public function get_plugin_action_links($links)
    {
        $setting_link = $this->get_setting_link();
        $plugin_links = array(
            '<a href="' . $setting_link . '">' . __('Settings', 'pagantis') . '</a>',
        );

        return array_merge($plugin_links, $links);
    }


    public function get_setting_link()
    {
        $section_slug = 'pagantis';

        return admin_url('admin.php?page=wc-settings&tab=checkout&section=' . $section_slug);
    }

    /**
     * Add links to Plugin options
     *
     * @param $links
     * @param $file
     *
     * @hook plugin_row_meta
     * @return array
     */
    public function get_plugin_row_meta_links($links, $file)
    {
        if ($file === plugin_basename(__FILE__)) {
            $links[] = '<a href="' . PAGANTIS_GIT_HUB_URL . '" target="_blank">' . __('Documentation', 'pagantis') . '</a>';
            $links[] = '<a href="' . PAGANTIS_DOC_URL . '" target="_blank">' . __('API documentation', 'pagantis') . '</a>';
            $links[] = '<a href="' . PAGANTIS_SUPPORT_EMAIL . '">' . __('Support', 'pagantis') . '</a>';

            return $links;
        }

        return $links;
    }

    /**
     * Read logs
     *
     * @param       $data
     *
     * @global wpdb $wpdb WordPress database abstraction object.
     */
    public function get_pagantis_logs($data)
    {
        global $wpdb;
        $filters    = ($data->get_params());
        $response   = array();
        $secretKey  = $filters['secret'];
        $from       = $filters['from'];
        $to         = $filters['to'];
        $cfg        = get_option('woocommerce_pagantis_settings');
        $privateKey = isset($cfg['pagantis_private_key']) ? $cfg['pagantis_private_key'] : null;
        $tableName  = $wpdb->prefix . PAGANTIS_LOGS_TABLE;
        $query      = "SELECT * FROM $tableName WHERE createdAt>$from AND createdAt<$to ORDER BY createdAt DESC";
        $results    = $wpdb->get_results($query);
        if (isset($results) && $privateKey === $secretKey) {
            foreach ($results as $key => $result) {
                $response[$key]['timestamp'] = $result->createdAt;
                $response[$key]['log']       = json_decode($result->log);
            }
        } else {
            $response['result'] = 'Error';
        }
        $response = json_encode($response);
        header('HTTP/1.1 200', true, 200);
        header('Content-Type: application/json', true);
        header('Content-Length: ' . strlen($response));
        echo($response);
        exit();
    }

    /**
     * Update extra config
     *
     * @param $data
     */
    public function updateExtraConfig($data)
    {
        global $wpdb;
        $tableName = $wpdb->prefix . PAGANTIS_CONFIG_TABLE;
        $response  = array('status' => null);

        $filters    = ($data->get_params());
        $secretKey  = $filters['secret'];
        $cfg        = get_option('woocommerce_pagantis_settings');
        $privateKey = isset($cfg['pagantis_private_key']) ? $cfg['pagantis_private_key'] : null;
        if ($privateKey !== $secretKey) {
            $response['status'] = 401;
            $response['result'] = 'Unauthorized';
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (count($_POST)) {
                foreach ($_POST as $config => $value) {
                    if (isset($this->initialConfig[$config]) && $response['status'] === null) {
                        $wpdb->update(
                            $tableName,
                            array('value' => stripslashes($value)),
                            array('config' => $config),
                            array('%s'),
                            array('%s')
                        );
                    } else {
                        $response['status'] = 400;
                        $response['result'] = 'Bad request';
                    }
                }
            } else {
                $response['status'] = 422;
                $response['result'] = 'Empty data';
            }
        }

        if ($response['status'] === null) {
            $tableName = $wpdb->prefix . PAGANTIS_CONFIG_TABLE;
            $dbResult  = $wpdb->get_results("select config, value from $tableName", ARRAY_A);
            foreach ($dbResult as $value) {
                $formattedResult[$value['config']] = $value['value'];
            }
            $response['result'] = $formattedResult;
        }

        $result = json_encode($response['result']);
        header('HTTP/1.1 ' . $response['status'], true, $response['status']);
        header('Content-Type: application/json', true);
        header('Content-Length: ' . strlen($result));
        echo($result);
        exit();
    }

    /**
     * Read logs
     *
     * @param $data
     */
    public function readApi($data)
    {
        global $wpdb;
        $filters        = ($data->get_params());
        $response       = array('timestamp' => time());
        $secretKey      = $filters['secret'];
        $from           = ($filters['from']) ? date_create($filters['from']) : date('Y-m-d', strtotime('-7 day'));
        $to             = ($filters['to']) ? date_create($filters['to']) : date('Y-m-d', strtotime('+1 day'));
        $method         = ($filters['method']) ? ($filters['method']) : 'Pagantis';
        $cfg            = get_option('woocommerce_pagantis_settings');
        $privateKey     = isset($cfg['pagantis_private_key']) ? $cfg['pagantis_private_key'] : null;
        $tableName      = $wpdb->prefix . PAGANTIS_WC_ORDERS_TABLE;
        $tableNameInner = $wpdb->prefix . 'postmeta';
        $query          = "SELECT * FROM $tableName tn INNER JOIN $tableNameInner tn2 ON tn2.post_id = tn.id
                  WHERE tn.post_type='shop_order' AND tn.post_date>'" . $from->format('Y-m-d') . "' 
                  AND tn.post_date<'" . $to->format('Y-m-d') . "' ORDER BY tn.post_date DESC";
        $results        = $wpdb->get_results($query);

        if (isset($results) && $privateKey === $secretKey) {
            foreach ($results as $result) {
                $key                                          = $result->ID;
                $response['message'][$key]['timestamp']       = $result->post_date;
                $response['message'][$key]['order_id']        = $key;
                $response['message'][$key][$result->meta_key] = $result->meta_value;
            }
        } else {
            $response['result'] = 'Error';
        }
        $response = json_encode($response);
        header('HTTP/1.1 200', true, 200);
        header('Content-Type: application/json', true);
        header('Content-Length: ' . strlen($response));
        echo($response);
        exit();
    }

    /**
     * ENDPOINT - Read logs -> Hook: rest_api_init
     *
     * @hook rest_api_init
     * @return mixed
     */
    public function register_pg_rest_routes()
    {
        register_rest_route('pagantis/v1', '/logs/(?P<secret>\w+)/(?P<from>\d+)/(?P<to>\d+)', array(
            'methods'  => 'GET',
            'callback' => array(
                $this,
                'get_pagantis_logs',
            ),
        ), true);

        register_rest_route('pagantis/v1', '/configController/(?P<secret>\w+)', array(
            'methods'  => 'GET, POST',
            'callback' => array(
                $this,
                'updateExtraConfig',
            ),
        ), true);

        register_rest_route('pagantis/v1', '/api/(?P<secret>\w+)/(?P<from>\w+)/(?P<to>\w+)', array(
            'methods'  => 'GET',
            'callback' => array(
                $this,
                'readApi',
            ),
        ), true);
    }

    /**
     * @param $css_quantity_selector
     *
     * @return mixed|string
     */
    private function prepareQuantitySelector($css_quantity_selector)
    {
        if ($css_quantity_selector === 'default' || $css_quantity_selector === '') {
            $css_quantity_selector = $this->initialConfig['PAGANTIS_SIMULATOR_CSS_QUANTITY_SELECTOR'];
        } elseif (! unserialize($css_quantity_selector)) { //in the case of a custom string selector, we keep it
            $css_quantity_selector = serialize(array($css_quantity_selector));
        }

        return $css_quantity_selector;
    }

    /**
     * @param $css_price_selector
     *
     * @return mixed|string
     */
    private function preparePriceSelector($css_price_selector)
    {
        if ($css_price_selector === 'default' || $css_price_selector === '') {
            $css_price_selector = $this->initialConfig['PAGANTIS_SIMULATOR_CSS_PRICE_SELECTOR'];
        } elseif (! unserialize($css_price_selector)) { //in the case of a custom string selector, we keep it
            $css_price_selector = serialize(array($css_price_selector));
        }

        return $css_price_selector;
    }
}

/**
 * Add widget Js
 **/
function add_pagantis_widget_js()
{
    wp_enqueue_script('pgSDK', 'https://cdn.pagantis.com/js/pg-v2/sdk.js', '', '', true);
}

WC_Pagantis_Plugin::get_instance();

/**
 * Main instance WC_Pagantis_Plugin.
 *
 * Returns the main instance of WC_Pagantis_Plugin.
 *
 * @return WC_Pagantis_Plugin
 */
function PG_WC() // phpcs:ignore
{
    return WC_Pagantis_Plugin::get_instance();
}
