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
    )
);
