<?php
/**
 * Plugin Name: Pagamastarde
 * Plugin URI: http://www.pagamastarde.com/
 * Description: Financiar con Pagamastarde
 * Version: 7.0.0
 * Author: Pagamastarde
 */

//namespace Gateways;


if (!defined('ABSPATH')) {
    exit;
}

class WcPaylater
{
    const GIT_HUB_URL = 'https://github.com/PagaMasTarde/woocommerce';
    const PMT_DOC_URL = 'https://docs.pagamastarde.com';
    const SUPPORT_EML = 'mailto:soporte@pagamastarde.com?Subject=woocommerce_plugin';
    /** Concurrency tablename */
    const LOGS_TABLE = 'pmt_logs';
    /** Config tablename */
    const CONFIG_TABLE = 'pmt_config';

    public $defaultConfigs = array('PMT_TITLE'=>'Instant Financing',
                            'PMT_SIMULATOR_DISPLAY_TYPE'=>'pmtSDK.simulator.types.SIMPLE',
                            'PMT_SIMULATOR_DISPLAY_SKIN'=>'pmtSDK.simulator.skins.BLUE',
                            'PMT_SIMULATOR_DISPLAY_POSITION'=>'hookDisplayProductButtons',
                            'PMT_SIMULATOR_START_INSTALLMENTS'=>3,
                            'PMT_SIMULATOR_MAX_INSTALLMENTS'=>12,
                            'PMT_SIMULATOR_CSS_POSITION_SELECTOR'=>'default',
                            'PMT_SIMULATOR_DISPLAY_CSS_POSITION'=>'pmtSDK.simulator.positions.INNER',
                            'PMT_SIMULATOR_CSS_PRICE_SELECTOR'=>'default',
                            'PMT_SIMULATOR_CSS_QUANTITY_SELECTOR'=>'default',
                            'PMT_FORM_DISPLAY_TYPE'=>0,
                            'PMT_DISPLAY_MIN_AMOUNT'=>1,
                            'PMT_URL_OK'=>'',
                            'PMT_URL_KO'=>'',
                            'PMT_TITLE_EXTRA' => 'Paga hasta en 12 cómodas cuotas con Paga+Tarde. Solicitud totalmente 
                            online y sin papeleos,¡y la respuesta es inmediata!'
    );

    /**
     * WC_Paylater constructor.
     */
    public function __construct()
    {
        require_once(plugin_dir_path(__FILE__).'/vendor/autoload.php');

        $this->template_path = plugin_dir_path(__FILE__).'/templates/';

        $this->paylaterActivation();

        load_plugin_textdomain('paylater', false, basename(dirname(__FILE__)).'/languages');
        add_filter('woocommerce_payment_gateways', array($this, 'addPaylaterGateway'));
        add_filter('woocommerce_available_payment_gateways', array($this, 'paylaterFilterGateways'), 9999);
        add_filter('plugin_row_meta', array($this, 'paylaterRowMeta'), 10, 2);
        add_filter('plugin_action_links_'.plugin_basename(__FILE__), array($this, 'paylaterActionLinks'));
        add_action('woocommerce_after_add_to_cart_form', array($this, 'paylaterAddProductSimulator'));
        add_action('wp_enqueue_scripts', 'add_widget_js');
        add_action('rest_api_init', array($this, 'paylaterRegisterEndpoint')); //Endpoint
    }

    /**
     * Sql table
     */
    public function paylaterActivation()
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
                                value varchar(100) NOT NULL, 
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

        foreach (array_merge($this->defaultConfigs, $simpleDbConfigs) as $key => $value) {
            putenv($key . '=' . $value);
        }

        //Current plugin config: pmt_public_key => New field --- public_key => Old field
        $settings = get_option('woocommerce_paylater_settings');

        if (!isset($settings['pmt_public_key']) && $settings['public_key']) {
            $settings['pmt_public_key'] = $settings['public_key'];
            unset($settings['public_key']);
        }

        if (!isset($settings['pmt_private_key']) && $settings['secret_key']) {
            $settings['pmt_private_key'] = $settings['secret_key'];
            unset($settings['secret_key']);
        }

        update_option('woocommerce_paylater_settings', $settings);
    }

    /**
     * Product simulator
     */
    public function paylaterAddProductSimulator()
    {
        global $product;

        $cfg = get_option('woocommerce_paylater_settings');
        if ($cfg['enabled'] !== 'yes' || $cfg['pmt_public_key'] == '' || $cfg['pmt_private_key'] == '' ||
            $cfg['simulator'] !== 'yes') {
            return;
        }

        $template_fields = array(
            'total'    => is_numeric($product->price) ? $product->price : 0,
            'public_key' => $cfg['pmt_public_key'],
            'simulator_type' => getenv('PMT_SIMULATOR_DISPLAY_TYPE'),
            'positionSelector' => getenv('PMT_SIMULATOR_CSS_POSITION_SELECTOR'),
            'quantitySelector' => getenv('PMT_SIMULATOR_CSS_QUANTITY_SELECTOR'),
            'priceSelector' => getenv('PMT_SIMULATOR_CSS_PRICE_SELECTOR'),
            'totalAmount' => is_numeric($product->price) ? $product->price : 0
        );
        wc_get_template('product_simulator.php', $template_fields, '', $this->template_path);
    }

    /**
     * Add Paylater to payments list.
     *
     * @param $methods
     *
     * @return array
     */
    public function addPaylaterGateway($methods)
    {
        if (! class_exists('WC_Payment_Gateway')) {
            return $methods;
        }

        include_once('controllers/paymentController.php');
        $methods[] = 'WcPaylaterGateway';

        return $methods;
    }

    /**
     * Initialize WC_Paylater class
     *
     * @param $methods
     *
     * @return mixed
     */
    public function paylaterFilterGateways($methods)
    {
        global $woocommerce;
        $paylater = new WcPaylaterGateway();

        return $methods;
    }

    /**
     * Add links to Plugin description
     *
     * @param $links
     *
     * @return mixed
     */
    public function paylaterActionLinks($links)
    {
        $params_array = array('page' => 'wc-settings', 'tab' => 'checkout', 'section' => 'paylater');
        $setting_url  = esc_url(add_query_arg($params_array, admin_url('admin.php?')));
        $setting_link = '<a href="'.$setting_url.'">'.__('Settings', 'paylater').'</a>';

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
    public function paylaterRowMeta($links, $file)
    {
        if ($file == plugin_basename(__FILE__)) {
            $links[] = '<a href="'.WcPaylater::GIT_HUB_URL.'" target="_blank">'.__('Documentation', 'paylater').'</a>';
            $links[] = '<a href="'.WcPaylater::PMT_DOC_URL.'" target="_blank">'.
            __('API documentation', 'paylater').'</a>';
            $links[] = '<a href="'.WcPaylater::SUPPORT_EML.'">'.__('Support', 'paylater').'</a>';

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
        $cfg  = get_option('woocommerce_paylater_settings');
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
        $response = array('message'=>'Wrong request method');

        $filters   = ($data->get_params());
        $response  = array();
        $secretKey = $filters['secret'];
        $cfg  = get_option('woocommerce_paylater_settings');
        $privateKey = isset($cfg['pmt_private_key']) ? $cfg['pmt_private_key'] : null;
        if ($privateKey != $secretKey) {
            $response = array('message'=>'Wrong input');
        } elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if (count($_POST)) {
                foreach ($_POST as $config => $value) {
                    if (isset($this->defaultConfigs[$config])) {
                        $result = $wpdb->update(
                            $tableName,
                            array('value' => $value),
                            array('config' => $config),
                            array('%s'),
                            array('%s')
                        );

                        if ($result) {
                            $response['message'].= "Updated $config with $value --";
                        }
                    } else {
                        $response['message'].= "Wrong $config with $value --";
                    }
                }
            } else {
                $response = array('message'=>'No data found');
            }
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
    public function paylaterRegisterEndpoint()
    {
        register_rest_route(
            'paylater/v1',
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
            'paylater/v1',
            '/configController/(?P<secret>\w+)',
            array(
                'methods'  => 'POST',
                'callback' => array(
                    $this,
                    'updateExtraConfig')
            ),
            true
        );
    }
}

/**
 * Add widget Js
 **/
function add_widget_js()
{
    wp_enqueue_script('pmtSdk', 'https://cdn.pagamastarde.com/js/pmt-v2/sdk.js', '', '', true);
}

new WcPaylater();
