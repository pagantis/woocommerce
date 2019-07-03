<?php
/**
 * Plugin Name: Pagantis
 * Plugin URI: http://www.pagantis.com/
 * Description: Financiar con Pagantis
 * Version: 8.1.0
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

    public $defaultConfigs = array('PAGANTIS_TITLE'=>'Instant Financing',
                            'PAGANTIS_SIMULATOR_DISPLAY_TYPE'=>'pgSDK.simulator.types.SIMPLE',
                            'PAGANTIS_SIMULATOR_DISPLAY_SKIN'=>'pgSDK.simulator.skins.BLUE',
                            'PAGANTIS_SIMULATOR_DISPLAY_POSITION'=>'hookDisplayProductButtons',

                            'PAGANTIS_SIMULATOR_START_INSTALLMENTS'=>3,
                            'PAGANTIS_SIMULATOR_MAX_INSTALLMENTS'=>12,
                            'PAGANTIS_SIMULATOR_CSS_POSITION_SELECTOR'=>'default',
                            'PAGANTIS_SIMULATOR_DISPLAY_CSS_POSITION'=>'pgSDK.simulator.positions.INNER',
                            'PAGANTIS_SIMULATOR_CSS_PRICE_SELECTOR'=>'default',
                            'PAGANTIS_SIMULATOR_CSS_QUANTITY_SELECTOR'=>'default',
                            'PAGANTIS_FORM_DISPLAY_TYPE'=>0,
                            'PAGANTIS_DISPLAY_MIN_AMOUNT'=>1,
                            'PAGANTIS_URL_OK'=>'',
                            'PAGANTIS_URL_KO'=>'',
                            'PAGANTIS_TITLE_EXTRA' => 'Pay up to 12 comfortable installments with Pagantis. Completely online and sympathetic request, and the answer is immediate!',
                            'PAGANTIS_ALLOWED_COUNTRIES' => 'a:2:{i:0;s:2:"es";i:1;s:2:"it";}'
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

        $this->pagantisActivation();

        $this->extraConfig = $this->getExtraConfig();

        load_plugin_textdomain('pagantis', false, basename(dirname(__FILE__)).'/languages');
        add_filter('woocommerce_payment_gateways', array($this, 'addPagantisGateway'));
        add_filter('woocommerce_available_payment_gateways', array($this, 'pagantisFilterGateways'), 9999);
        add_filter('plugin_row_meta', array($this, 'pagantisRowMeta'), 10, 2);
        add_filter('plugin_action_links_'.plugin_basename(__FILE__), array($this, 'pagantisActionLinks'));
        add_action('woocommerce_after_add_to_cart_form', array($this, 'pagantisAddProductSimulator'));
        add_action('wp_enqueue_scripts', 'add_widget_js');
        add_action('rest_api_init', array($this, 'pagantisRegisterEndpoint')); //Endpoint
        add_filter('load_textdomain_mofile', array($this, 'loadPagantisTranslation'), 10, 2);
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
            $cfg['simulator'] !== 'yes' ||  $product->price < $this->extraConfig['PAGANTIS_DISPLAY_MIN_AMOUNT'] ||
            !$allowedCountry ) {
            return;
        }

        $template_fields = array(
            'total'    => is_numeric($product->price) ? $product->price : 0,
            'public_key' => $cfg['pagantis_public_key'],
            'simulator_type' => $this->extraConfig['PAGANTIS_SIMULATOR_DISPLAY_TYPE'],
            'positionSelector' => $this->extraConfig['PAGANTIS_SIMULATOR_CSS_POSITION_SELECTOR'],
            'quantitySelector' => $this->extraConfig['PAGANTIS_SIMULATOR_CSS_QUANTITY_SELECTOR'],
            'priceSelector' => $this->extraConfig['PAGANTIS_SIMULATOR_CSS_PRICE_SELECTOR'],
            'totalAmount' => is_numeric($product->price) ? $product->price : 0,
            'locale' => $locale
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
        if ($pagantis->is_available()) {
            $methods['pagantis'] = $pagantis;
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
        $privateKey = isset($cfg['secret_key']) ? $cfg['secret_key'] : null;
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
                            array('value' => $value),
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
}

/**
 * Add widget Js
 **/
function add_widget_js()
{
    wp_enqueue_script('pgSDK', 'https://cdn.pagantis.com/js/pg-v2/sdk.js', '', '', true);
    wp_enqueue_script('pmtSDK', 'https://cdn.pagamastarde.com/js/pmt-v2/sdk.js', '', '', true);
}

$WcPagantis = new WcPagantis();
