/* global pagantis_params */

jQuery(function ($) {

    //pagantis_params is required to continue, ensure the object exists
    if (typeof pagantis_params === 'undefined') {
        return false;
    }

    var ppm_checkout = {
        $bodyElem: $('body'),
        $orderReviewElem: $('#order_review'),
        $checkoutFormElem: $('form.checkout'),
        $paymentMethodsList: $('.wc_payment_methods'),
        paymentMethodName: '',
        isPagantisPaymentMethod: false,
        selectedPaymentMethod: false,
        checkout_values: {},
        updateTimer: false,
        xhr: false,
        init: function () {

            // Checks if Pagantis is selected Payment methods
            ppm_checkout.$checkoutFormElem.on('click', 'input[name="payment_method"]', this.pagantis_payment_method_selected);

            // unbind normal submit event
            if (ppm_checkout.isPagantisPaymentsSelected()) {
                ppm_checkout.$checkoutFormElem.off('submit', function () {
                    console.log('submit ahas been unbinded');
                    return false;
                });
                ppm_checkout.$checkoutFormElem.on('checkout_place_order', function () {
                    console.log('event checkout_place_order intercepted');
                    return false;
                });
            }
            ;


            // Store all billing and shipping values.
            $(document).ready(function () {
                $('#customer_details input, #customer_details select').each(function () {
                    var fieldName = $(this).attr('name');
                    var fieldValue = $(this).val();
                    ppm_checkout.checkout_values[fieldName] = fieldValue;
                });
            });


            ppm_checkout.$checkoutFormElem.submit(function (event) {
                console.info(' trigger has worked');
                ppm_checkout.submitPagantisOrder();
            });
        },

        isPagantisPaymentsSelected: function () {
            if (ppm_checkout.get_selected_method() == 'pagantis') {
                return true;
            }
            return false;
        },
        get_selected_method: function () {
            return ppm_checkout.$checkoutFormElem.find('input[name="payment_method"]:checked').val();
        },
        pagantis_payment_method_selected: function (e) {
            var selectedPaymentMethod = ppm_checkout.get_selected_method();
            if (selectedPaymentMethod == 'pagantis') {
                ppm_checkout.isPagantisPaymentMethod = true;
                ppm_checkout.paymentMethodName = selectedPaymentMethod;
            }
        },

        is_valid_json: function (raw_json) {
            try {
                var json = $.parseJSON(raw_json);

                return (json && 'object' === typeof json);
            } catch (e) {
                return false;
            }
        },
        handleUnloadEvent: function (e) {
            // Modern browsers have their own standard generic messages that they will display.
            // Confirm, alert, prompt or custom message are not allowed during the unload event
            // Browsers will display their own standard messages

            // Check if the browser is Internet Explorer
            if ((navigator.userAgent.indexOf('MSIE') !== -1) || (!!document.documentMode)) {
                // IE handles unload events differently than modern browsers
                e.preventDefault();
                return undefined;
            }

            return true;
        },
        attachUnloadEventsOnSubmit: function () {
            $(window).on('beforeunload', this.handleUnloadEvent);
        },
        detachUnloadEventsOnSubmit: function () {
            $(window).unbind('beforeunload', this.handleUnloadEvent);
        },
        reset_update_checkout_timer: function () {
            clearTimeout(ppm_checkout.updateTimer);
        },
        blockOnSubmit: function ($form) {
            var form_data = $form.data();

            if (1 !== form_data['blockUI.isBlocked']) {
                $form.block({
                    message: null,
                    overlayCSS: {
                        background: '#fff',
                        opacity: 0.6
                    }
                });
            }
        },
        submitOrder: function () {
            ppm_checkout.blockOnSubmit($(this));
        },
        submitPagantisOrder: function () {
            var $form = $(this);

            if (ppm_checkout.isPagantisPaymentMethod === true) {
                $form.addClass('processing');

                ppm_checkout.blockOnSubmit($form);

                // Attach event to block reloading the page when the form has been submitted
                //ppm_checkout.attachUnloadEventsOnSubmit();
                console.info(ppm_checkout.checkout_values)
                if ($.checkout_values.isEmpty(this.checkout_values)) {
                    return;
                }

                // ajaxSetup is global, but we use it to ensure JSON is valid once returned.
                $.ajaxSetup({
                    dataFilter: function (raw_response, dataType) {
                        // We only want to work with JSON
                        if ('json' !== dataType) {
                            return raw_response;
                        }

                        if (ppm_checkout.is_valid_json(raw_response)) {
                            return raw_response;
                        } else {
                            // Attempt to fix the malformed JSON
                            var maybe_valid_json = raw_response.match(/{"result.*}/);

                            if (null === maybe_valid_json) {
                                console.log('Unable to fix malformed JSON');
                            } else if (ppm_checkout.is_valid_json(maybe_valid_json[0])) {
                                console.log('Fixed malformed JSON. Original:');
                                console.log(raw_response);
                                raw_response = maybe_valid_json[0];
                            } else {
                                console.log('Unable to fix malformed JSON');
                            }
                        }
                        return raw_response;
                    }
                });




                var data = {
                    security: pagantis_params.place_order_nonce,
                    payment_method: ppm_checkout.get_payment_method(),
                    checkout_values: this.checkout_values,
                    post_data: $('form.checkout').serialize()
                };

                console.info(data);
                $.ajax({
                    type: 'POST',
                    url: pagantis_params.place_order_url,
                    security: pagantis_params.place_order_nonce,
                    payment_method: ppm_checkout.get_payment_method(),
                    checkout_values: this.checkout_values,
                    data: $form.serialize(),
                    dataType: 'json',
                    success: function (result) {
                        // Detach the unload handler that prevents a reload / redirect
                        ppm_checkout.detachUnloadEventsOnSubmit();
                        try {
                            if ('success' === result.result && $form.triggerHandler('checkout_place_order_success') !== false) {
                                if (-1 === result.redirect.indexOf('https://') || -1 === result.redirect.indexOf('http://')) {
                                    window.location = result.redirect;
                                } else {
                                    window.location = decodeURI(result.redirect);
                                }
                            } else if ('failure' === result.result) {
                                throw 'Result failure';
                            } else {
                                throw 'Invalid response';
                            }
                        } catch (err) {
                            // Reload page
                            if (true === result.reload) {
                                window.location.reload();
                                return;
                            }

                            // Trigger update in case we need a fresh nonce
                            if (true === result.refresh) {
                                $(document.body).trigger('update_checkout');
                            }

                            // Add new errors
                            if (result.messages) {
                                pagantis_params.submit_error(result.messages);
                            } else {
                                pagantis_params.submit_error('<div class="woocommerce-error">' + pagantis_params.i18n_checkout_error + '</div>');
                            }
                        }
                    },
                    error: function (jqXHR, textStatus, errorThrown) {
                        // Detach the unload handler that prevents a reload / redirect
                        pagantis_params.detachUnloadEventsOnSubmit();

                        pagantis_params.submit_error('<div class="woocommerce-error">' + errorThrown + '</div>');
                    }
                });
            }

            return false;
        },
        submit_error: function (error_message) {
            $('.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message').remove();
            pagantis_params.$checkoutFormElem.prepend('<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' + error_message + '</div>');
            pagantis_params.$checkoutFormElem.removeClass('processing').unblock();
            pagantis_params.$checkoutFormElem.find('.input-text, select, input:checkbox').trigger('validate').blur();
            pagantis_params.scroll_to_notices();
            $(document.body).trigger('checkout_error');
        },
        scroll_to_notices: function() {
            var scrollElement           = $( '.woocommerce-NoticeGroup-updateOrderReview, .woocommerce-NoticeGroup-checkout' );

            if ( ! scrollElement.length ) {
                scrollElement = $( '.form.checkout' );
            }
            $.scroll_to_notices( scrollElement );
        }
    };


    ppm_checkout.init();
});
