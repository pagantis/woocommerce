<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings for Pagantis Gateway.
 */
return array(
    'enabled_4x' => array(
        'title'       => __('Activate the module 4x', 'pagantis'),
        'type'        => 'checkbox',
        'default'     => 'yes'
    ),
    'pagantis_public_key_4x' => array(
        'title'       => __('Public Key 4x', 'pagantis'),
        'type'        => 'text',
        'description' => __('MANDATORY. You can get in your pagantis backoffice', 'pagantis')
    ),
    'pagantis_private_key_4x' => array(
        'title'       => __('Secret Key 4x', 'pagantis'),
        'type'        => 'text',
        'description' => __('MANDATORY. You can get in your pagantis backoffice', 'pagantis')
    ),
    'enabled' => array(
        'title'       => __('Activate the module 12x', 'pagantis'),
        'type'        => 'checkbox',
        'default'     => 'no'
    ),
    'pagantis_public_key' => array(
        'title'       => __('Public Key 12x', 'pagantis'),
        'type'        => 'text',
        'description' => __('You can get in your pagantis backoffice', 'pagantis')
    ),
    'pagantis_private_key' => array(
        'title'       => __('Secret Key 12x', 'pagantis'),
        'type'        => 'text',
        'description' => __('You can get in your pagantis backoffice', 'pagantis')
    ),
    'simulator' => array(
        'title'       => __('Simulator 12x', 'pagantis'),
        'type'        => 'checkbox',
        'default'     => 'yes'
    )
);
