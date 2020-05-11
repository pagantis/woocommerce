<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings for Pagantis Gateway.
 */
return array(
    'enabled' => array(
        'title'       => __('Activate the module', 'pagantis'),
        'type'        => 'checkbox',
        'default'     => 'yes'
    ),
    'pagantis_public_key' => array(
        'title'       => __('Public Key', 'pagantis'),
        'type'        => 'text',
        'description' => __('MANDATORY. You can get in your pagantis profile', 'pagantis')
    ),
    'pagantis_private_key' => array(
        'title'       => __('Secret Key', 'pagantis'),
        'type'        => 'text',
        'description' => __('MANDATORY. You can get in your pagantis profile', 'pagantis')
    ),
    'simulator' => array(
        'title'       => __('Product simulator', 'pagantis'),
        'type'        => 'checkbox',
        'default'     => 'yes'
    ),
//    'debug'            => array(
//    'title'       => __('Debug log', 'woocommerce'),
//    'type'        => 'checkbox',
//    'label'       => __('Enable logging', 'woocommerce'),
//    'default'     => 'no',
//    /* translators: %s: URL */
//    'description' => sprintf(__(
//        'Log Pagantis events to troubleshoot. Log Location %s Note: this may log personal information. We recommend using this for debugging purposes only and deleting the logs when finished.',
//        'woocommerce'
//    ), '<code>' . WC_Log_Handler_File::get_log_file_path('pagantis') . '</code>')),
);
