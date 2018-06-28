<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings for Paylater Gateway.
 */
return array(
    'enabled' => array(
        'title'       => __('Activar el modulo', 'paylater'),
        'type'        => 'checkbox',
        'default'     => 'no'
    ),
    'public_key' => array(
        'title'       => __('Public Key', 'paylater'),
        'type'        => 'text',
        'description' => __('OBLIGATORIO. Puede obtenerla en su perfil de usuario de pagamastarde', 'paylater')
    ),
    'secret_key' => array(
        'title'       => __('Secret Key', 'paylater'),
        'type'        => 'text',
        'description' => __('OBLIGATORIO. Puede obtenerla en su perfil de usuario de pagamastarde', 'paylater')
    ),
    'extra_title' => array(
        'title'       => __('Título', 'paylater'),
        'description' => __('Título a mostrar junto al metodo de pago', 'paylater'),
        'type'        => 'text',
        'default'     => 'Financiación instantánea',
    ),
    'checkout_title' => array(
        'title'       => __('Descripción en el checkout', 'paylater'),
        'description' => __('Título a mostrar junto al simulador en el checkout', 'paylater'),
        'type'        => 'text',
        'default'     => 'Paga hasta en 12 cómodas cuotas con Paga+Tarde. Solicitud totalmente online y sin papeleos,
¡y la respuesta es inmediata!',
    ),
    'min_amount' => array(
        'title'       => __('Importe mínimo', 'paylater'),
        'type'        => 'text',
        'description' => __('Cantidad mínima para habilitar el módulo.', 'paylater'),
        'default'     => '1',
    ),
    'max_amount' => array(
        'title'       => __('Importe máximo', 'paylater'),
        'type'        => 'text',
        'description' => __('Cantidad maxima para habilitar el módulo', 'paylater'),
        'default'     => '1000000',
    ),
    'min_installments' => array(
        'title'       => __('Número de cuotas por defecto', 'paylater'),
        'type'        => 'number',
        'description' => __('Número de cuotas que va a mostrar el simulador por defecto. Debe estar entre 2-12', 'paylater'),
        'default'     => '3',
    ),
    'max_installments' => array(
        'title'       => __('Máximo número de pagos', 'paylater'),
        'type'        => 'number',
        'description' => __('Número máximo de pagos para mostrar en el simulador. Debe estar entre 2-12', 'paylater'),
        'default'     => '12',
    ),
    'iframe' => array(
        'title'       => __('Visualización', 'paylater'),
        'type'        => 'select',
        'description' => __('Abrir el formulario en un pop-up.', 'paylater'),
        'default'     => 'false',
        'desc_tip'    => true,
        'options'     => array(
            'false' => __('Redirect', 'paylater'),
            'true'  => __('Iframe', 'paylater'),
        )
    ),
    'simulator_product' => array(
        'title'       => __('Simulador en el producto', 'paylater'),
        'type'        => 'select',
        'description' => __('Incluir un simulador de cuotas en la pagina de producto', 'paylater'),
        'default'     => '6',
        'desc_tip'    => true,
        'options'     => array(
            '0'       => __('No', 'paylater'),
            '1'       => __('Simple', 'paylater'),
            '2'       => __('Completo', 'paylater'),
            '6'       => __('Mini', 'paylater'),
            '3'       => __('Seleccionable', 'paylater'),
            '4'       => __('Texto descriptivo', 'paylater'),
        )
    ),
    'simulator_checkout' => array(
        'title'       => __('Simulador al checkout', 'paylater'),
        'type'        => 'select',
        'description' => __('Incluir un simulador de cuotas en el checkout', 'paylater'),
        'default'     => '6',
        'desc_tip'    => true,
        'options'     => array(
            '0'       => __('No', 'paylater'),
            '1'       => __('Simple', 'paylater'),
            '2'       => __('Completo', 'paylater'),
            '6'       => __('Mini', 'paylater'),
            '3'       => __('Seleccionable', 'paylater'),
            '4'       => __('Texto descriptivo', 'paylater'),
        )
    ),
    'price_selector' => array(
        'title'       => __('Selector de precio', 'paylater'),
        'type'        => 'text',
        'description' => __('Selector de html para obtener el precio en la página de producto. Será la cantidad usada 
        en el simulador de producto si este está activo. 
        Por defecto: "div.summary.entry-summary span.woocommerce-Price-amount.amount"', 'paylater'),
        'default'     => 'div.summary.entry-summary span.woocommerce-Price-amount.amount',
    ),
    'quantity_selector' => array(
        'title'       => __('Selector de cantidad', 'paylater'),
        'type'        => 'text',
        'description' => __('Selector de html para obtener el número de productos a comprar en la página de producto.
        La cantidad de productos será multiplicada por el precio de producto para obtener el precio final, este precio 
        será el que se usará en el simulador de producto si este está activo. Dejar en blanco para omitir su uso. 
        Por defecto: "div.quantity > input"', 'paylater'),
        'default'     => 'div.quantity > input',
    ),
    'ok_url' => array(
        'title'       => __('Url Ok', 'paylater'),
        'description' => __('Pagina donde será redirigido el usuario tras un proceso de pago. 
        {{order-received}} sera reemplazado por el valor del order_id', 'paylater'),
        'type'        => 'text',
    ),
    'ko_url' => array(
        'title'       => __('Url Ko', 'paylater'),
        'description' => __('Pagina donde será redirigido el usuario tras un proceso de pago. 
        {{order-received}} sera reemplazado por el valor del order_id', 'paylater'),
        'type'        => 'text',
    )
);
