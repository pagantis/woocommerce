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
        'default'     => 'no'
    ),
    'public_key' => array(
        'title'       => __('Public Key', 'paylater'),
        'type'        => 'text',
        'description' => __('MANDATORY. You can get in your pagamastarde profile', 'paylater')
    ),
    'secret_key' => array(
        'title'       => __('Secret Key', 'paylater'),
        'type'        => 'text',
        'description' => __('MANDATORY. You can get in your pagamastarde profile', 'paylater')
    ),
    'simulator' => array(
        'title'       => __('Product simulator', 'paylater'),
        'type'        => 'select',
        'description' => __('Include simulator in product page', 'paylater'),
        'default'     => '6',
        'desc_tip'    => true,
        'options'     => array(
            '0'       => __('No', 'paylater'),
            'pmtSDK.simulator.types.SIMPLE'     => __('Simple', 'paylater'),
            'pmtSDK.simulator.types.SELECTABLE' => __('Selectable', 'paylater'),
            'pmtSDK.simulator.types.TEXT'       => __('Descriptive text', 'paylater'),
        )
    )
);
