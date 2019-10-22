<?php
/**
 * Plugin Name: Pagantis
 * Plugin URI: http://www.pagantis.com/
 * Description: Financiar con Pagantis
 * Version: 8.2.6
 * Author: Pagantis
 */

//namespace Gateways;


if (!defined('ABSPATH')) {
    exit;
}

class WcPagantis
{
    const GIT_HUB_URL = 'https://github.com/pagantis/woocommerce';
    const PAGANTIS_DOC_URL = 'https://developer.pagantis.com';
    const SUPPORT_EML = 'mailto:integrations@pagantis.com?Subject=woocommerce_plugin';

    /** Concurrency tablename */
    const LOGS_TABLE = 'pagantis_logs';

    /** Config tablename */
    const CONFIG_TABLE = 'pagantis_config';

    /** Concurrency tablename  */
    const CONCURRENCY_TABLE = 'pagantis_concurrency';

    /** Config tablename */
    const ORDERS_TABLE = 'posts';

    public $defaultConfigs = array(
       'PAGANTIS_TITLE'=>'Instant Financing',
       'PAGANTIS_SIMULATOR_DISPLAY_TYPE'=>'sdk.simulator.types.SIMPLE',
       'PAGANTIS_SIMULATOR_DISPLAY_SKIN'=>'sdk.simulator.skins.BLUE',
       'PAGANTIS_SIMULATOR_DISPLAY_POSITION'=>'hookDisplayProductButtons',
       'PAGANTIS_SIMULATOR_START_INSTALLMENTS'=>3,
       'PAGANTIS_SIMULATOR_MAX_INSTALLMENTS'=>12,
       'PAGANTIS_SIMULATOR_CSS_POSITION_SELECTOR'=>'default',
       'PAGANTIS_SIMULATOR_DISPLAY_CSS_POSITION'=>'sdk.simulator.positions.INNER',
       'PAGANTIS_SIMULATOR_CSS_PRICE_SELECTOR'=>'a:3:{i:0;s:48:"div.summary *:not(del)>.woocommerce-Price-amount";i:1;s:54:"div.entry-summary *:not(del)>.woocommerce-Price-amount";i:2;s:36:"*:not(del)>.woocommerce-Price-amount";}',
       'PAGANTIS_SIMULATOR_CSS_QUANTITY_SELECTOR'=>'a:2:{i:0;s:22:"div.quantity input.qty";i:1;s:18:"div.quantity>input";}',
       'PAGANTIS_FORM_DISPLAY_TYPE'=>0,
       'PAGANTIS_DISPLAY_MIN_AMOUNT'=>1,
       'PAGANTIS_URL_OK'=>'',
       'PAGANTIS_URL_KO'=>'',
       'PAGANTIS_ALLOWED_COUNTRIES' => 'a:3:{i:0;s:2:"es";i:1;s:2:"it";i:2;s:2:"fr";}',
       'PAGANTIS_PROMOTION_EXTRA' => '<p>Finance this product <span class="pmt-no-interest">without interest!</span></p>',
       'PAGANTIS_SIMULATOR_THOUSANDS_SEPARATOR' => '.',
       'PAGANTIS_SIMULATOR_DECIMAL_SEPARATOR' => ','
    );

    /** @var Array $extraConfig */
    public $extraConfig;

    /**
     * WC_Pagantis constructor.
     */
    public function __construct()
    {
        require_once(plugin_dir_path(__FILE__).'/vendor/autoload.php');

        $this->template_path = plugin_dir_path(__FILE__).'/templates/';

        $this->extraConfig = $this->getExtraConfig();

        load_plugin_textdomain('pagantis', false, basename(dirname(__FILE__)).'/languages');
        add_filter('woocommerce_payment_gateways', array($this, 'addPagantisGateway'));
        add_filter('woocommerce_available_payment_gateways', array($this, 'pagantisFilterGateways'), 9999);
        add_filter('plugin_row_meta', array($this, 'pagantisRowMeta'), 10, 2);
        add_filter('plugin_action_links_'.plugin_basename(__FILE__), array($this, 'pagantisActionLinks'));
        add_action('woocommerce_after_add_to_cart_form', array($this, 'pagantisAddProductSimulator'));
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
        if ($pagantis_promoted_value == 'on') {
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
        if ($pagantis_promoted_value == null) {
            $pagantis_promoted_value = 'no';
        }
        update_post_meta($post_id, 'custom_product_pagantis_promoted', esc_attr($pagantis_promoted_value));
    }

    /*
     * Replace 'textdomain' with your plugin's textdomain. e.g. 'woocommerce'.
     * File to be named, for example, yourtranslationfile-en_GB.mo
     * File to be placed, for example, wp-content/lanaguages/textdomain/yourtranslationfile-en_GB.mo
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

        $tableName = $wpdb->prefix.self::CONCURRENCY_TABLE;
        if ($wpdb->get_var("SHOW TABLES LIKE '$tableName'") != $tableName) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $tableName ( order_id int NOT NULL,  
                    createdAt timestamp DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY id (order_id)) $charset_collate";
            require_once(ABSPATH.'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        $tableName = $wpdb->prefix.self::CONFIG_TABLE;

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

        //Adapting selector to array < v8.2.2
        $tableName = $wpdb->prefix.self::CONFIG_TABLE;
        $query = "select * from $tableName where config='PAGANTIS_SIMULATOR_THOUSANDS_SEPARATOR'";
        $results = $wpdb->get_results($query, ARRAY_A);
        if (count($results) == 0) {
            $wpdb->insert($tableName, array('config' => 'PAGANTIS_SIMULATOR_THOUSANDS_SEPARATOR', 'value'  => '.'), array('%s', '%s'));
            $wpdb->insert($tableName, array('config' => 'PAGANTIS_SIMULATOR_DECIMAL_SEPARATOR', 'value'  => ','), array('%s', '%s'));
        }

        $dbConfigs = $wpdb->get_results("select * from $tableName", ARRAY_A);

        // Convert a multimple dimension array for SQL insert statements into a simple key/value
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
     * Product simulator
     */
    public function pagantisAddProductSimulator()
    {
        global $product;

        $cfg = get_option('woocommerce_pagantis_settings');
        $locale = strtolower(strstr(get_locale(), '_', true));
        $allowedCountries = unserialize($this->extraConfig['PAGANTIS_ALLOWED_COUNTRIES']);
        $allowedCountry = (in_array(strtolower($locale), $allowedCountries));
        if ($cfg['enabled'] !== 'yes' || $cfg['pagantis_public_key'] == '' || $cfg['pagantis_private_key'] == '' ||
            $cfg['simulator'] !== 'yes' ||  $product->get_price() < $this->extraConfig['PAGANTIS_DISPLAY_MIN_AMOUNT'] ||
            !$allowedCountry ) {
            return;
        }

        $post_id = $product->get_id();
        $template_fields = array(
            'total'    => is_numeric($product->price) ? $product->price : 0,
            'public_key' => $cfg['pagantis_public_key'],
            'simulator_type' => $this->extraConfig['PAGANTIS_SIMULATOR_DISPLAY_TYPE'],
            'positionSelector' => $this->extraConfig['PAGANTIS_SIMULATOR_CSS_POSITION_SELECTOR'],
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
            'pagantisSimulatorPosition' => $this->extraConfig['PAGANTIS_SIMULATOR_DISPLAY_CSS_POSITION']
        );
        wc_get_template('product_simulator.php', $template_fields, '', $this->template_path);
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
        $tableName = $wpdb->prefix.self::LOGS_TABLE;
        $query = "select * from $tableName where createdAt>$from and createdAt<$to order by createdAt desc";
        $results = $wpdb->get_results($query);
        if (isset($results) && $privateKey == $secretKey) {
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
        $tableName = $wpdb->prefix.self::CONFIG_TABLE;
        $response = array('status'=>null);

        $filters   = ($data->get_params());
        $secretKey = $filters['secret'];
        $cfg  = get_option('woocommerce_pagantis_settings');
        $privateKey = isset($cfg['pagantis_private_key']) ? $cfg['pagantis_private_key'] : null;
        if ($privateKey != $secretKey) {
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
            $tableName = $wpdb->prefix.self::CONFIG_TABLE;
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
        $tableName = $wpdb->prefix.self::ORDERS_TABLE;
        $tableNameInner = $wpdb->prefix.'postmeta';
        $query = "select * from $tableName tn INNER JOIN $tableNameInner tn2 ON tn2.post_id = tn.id
                  where tn.post_type='shop_order' and tn.post_date>'".$from->format("Y-m-d")."' 
                  and tn.post_date<'".$to->format("Y-m-d")."' order by tn.post_date desc";
        $results = $wpdb->get_results($query);

        if (isset($results) && $privateKey == $secretKey) {
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
                    'readLogs')
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
                    'updateExtraConfig')
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
                    'readApi')
            ),
            true
        );
    }

    /**
     * @return array
     */
    private function getExtraConfig()
    {
        global $wpdb;
        $tableName = $wpdb->prefix.self::CONFIG_TABLE;
        $response = array();
        $dbResult = $wpdb->get_results("select config, value from $tableName", ARRAY_A);
        foreach ($dbResult as $value) {
            $response[$value['config']] = $value['value'];
        }

        return $response;
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
}

/**
 * Add widget Js
 **/
function add_pagantis_widget_js()
{
    wp_enqueue_script('pgSDK', 'https://cdn.pagantis.com/js/pg-v2/sdk.js', '', '', true);
    wp_enqueue_script('pmtSDK', 'https://cdn.pagamastarde.com/js/pmt-v2/sdk.js', '', '', true);
}

$WcPagantis = new WcPagantis();
