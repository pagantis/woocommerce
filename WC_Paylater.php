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
    const GIT_HUB_URL     = 'https://github.com/PagaMasTarde/woocommerce';
    const PMT_DOC_URL     = 'https://docs.pagamastarde.com';
    const SUPPORT_EML     = 'mailto:soporte@pagamastarde.com?Subject=woocommerce_plugin';

    /**
     * WC_Paylater constructor.
     */
    public function __construct()
    {
        $this->template_path  = plugin_dir_path(__FILE__) . '/templates/';

        load_plugin_textdomain('paylater', false, basename(dirname(__FILE__)).'/languages');
        add_filter('woocommerce_payment_gateways', array($this, 'addPaylaterGateway'));
        add_filter('woocommerce_available_payment_gateways', array($this, 'paylaterFilterGateways'), 9999);
        add_filter('plugin_row_meta', array($this,'paylaterRowMeta'), 10, 2);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this,'paylaterActionLinks'));
        add_action('woocommerce_after_add_to_cart_form', array( $this, 'paylaterAddProductSimulator'));
        add_action('wp_enqueue_scripts', 'add_widget_js');
    }

    /**
     * Product simulator
     */
    public function paylaterAddProductSimulator()
    {
        global $product;
        $cfg = get_option('woocommerce_paylater_settings');
        if ($cfg['enabled']!=='yes' || $cfg['public_key']=='' || $cfg['secret_key']=='' ||
            $cfg['simulator_product']==='0') {
            return;
        }

        $template_fields = array(
            'total' => is_numeric($product->price) ? $product->price : 0,
            'settings' => $cfg
        );
        wc_get_template('product_simulator.php', $template_fields, '', $this->template_path);
    }

    /**
     * Add Paylater to payments list.
     * @param $methods
     *
     * @return array
     */
    public function addPaylaterGateway($methods)
    {
        if (!class_exists('WC_Payment_Gateway')) {
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
        $params_array = array('page'=>'wc-settings','tab'=>'checkout','section'=>'paylater');
        $setting_url = esc_url(add_query_arg($params_array, admin_url('admin.php?')));
        $setting_link = '<a href="'.$setting_url.'">'.__('Ajustes', 'paylater').'</a>';

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
            $links[]='<a href="'.WcPaylater::GIT_HUB_URL.'" target="_blank">'.__('Documentación', 'paylater').'</a>';
            $links[]='<a href="'.WcPaylater::PMT_DOC_URL.'" target="_blank">'.__('Documentación de la API', 'paylater').'</a>';
            $links[]='<a href="'.WcPaylater::SUPPORT_EML.'">'.__('Soporte', 'paylater').'</a>';
            return $links;
        }
        return $links;
    }
}

/**
 * Add widget Js
 **/
function add_widget_js()
{
    wp_enqueue_script('pmtSdk', 'https://cdn.pagamastarde.com/pmt-js-client-sdk/3/js/client-sdk.min.js', '', '', true);
}

new WcPaylater();
