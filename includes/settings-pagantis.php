<?php

if (! defined('ABSPATH')) {
    exit;
}

$settings_array = array(
    'enabled'              => array(
        'title'   => __('Activate the module', 'pagantis'),
        'type'    => 'checkbox',
        'default' => 'yes'
    ),
    'pagantis_public_key'  => array(
        'title'       => __('Public Key', 'pagantis'),
        'type'        => 'text',
        'description' => __('MANDATORY. You can get in your pagantis profile', 'pagantis')
    ),
    'pagantis_private_key' => array(
        'title'       => __('Secret Key', 'pagantis'),
        'type'        => 'text',
        'description' => __('MANDATORY. You can get in your pagantis profile', 'pagantis')
    ),
    'simulator'            => array(
        'title'   => __('Product simulator', 'pagantis'),
        'type'    => 'checkbox',
        'default' => 'yes'
    )
);

if (in_array(strtolower((string)WP_DEBUG_LOG), array('true', '1'), true)) {
    $settings_array['debug'] = array(
        'title'       => __('Debug log', 'woocommerce'),
        'type'        => 'checkbox',
        'label'       => __('Enable logging', 'woocommerce'),
        'default'     => 'yes',
        'desc_tip'    => false,
        /* translators: %s: URL */
        'description' => sprintf(
            __(
                '<div class="woocommerce-info"> <p>Log Pagantis events to troubleshoot. </p><p> Log Location %s </p>
            <p><a class="woocommerce-BlankState-cta button-primary button" target="_blank" href="%s">You can also see the logs in this menu</a></p>
                    <p>Note: this may log personal information.</p> 
                    <p>We recommend using this for debugging purposes only and deleting the logs when finished.</p></div>',
                'woocommerce'
            ),
            '<code>' . wc_get_log_file_name(PAGANTIS_PLUGIN_ID) . '</code>',
            esc_url(add_query_arg(
                'log_file',
                wc_get_log_file_name(PAGANTIS_PLUGIN_ID),
                admin_url('admin.php?page=wc-status&tab=logs')
            ))
        )
    );
}


return $settings_array;
