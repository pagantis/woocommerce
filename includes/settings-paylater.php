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
    'extra_title' => array(
        'title'       => __('Title', 'paylater'),
        'description' => __('Title to show near to payment method', 'paylater'),
        'type'        => 'text',
        'default'     => __('Instant financing', 'paylater'),
    ),
    'checkout_title' => array(
        'title'       => __('Checkout description', 'paylater'),
        'description' => __('Title to show with the checkout simulator', 'paylater'),
        'type'        => 'text',
        'default'     => __('Pay up to 12 comfortable installments with Pay + Afternoon. Application totally online and without paperwork, And the answer is immediate!', 'paylater'),
    ),
    'min_amount' => array(
        'title'       => __('Minimum amount', 'paylater'),
        'type'        => 'text',
        'description' => __('Minimum amount to activate the plugin', 'paylater'),
        'default'     => '1',
    ),
    'max_amount' => array(
        'title'       => __('Maximum amount', 'paylater'),
        'type'        => 'text',
        'description' => __('Maximum amount to activate the plugin', 'paylater'),
        'default'     => '1000000',
    ),
    'min_installments' => array(
        'title'       => __('Number of default installments', 'paylater'),
        'type'        => 'number',
        'description' => __('Number of installments that the simulator will show by default. Must be between 2-12', 'paylater'),
        'default'     => '3',
    ),
    'max_installments' => array(
        'title'       => __('Maximum numbers of installments', 'paylater'),
        'type'        => 'number',
        'description' => __('Maximum number of installments to show in simulator. Must be between 2-12', 'paylater'),
        'default'     => '12',
    ),
    'iframe' => array(
        'title'       => __('Display mode', 'paylater'),
        'type'        => 'select',
        'description' => __('Open form in a pop-up', 'paylater'),
        'default'     => 'false',
        'desc_tip'    => true,
        'options'     => array(
            'false' => __('Redirect', 'paylater'),
            'true'  => __('Iframe', 'paylater'),
        )
    ),
    'simulator_product' => array(
        'title'       => __('Product simulator', 'paylater'),
        'type'        => 'select',
        'description' => __('Include simulator in product page', 'paylater'),
        'default'     => '6',
        'desc_tip'    => true,
        'options'     => array(
            '0'       => __('No', 'paylater'),
            '1'       => __('Simple', 'paylater'),
            '2'       => __('Full', 'paylater'),
            '6'       => __('Mini', 'paylater'),
            '3'       => __('Selectable', 'paylater'),
            '4'       => __('Descriptive text', 'paylater'),
        )
    ),
    'simulator_checkout' => array(
        'title'       => __('Checkout simulator', 'paylater'),
        'type'        => 'select',
        'description' => __('Include simulator in checkout page', 'paylater'),
        'default'     => '6',
        'desc_tip'    => true,
        'options'     => array(
            '0'       => __('No', 'paylater'),
            '1'       => __('Simple', 'paylater'),
            '2'       => __('Full', 'paylater'),
            '6'       => __('Mini', 'paylater'),
            '3'       => __('Selectable', 'paylater'),
            '4'       => __('Descriptive text', 'paylater'),
        )
    ),
    'price_selector' => array(
        'title'       => __('Price selector', 'paylater'),
        'type'        => 'text',
        'description' => __('Html selector to get the price on the product page. It will be the amount used in the product simulator if it is active. By default: ', 'paylater') .
                            '"div.summary.entry-summary span.woocommerce-Price-amount.amount"',
        'default'     => 'div.summary.entry-summary span.woocommerce-Price-amount.amount',
    ),
    'quantity_selector' => array(
        'title'       => __('Quantity selector', 'paylater'),
        'type'        => 'text',
        'description' => __('Html selector to obtain the number of products to buy on the product page. The quantity of products will be multiplied by the price of the product to obtain the final price, this price will be the one that will be used in the product simulator if it is active. Leave blank to omit its use. By default: ', 'paylater') .
                            '"div.quantity > input"',
        'default'     => 'div.quantity > input',
    ),
    'ok_url' => array(
        'title'       => __('Url Ok', 'paylater'),
        'description' => __('Page where the user will be redirected after a correct payment process. {{order-received}} will be replaced by the value of the order_id', 'paylater'),
        'type'        => 'text',
    ),
    'ko_url' => array(
        'title'       => __('Url Ko', 'paylater'),
        'description' => __('Page where the user will be redirected after a failure payment process. {{order-received}} will be replaced by the value of the order_id', 'paylater'),
        'type'        => 'text',
    )
);
