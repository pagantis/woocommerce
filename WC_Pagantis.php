<?php
/**
 * Plugin Name: Pagantis
 * Plugin URI: http://www.pagantis.com/
 * Description: Financiar con Pagantis
 * Version: 8.6.14
 * Author: Pagantis
 *
 * Text Domain: pagantis
 * Domain Path: /languages/
 *
 */

//namespace Gateways;


if (!defined('ABSPATH')) {
    exit;
}


require_once(__DIR__ . '/includes/pg-functions.php');

/**
 * Required minimums and constants
 */
define('PG_WC_MAIN_FILE', __FILE__);
define('PG_ABSPATH', trailingslashit(dirname(PG_WC_MAIN_FILE)));
define('PG_VERSION', getModuleVersion());
define('PG_ROOT', dirname(__DIR__));
define('PG_CONFIG_TABLE_NAME', 'pagantis_config');
define('PG_LOGS_TABLE_NAME', 'pagantis_logs');
define('PG_CONCURRENCY_TABLE_NAME', 'pagantis_concurrency');
define('PG_CART_PROCESS_TABLE', 'cart_process');
define('PG_ORDERS_TABLE', 'posts');


class WcPagantis
{
    const GIT_HUB_URL = 'https://github.com/pagantis/woocommerce';
    const PAGANTIS_DOC_URL = 'https://developer.pagantis.com';
    const SUPPORT_EML = 'mailto:integrations@pagantis.com?Subject=woocommerce_plugin';


    public $defaultConfigs = array(
        'PAGANTIS_TITLE'=>'Instant financing',
        'PAGANTIS_SIMULATOR_DISPLAY_TYPE'=>'sdk.simulator.types.PRODUCT_PAGE',
        'PAGANTIS_SIMULATOR_DISPLAY_TYPE_CHECKOUT'=>'sdk.simulator.types.CHECKOUT_PAGE',
        'PAGANTIS_SIMULATOR_DISPLAY_SKIN'=>'sdk.simulator.skins.BLUE',
        'PAGANTIS_SIMULATOR_DISPLAY_POSITION'=>'hookDisplayProductButtons',
        'PAGANTIS_SIMULATOR_START_INSTALLMENTS'=>3,
        'PAGANTIS_SIMULATOR_MAX_INSTALLMENTS'=>12,
        'PAGANTIS_SIMULATOR_CSS_POSITION_SELECTOR'=>'default',
        'PAGANTIS_SIMULATOR_DISPLAY_CSS_POSITION'=>'sdk.simulator.positions.INNER',
        'PAGANTIS_SIMULATOR_CSS_PRICE_SELECTOR'=>'a:4:{i:0;s:52:"div.summary *:not(del)>.woocommerce-Price-amount bdi";i:1;s:48:"div.summary *:not(del)>.woocommerce-Price-amount";i:2;s:54:"div.entry-summary *:not(del)>.woocommerce-Price-amount";i:3;s:36:"*:not(del)>.woocommerce-Price-amount";}',
        'PAGANTIS_SIMULATOR_CSS_QUANTITY_SELECTOR'=>'a:2:{i:0;s:22:"div.quantity input.qty";i:1;s:18:"div.quantity>input";}',
        'PAGANTIS_FORM_DISPLAY_TYPE'=>0,
        'PAGANTIS_DISPLAY_MIN_AMOUNT'=>1,
        'PAGANTIS_DISPLAY_MAX_AMOUNT'=>1500,
        'PAGANTIS_URL_OK'=>'',
        'PAGANTIS_URL_KO'=>'',
        'PAGANTIS_ALLOWED_COUNTRIES' => 'a:3:{i:0;s:2:"es";i:1;s:2:"it";i:2;s:2:"fr";}',
        'PAGANTIS_PROMOTION_EXTRA' => '<p>Finance this product <span class="pg-no-interest">without interest!</span></p>',
        'PAGANTIS_SIMULATOR_THOUSANDS_SEPARATOR' => '.',
        'PAGANTIS_SIMULATOR_DECIMAL_SEPARATOR' => ',',
        'PAGANTIS_SIMULATOR_DISPLAY_SITUATION' => 'default',
        'PAGANTIS_SIMULATOR_SELECTOR_VARIATION' => 'default',
        //4x
        'PAGANTIS_DISPLAY_MIN_AMOUNT_4x'=>0,
        'PAGANTIS_DISPLAY_MAX_AMOUNT_4x'=>800,
        'PAGANTIS_TITLE_4x'=>'Hasta 4 pagos, sin coste',

    );

    /** @var array $extraConfig */
    public $extraConfig;

    /**
     * WC_Pagantis constructor.
     */
    public function __construct()
    {
        require_once(plugin_dir_path(__FILE__).'/vendor/autoload.php');
        require_once(PG_ABSPATH . '/includes/pg-functions.php');
        $this->template_path = plugin_dir_path(__FILE__).'/templates/';

        $this->pagantisActivation();

        $this->extraConfig = getExtraConfig();

        load_plugin_textdomain('pagantis', false, dirname(plugin_basename(__FILE__)).'/languages');

        add_filter('woocommerce_payment_gateways', array($this, 'addPagantisGateway'));
        add_filter('woocommerce_available_payment_gateways', array($this, 'pagantisFilterGateways'), 9999);
        add_filter('plugin_row_meta', array($this, 'pagantisRowMeta'), 10, 2);
        add_filter('plugin_action_links_'.plugin_basename(__FILE__), array($this, 'pagantisActionLinks'));
        add_action('init', array($this, 'checkWcPriceSettings'), 10);
        add_action('woocommerce_after_template_part', array($this, 'pagantisAddSimulatorHtmlDiv'), 10);
        add_action('woocommerce_single_product_summary', array($this, 'pagantisInitProductSimulator'), 20);
        add_action('woocommerce_single_variation', array($this,'pagantisAddProductSnippetForVariations'), 30);
        add_action('wp_enqueue_scripts', 'add_pagantis_widget_js');
        add_action('rest_api_init', array($this, 'pagantisRegisterEndpoint')); //Endpoint
        add_filter('load_textdomain_mofile', array($this, 'loadPagantisTranslation'), 10, 2);
        register_activation_hook(__FILE__, array($this, 'pagantisActivation'));
        add_action('woocommerce_product_options_general_product_data', array($this, 'pagantisPromotedProductTpl'));
        add_action('woocommerce_process_product_meta', array($this, 'pagantisPromotedVarSave'));
        add_action('woocommerce_product_bulk_edit_start', array($this,'pagantisPromotedBulkTemplate'));
        add_action('woocommerce_product_bulk_edit_save', array($this,'pagantisPromotedBulkTemplateSave'));
    }

    /**
     * Piece of html code to insert into BULK admin edit
     */
    public function pagantisPromotedBulkTemplate()
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
     * @param $product
     */
    public function pagantisPromotedBulkTemplateSave($product)
    {
        $post_id = $product->get_id();
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
        woocommerce_wp_checkbox(
            array(
                'id' => 'pagantis_promoted',
                'label' => __('Pagantis promoted', 'woocommerce'),
                'value' => $_product['custom_product_pagantis_promoted']['0'],
                'cbvalue' => 'yes',
                'echo' => true
            )
        );
    }

    /**
     *  Php code to save our meta after a PRODUCT admin edit
     * @param $post_id
     */
    public function pagantisPromotedVarSave($post_id)
    {
        $pagantis_promoted_value = $_POST['pagantis_promoted'];
        if ($pagantis_promoted_value !== 'yes') {
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
    public function pagantisActivation()
    {
        global $wpdb;

        $tableName = $wpdb->prefix.PG_CONCURRENCY_TABLE_NAME;
        if ($wpdb->get_var("SHOW TABLES LIKE '$tableName'") != $tableName) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $tableName ( order_id int NOT NULL,  
                    createdAt timestamp DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY id (order_id)) $charset_collate";
            require_once(ABSPATH.'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        $tableName = $wpdb->prefix.PG_CONFIG_TABLE_NAME;

        //Check if table exists
        $tableExists = $wpdb->get_var("SHOW TABLES LIKE '$tableName'") != $tableName;
        if ($tableExists) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE IF NOT EXISTS $tableName (
                                id int NOT NULL AUTO_INCREMENT, 
                                config varchar(60) NOT NULL, 
                                value varchar(1000) NOT NULL, 
                                UNIQUE KEY id(id)) $charset_collate";

            require_once(ABSPATH.'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        } else {
            //Updated value field to adapt to new length < v8.0.1
            $query = "select COLUMN_TYPE FROM information_schema.COLUMNS where TABLE_NAME='$tableName' AND COLUMN_NAME='value'";
            $results = $wpdb->get_results($query, ARRAY_A);
            if ($results['0']['COLUMN_TYPE'] == 'varchar(100)') {
                $sql = "ALTER TABLE $tableName MODIFY value varchar(1000)";
                $wpdb->query($sql);
            }

            //Adapting selector to array < v8.1.1
            $query = "select * from $tableName where config='PAGANTIS_SIMULATOR_CSS_QUANTITY_SELECTOR' 
                               or config='PAGANTIS_SIMULATOR_CSS_PRICE_SELECTOR'";
            $dbCurrentConfig = $wpdb->get_results($query, ARRAY_A);
            foreach ($dbCurrentConfig as $item) {
                if ($item['config'] == 'PAGANTIS_SIMULATOR_CSS_PRICE_SELECTOR') {
                    $css_price_selector = $this->preparePriceSelector($item['value']);
                    if ($item['value'] != $css_price_selector) {
                        $wpdb->update(
                            $tableName,
                            array('value' => stripslashes($css_price_selector)),
                            array('config' => 'PAGANTIS_SIMULATOR_CSS_PRICE_SELECTOR'),
                            array('%s'),
                            array('%s')
                        );
                    }
                } elseif ($item['config'] == 'PAGANTIS_SIMULATOR_CSS_QUANTITY_SELECTOR') {
                    $css_quantity_selector = $this->prepareQuantitySelector($item['value']);
                    if ($item['value'] != $css_quantity_selector) {
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

        // Making sure DB tables are created < v8.6.9
        if (!isPgTableCreated(PG_LOGS_TABLE_NAME)){
            createLogsTable();
        }
        if (isPgTableCreated(PG_CART_PROCESS_TABLE)){
            alterCartProcessTable();
        }

        if (!isPgTableCreated(PG_CART_PROCESS_TABLE)) {
            checkCartProcessTable();
        }

        //Adapting selector to array < v8.2.2
        $tableName = $wpdb->prefix.PG_CONFIG_TABLE_NAME;
        $query = "select * from $tableName where config='PAGANTIS_SIMULATOR_THOUSANDS_SEPARATOR'";
        $results = $wpdb->get_results($query, ARRAY_A);
        if (count($results) == 0) {
            $wpdb->insert($tableName, array('config' => 'PAGANTIS_SIMULATOR_THOUSANDS_SEPARATOR', 'value'  => '.'), array('%s', '%s'));
            $wpdb->insert($tableName, array('config' => 'PAGANTIS_SIMULATOR_DECIMAL_SEPARATOR', 'value'  => ','), array('%s', '%s'));
        }

        //Adding new selector < v8.3.0
        $tableName = $wpdb->prefix.PG_CONFIG_TABLE_NAME;
        $query = "select * from $tableName where config='PAGANTIS_DISPLAY_MAX_AMOUNT'";
        $results = $wpdb->get_results($query, ARRAY_A);
        if (count($results) == 0) {
            $wpdb->insert($tableName, array('config' => 'PAGANTIS_DISPLAY_MAX_AMOUNT', 'value'  => '0'), array('%s', '%s'));
        }

        //Adding new selector < v8.3.2
        $tableName = $wpdb->prefix.PG_CONFIG_TABLE_NAME;
        $query = "select * from $tableName where config='PAGANTIS_SIMULATOR_DISPLAY_SITUATION'";
        $results = $wpdb->get_results($query, ARRAY_A);
        if (count($results) == 0) {
            $wpdb->insert($tableName, array('config' => 'PAGANTIS_SIMULATOR_DISPLAY_SITUATION', 'value'  => 'default'), array('%s', '%s'));
            $wpdb->insert($tableName, array('config' => 'PAGANTIS_SIMULATOR_SELECTOR_VARIATION', 'value'  => 'default'), array('%s', '%s'));
        }


        //Adding new selector < v8.3.3
        $tableName = $wpdb->prefix.PG_CONFIG_TABLE_NAME;
        $query = "select * from $tableName where config='PAGANTIS_SIMULATOR_DISPLAY_TYPE_CHECKOUT'";
        $results = $wpdb->get_results($query, ARRAY_A);
        if (count($results) == 0) {
            $wpdb->insert($tableName, array('config' => 'PAGANTIS_SIMULATOR_DISPLAY_TYPE_CHECKOUT', 'value'  => 'sdk.simulator.types.CHECKOUT_PAGE'), array('%s', '%s'));
            $wpdb->update($tableName, array('value' => 'sdk.simulator.types.PRODUCT_PAGE'), array('config' => 'PAGANTIS_SIMULATOR_DISPLAY_TYPE'), array('%s'), array('%s'));
        }

        //Adapting to variable selector < v8.3.6
        $variableSelector="div.summary div.woocommerce-variation.single_variation > div.woocommerce-variation-price span.price";
        $tableName = $wpdb->prefix.PG_CONFIG_TABLE_NAME;
        $query = "select * from $tableName where config='PAGANTIS_SIMULATOR_SELECTOR_VARIATION' and value='default'";
        $results = $wpdb->get_results($query, ARRAY_A);
        if (count($results) == 0) {
            $wpdb->update($tableName, array('value' => $variableSelector), array('config' => 'PAGANTIS_SIMULATOR_SELECTOR_VARIATION'), array('%s'), array('%s'));
        }

        //Adapting vars to 4x < v8.6.x
        $tableName = $wpdb->prefix.PG_CONFIG_TABLE_NAME;
        $query = "select * from $tableName where config='PAGANTIS_TITLE_4x'";
        $results = $wpdb->get_results($query, ARRAY_A);
        if (count($results) == 0) {
            $wpdb->insert($tableName, array('config' => 'PAGANTIS_TITLE_4x', 'value'  => 'Until 4 installments, without fees'), array('%s', '%s'));
            $wpdb->insert($tableName, array('config' => 'PAGANTIS_DISPLAY_MIN_AMOUNT_4x', 'value'  => 1), array('%s', '%s'));
            $wpdb->insert($tableName, array('config' => 'PAGANTIS_DISPLAY_MAX_AMOUNT_4x', 'value'  => 800), array('%s', '%s'));

            $wpdb->update($tableName, array('value' => 'Instant financing'), array('config' => 'PAGANTIS_TITLE'), array('%s'), array('%s'));
            $wpdb->update($tableName, array('value' => 1500), array('config' => 'PAGANTIS_DISPLAY_MAX_AMOUNT'), array('%s'), array('%s'));
        }

        //Adapting situation var of 4x < v8.6.2
        $tableName = $wpdb->prefix.PG_CONFIG_TABLE_NAME;
        $query = "select * from $tableName where config='PAGANTIS_SIMULATOR_CSS_POSITION_SELECTOR_4X'";
        $results = $wpdb->get_results($query, ARRAY_A);
        if (count($results) == 0) {
            $wpdb->insert($tableName, array('config' => 'PAGANTIS_SIMULATOR_CSS_POSITION_SELECTOR_4X', 'value'  => 'default'), array('%s', '%s'));
        }

        //Adding WC price separator verifications to adapt extra config dynamically < v8.3.9
        if (!areDecimalSeparatorEqual()) {
            updateDecimalSeparatorDbConfig();
        }
        if (!areThousandsSeparatorEqual()) {
            updateThousandsSeparatorDbConfig();
        }

        //Adapting product price selector < v8.6.7
        $tableName = $wpdb->prefix.PG_CONFIG_TABLE_NAME;
        $query = "select * from $tableName where config='PAGANTIS_SIMULATOR_CSS_PRICE_SELECTOR'";
        $results = $wpdb->get_results($query, ARRAY_A);
        if (count($results) == 0) {
            $wpdb->update($tableName, array('value' => 'a:4:{i:0;s:52:"div.summary *:not(del)>.woocommerce-Price-amount bdi";i:1;s:48:"div.summary *:not(del)>.woocommerce-Price-amount";i:2;s:54:"div.entry-summary *:not(del)>.woocommerce-Price-amount";i:3;s:36:"*:not(del)>.woocommerce-Price-amount";}'), array('config' => 'PAGANTIS_SIMULATOR_CSS_PRICE_SELECTOR'), array('%s'), array('%s'));
        }

        $dbConfigs = $wpdb->get_results("select * from $tableName", ARRAY_A);

        // Convert a multiple dimension array for SQL insert statements into a simple key/value
        $simpleDbConfigs = array();
        foreach ($dbConfigs as $config) {
            $simpleDbConfigs[$config['config']] = $config['value'];
        }
        $newConfigs = array_diff_key($this->defaultConfigs, $simpleDbConfigs);
        if (!empty($newConfigs)) {
            foreach ($newConfigs as $key => $value) {
                $wpdb->insert($tableName, array('config' => $key, 'value'  => $value), array('%s', '%s'));
            }
        }

        //Current plugin config: pagantis_public_key => New field --- public_key => Old field
        $settings = get_option('woocommerce_pagantis_settings');

        if (!isset($settings['pagantis_public_key']) && $settings['public_key']) {
            $settings['pagantis_public_key'] = $settings['public_key'];
            unset($settings['public_key']);
        }

        if (!isset($settings['pagantis_private_key']) && $settings['secret_key']) {
            $settings['pagantis_private_key'] = $settings['secret_key'];
            unset($settings['secret_key']);
        }

        update_option('woocommerce_pagantis_settings', $settings);
    }

    /**
     * Checks the WC settings to know if we should modify our config
     */
    public function checkWcPriceSettings()
    {
        if (class_exists( 'WooCommerce' ) ){
            $this->checkWcDecimalSeparatorSettings();
            $this->checkWcThousandsSeparatorSettings();
        }
    }

    /**
     * Check woocommerce_price_thousand_sep and update our config if necessary
     */
    private function checkWcThousandsSeparatorSettings()
    {
        if (areThousandsSeparatorEqual()) {
            return;
        }
        if (!areThousandsSeparatorEqual()) {
            updateThousandsSeparatorDbConfig();
        }
    }

    /**
     * Check woocommerce_price_decimal_sep and update our config if necessary
     */
    private function checkWcDecimalSeparatorSettings()
    {
        if (areDecimalSeparatorEqual()) {
            return;
        }

        if (!areDecimalSeparatorEqual()) {
            updateDecimalSeparatorDbConfig();
        }
    }

    /**
     *  Pushes the simulator div depending on the config and plugin settings
     *
     * @param $template_name
     *
     * @return bool|mixed|void
     * @hooked woocommerce_after_template_part - 10
     * @see wc_get_template
     */
    public function pagantisAddSimulatorHtmlDiv($template_name)
    {
        $areSimulatorTypesValid = isSimulatorTypeValid(
            getConfigValue('PAGANTIS_SIMULATOR_DISPLAY_TYPE'),
            array('sdk.simulator.types.SELECTABLE_TEXT_CUSTOM',
                'sdk.simulator.types.PRODUCT_PAGE')
        );
        $isPriceTplPresent = isTemplatePresent($template_name, array('single-product/price.php'));
        $isAtcTplPresent = isTemplatePresent(
            $template_name,
            array('single-product/add-to-cart/variation-add-to-cart-button.php',
                'single-product/add-to-cart/variation.php','single-product/add-to-cart/simple.php')
        );

        $html = apply_filters('pagantis_simulator_selector_html', '<div class="mainPagantisSimulator"></div><div class="pagantisSimulator"></div>');


        $pagantisSimulator = 'enabled';
        if (!isPluginEnabled() || !areMerchantKeysSet() || !isSimulatorEnabled() || !isCountryShopContextValid() || !isProductAmountValid()) {
            $pagantisSimulator = 'disabled';
        }

        $pagantisSimulator4x = 'enabled';
        if (!isPluginEnabled4x() || !areMerchantKeysSet4x()  || !isCountryShopContextValid() || !isProductAmountValid4x()) {
            $pagantisSimulator4x = 'disabled';
        }
        if ($pagantisSimulator === 'disabled' && $pagantisSimulator4x === 'disabled') {
            return;
        }

        if (($areSimulatorTypesValid && $isPriceTplPresent) || (!$areSimulatorTypesValid && $isAtcTplPresent)) {
            self::enqueueSimulatorCss();
            echo $html;
        }
    }


    /**
     * Init code required to update price for products with variations
     *
     */
    public function pagantisAddProductSnippetForVariations()
    {
        global $product;
        if (!isPluginEnabled() || !areMerchantKeysSet() || !isSimulatorEnabled() || !isCountryShopContextValid() || !isProductAmountValid()) {
            return;
        }

        wp_register_script('pg-product-variation-simulator', plugins_url('assets/js/pg-product-variation-simulator.js', PG_WC_MAIN_FILE), array('pg-product-simulator'), '', true);

        $variationSimulatorData = array(
            'variationSelector' =>  $this->extraConfig['PAGANTIS_SIMULATOR_SELECTOR_VARIATION'],
            'productType' => $product->get_type()
        );

        wp_localize_script('pg-product-variation-simulator', 'variationSimulatorData', $variationSimulatorData);
        wp_enqueue_script('pg-product-variation-simulator');
    }

    /**
     * @return string|void
     *
     */
    public function pagantisInitProductSimulator()
    {
        global $product;

        //12x
        $pagantisSimulator = 'enabled';

        wp_register_script('pg-product-simulator', plugins_url('assets/js/pg-product-simulator.js', PG_WC_MAIN_FILE), array(), '', true);
        $settings = get_option('woocommerce_pagantis_settings');
        $locale = strtolower(strstr(get_locale(), '_', true));
        if (!isPluginEnabled() || !areMerchantKeysSet() || !isSimulatorEnabled() || !isCountryShopContextValid() || !isProductAmountValid()) {
            $pagantisSimulator = 'disabled';
        }

        $pagantisSimulator4x = 'enabled';
        if (!isPluginEnabled4x() || !areMerchantKeysSet4x() || !isCountryShopContextValid() || !isProductAmountValid4x()) {
            $pagantisSimulator4x = 'disabled';
        }

        if ($pagantisSimulator === 'disabled' && $pagantisSimulator4x === 'disabled') {
            return;
        }

        $totalPrice = $product->get_price();
        $formattedInstallments = number_format($totalPrice/4, 2);
        $simulatorMessage = sprintf(__('or 4 installments of %s€, without fees, with ', 'pagantis'), $formattedInstallments);
        $post_id = $product->get_id();
        $logo = 'https://cdn.digitalorigin.com/assets/master/logos/pg-130x30.svg';
        $simulatorData = array(
            'total'    => is_numeric($product->get_price()) ? $product->get_price() : 0,
            'public_key' => $settings['pagantis_public_key'],
            'simulator_type' => $this->extraConfig['PAGANTIS_SIMULATOR_DISPLAY_TYPE'],
            'positionSelector' => $this->extraConfig['PAGANTIS_SIMULATOR_CSS_POSITION_SELECTOR'],
            'positionSelector4x' => $this->extraConfig['PAGANTIS_SIMULATOR_CSS_POSITION_SELECTOR_4X'],
            'quantitySelector' => unserialize($this->extraConfig['PAGANTIS_SIMULATOR_CSS_QUANTITY_SELECTOR']),
            'priceSelector' => unserialize($this->extraConfig['PAGANTIS_SIMULATOR_CSS_PRICE_SELECTOR']),
            'totalAmount' => is_numeric($product->get_price()) ? $product->get_price() : 0,
            'locale' => $locale,
            'country' => $locale,
            'promoted' => $this->isPromoted($post_id),
            'promotedMessage' => $this->extraConfig['PAGANTIS_PROMOTION_EXTRA'],
            'thousandSeparator' => $this->extraConfig['PAGANTIS_SIMULATOR_THOUSANDS_SEPARATOR'],
            'decimalSeparator' => $this->extraConfig['PAGANTIS_SIMULATOR_DECIMAL_SEPARATOR'],
            'pagantisQuotesStart' => $this->extraConfig['PAGANTIS_SIMULATOR_START_INSTALLMENTS'],
            'pagantisSimulatorSkin' => $this->extraConfig['PAGANTIS_SIMULATOR_DISPLAY_SKIN'],
            'pagantisSimulatorPosition' => $this->extraConfig['PAGANTIS_SIMULATOR_DISPLAY_CSS_POSITION'],
            'finalDestination' => $this->extraConfig['PAGANTIS_SIMULATOR_DISPLAY_SITUATION'],
            'variationSelector' => $this->extraConfig['PAGANTIS_SIMULATOR_SELECTOR_VARIATION'],
            'productType' => $product->get_type(),
            'pagantisSimulator' => $pagantisSimulator,
            'pagantisSimulator4x' => $pagantisSimulator4x,
            'simulatorMessage' => "$simulatorMessage<img class='mainImageLogo' src='$logo'/>"
        );

        wp_localize_script('pg-product-simulator', 'simulatorData', $simulatorData);
        wp_enqueue_script('pg-product-simulator');
    }

    /**
     * Add Pagantis to payments list.
     *
     * @param $methods
     *
     * @return array
     */
    public function addPagantisGateway($methods)
    {
        if (! class_exists('WC_Payment_Gateway')) {
            return $methods;
        }

        //4x
        include_once('controllers/paymentController4x.php');
        $methods[] = 'WcPagantis4xGateway';

        //12x
        include_once('controllers/paymentController.php');
        $methods[] = 'WcPagantisGateway';

        return $methods;
    }

    /**
     * Initialize WC_Pagantis class
     *
     * @param $methods
     *
     * @return mixed
     */
    public function pagantisFilterGateways($methods)
    {
        $pagantis4x = new WcPagantis4xGateway();
        if (!$pagantis4x->is_available()) {
            unset($methods['pagantis4x']);
        }

        $pagantis = new WcPagantisGateway();
        if (!$pagantis->is_available()) {
            unset($methods['pagantis']);
        }

        return $methods;
    }

    /**
     * Add links to Plugin description
     *
     * @param $links
     *
     * @return mixed
     */
    public function pagantisActionLinks($links)
    {
        $params_array = array('page' => 'wc-settings', 'tab' => 'checkout', 'section' => 'pagantis');
        $setting_url  = esc_url(add_query_arg($params_array, admin_url('admin.php?')));
        $setting_link = '<a href="'.$setting_url.'">'.__('Settings', 'pagantis').'</a>';

        array_unshift($links, $setting_link);

        return $links;
    }

    /**
     * Add links to Plugin options
     *
     * @param $links
     * @param $file
     *
     * @return array
     */
    public function pagantisRowMeta($links, $file)
    {
        if ($file == plugin_basename(__FILE__)) {
            $links[] = '<a href="'.WcPagantis::GIT_HUB_URL.'" target="_blank">'.__('Documentation', 'pagantis').'</a>';
            $links[] = '<a href="'.WcPagantis::PAGANTIS_DOC_URL.'" target="_blank">'.
                       __('API documentation', 'pagantis').'</a>';
            $links[] = '<a href="'.WcPagantis::SUPPORT_EML.'">'.__('Support', 'pagantis').'</a>';

            return $links;
        }

        return $links;
    }

    /**
     * Read logs
     */
    public function readLogs($data)
    {
        global $wpdb;
        $filters   = ($data->get_params());
        $response  = array();
        $secretKey = $filters['secret'];
        $from = $filters['from'];
        $to   = $filters['to'];
        $cfg  = get_option('woocommerce_pagantis_settings');
        $privateKey = isset($cfg['pagantis_private_key']) ? $cfg['pagantis_private_key'] : null;
        $privateKey4x = isset($cfg['pagantis_private_key_4x']) ? $cfg['pagantis_private_key_4x'] : null;
        $tableName = $wpdb->prefix.PG_LOGS_TABLE_NAME;
        $query = "select * from $tableName where createdAt>$from and createdAt<$to order by createdAt desc";
        $results = $wpdb->get_results($query);
        if (isset($results) && ($privateKey == $secretKey || $privateKey4x == $secretKey)) {
            foreach ($results as $key => $result) {
                $response[$key]['timestamp'] = $result->createdAt;
                $response[$key]['log']       = json_decode($result->log);
            }
        } else {
            $response['result'] = 'Error';
        }
        $response = json_encode($response);
        header("HTTP/1.1 200", true, 200);
        header('Content-Type: application/json', true);
        header('Content-Length: '.strlen($response));
        echo($response);
        exit();
    }

    /**
     * Update extra config
     */
    public function updateExtraConfig($data)
    {
        global $wpdb;
        $tableName = $wpdb->prefix.PG_CONFIG_TABLE_NAME;
        $response = array('status'=>null);

        $filters   = ($data->get_params());
        $secretKey = $filters['secret'];
        $cfg  = get_option('woocommerce_pagantis_settings');
        $privateKey   = isset($cfg['pagantis_private_key']) ? $cfg['pagantis_private_key'] : null;
        $privateKey4x = isset($cfg['pagantis_private_key_4x']) ? $cfg['pagantis_private_key_4x'] : null;
        if ($privateKey != $secretKey && $privateKey4x != $secretKey) {
            $response['status'] = 401;
            $response['result'] = 'Unauthorized';
        } elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if (count($_POST)) {
                foreach ($_POST as $config => $value) {
                    if (isset($this->defaultConfigs[$config]) && $response['status']==null) {
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

        if ($response['status']==null) {
            $tableName = $wpdb->prefix.PG_CONFIG_TABLE_NAME;
            $dbResult = $wpdb->get_results("select config, value from $tableName", ARRAY_A);
            foreach ($dbResult as $value) {
                $formattedResult[$value['config']] = $value['value'];
            }
            $response['result'] = $formattedResult;
        }

        $result = json_encode($response['result']);
        header("HTTP/1.1 ".$response['status'], true, $response['status']);
        header('Content-Type: application/json', true);
        header('Content-Length: '.strlen($result));
        echo($result);
        exit();
    }

    /**
     * Read logs
     */
    public function readApi($data)
    {
        global $wpdb;
        $filters   = ($data->get_params());
        $response  = array('timestamp'=>time());
        $secretKey = $filters['secret'];
        $from = ($filters['from']) ? date_create($filters['from']) : date("Y-m-d", strtotime("-7 day"));
        $to = ($filters['to']) ? date_create($filters['to']) : date("Y-m-d", strtotime("+1 day"));
        $method = ($filters['method']) ? ($filters['method']) : 'Pagantis';
        $cfg  = get_option('woocommerce_pagantis_settings');
        $privateKey = isset($cfg['pagantis_private_key']) ? $cfg['pagantis_private_key'] : null;
        $privateKey4x = isset($cfg['pagantis_private_key_4x']) ? $cfg['pagantis_private_key_4x'] : null;
        $tableName = $wpdb->prefix.PG_ORDERS_TABLE;
        $tableNameInner = $wpdb->prefix.'postmeta';
        $query = "select * from $tableName tn INNER JOIN $tableNameInner tn2 ON tn2.post_id = tn.id
                  where tn.post_type='shop_order' and tn.post_date>'".$from->format("Y-m-d")."' 
                  and tn.post_date<'".$to->format("Y-m-d")."' order by tn.post_date desc";
        $results = $wpdb->get_results($query);

        if (isset($results) && ($privateKey == $secretKey || $privateKey4x == $secretKey)) {
            foreach ($results as $result) {
                $key = $result->ID;
                $response['message'][$key]['timestamp'] = $result->post_date;
                $response['message'][$key]['order_id'] = $key;
                $response['message'][$key][$result->meta_key] = $result->meta_value;
            }
        } else {
            $response['result'] = 'Error';
        }
        $response = json_encode($response);
        header("HTTP/1.1 200", true, 200);
        header('Content-Type: application/json', true);
        header('Content-Length: '.strlen($response));
        echo($response);
        exit();
    }

    /**
     * ENDPOINT - Read logs -> Hook: rest_api_init
     * @return mixed
     */
    public function pagantisRegisterEndpoint()
    {
        register_rest_route(
            'pagantis/v1',
            '/logs/(?P<secret>\w+)/(?P<from>\d+)/(?P<to>\d+)',
            array(
                'methods'  => 'GET',
                'callback' => array(
                    $this,
                    'readLogs'),
                'permission_callback' => '__return_true',
            ),
            true
        );

        register_rest_route(
            'pagantis/v1',
            '/configController/(?P<secret>\w+)',
            array(
                'methods'  => 'GET, POST',
                'callback' => array(
                    $this,
                    'updateExtraConfig'),
                'permission_callback' => '__return_true',
            ),
            true
        );

        register_rest_route(
            'pagantis/v1',
            '/api/(?P<secret>\w+)/(?P<from>\w+)/(?P<to>\w+)',
            array(
                'methods'  => 'GET',
                'callback' => array(
                    $this,
                    'readApi'),
                'permission_callback' => '__return_true',
            ),
            true
        );
    }



    /**
     * @param $css_quantity_selector
     *
     * @return mixed|string
     */
    private function prepareQuantitySelector($css_quantity_selector)
    {
        if ($css_quantity_selector == 'default' || $css_quantity_selector == '') {
            $css_quantity_selector = $this->defaultConfigs['PAGANTIS_SIMULATOR_CSS_QUANTITY_SELECTOR'];
        } elseif (!unserialize($css_quantity_selector)) { //in the case of a custom string selector, we keep it
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
        if ($css_price_selector == 'default' || $css_price_selector == '') {
            $css_price_selector = $this->defaultConfigs['PAGANTIS_SIMULATOR_CSS_PRICE_SELECTOR'];
        } elseif (!unserialize($css_price_selector)) { //in the case of a custom string selector, we keep it
            $css_price_selector = serialize(array($css_price_selector));
        }

        return $css_price_selector;
    }

    /**
     * @param $product_id
     *
     * @return string
     */
    private function isPromoted($product_id)
    {
        $metaProduct = get_post_meta($product_id);
        return (array_key_exists('custom_product_pagantis_promoted', $metaProduct) &&
                $metaProduct['custom_product_pagantis_promoted']['0'] === 'yes') ? 'true' : 'false';
    }

    /**
     * @see wp_enqueue_style
     */
    private static function enqueueSimulatorCss()
    {
        wp_enqueue_style('pg_simulator_style', plugins_url('assets/css/pg-simulator-style.css', PG_WC_MAIN_FILE));
        wp_enqueue_style('pg_sim_gfonts', 'https://fonts.googleapis.com/css?family=Open+Sans:400&display=swap');
    }
}

/**
 * Add widget Js
 **/
function add_pagantis_widget_js()
{
    wp_enqueue_script('pgSDK', 'https://cdn.pagantis.com/js/pg-v2/sdk.js', '', '', true);
}

$WcPagantis = new WcPagantis();
