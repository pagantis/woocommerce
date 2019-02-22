<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings for Paylater Gateway.
 */
return array(
    'enabled' => array(
        'title'       => __('Activate the module', 'paylater'),
        'type'        => 'checkbox',
        'default'     => 'yes'
    ),
    'pmt_public_key' => array(
        'title'       => __('Public Key', 'paylater'),
        'type'        => 'text',
        'description' => __('MANDATORY. You can get in your pagamastarde profile', 'paylater')
    ),
    'pmt_private_key' => array(
        'title'       => __('Secret Key', 'paylater'),
        'type'        => 'text',
        'description' => __('MANDATORY. You can get in your pagamastarde profile', 'paylater')
    ),
    'simulator' => array(
        'title'       => __('Product simulator', 'paylater'),
        'type'        => 'checkbox',
        'default'     => 'yes'
    )
);
