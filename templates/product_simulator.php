<script type="text/javascript" src="https://cdn.pagamastarde.com/pmt-js-client-sdk/3/js/client-sdk.min.js"></script>

<div class="PmtSimulator" style="width: max-content; display:none"
     data-pmt-num-quota="<? echo $settings['min_installments'] ; ?>"
     data-pmt-max-ins="<? echo $settings['max_installments']; ?>" data-pmt-style="blue"
     data-pmt-type="<? echo $settings['simulator_product']; ?>"
     data-pmt-discount="<? echo $settings['discount']; ?>" data-pmt-amount=""
     data-pmt-expanded="no">
</div>
<script>

function getWoocommercePrice(simulatorObject)
{
    // PRICE
    priceDiv = document.querySelectorAll(simulatorObject.selector);
    if( priceDiv !== 'undefined' ) {
        pricesLength = document.querySelectorAll(simulatorObject.selector).length;
        if( pricesLength !== 'undefined' && pricesLength > 0 ) {
            pricesLength = pricesLength - 1;
            price = document.querySelectorAll(selector)[pricesLength].innerText.toString().replace(/€|&euro/g, '');
            if( price!=='undefined' && price!='' ) {
                currentPrice = document.getElementsByClassName('PmtSimulator')[0].getAttribute('data-pmt-amount');
                if( simulatorObject.quantity_selector !== 'undefined' && simulatorObject.quantity_selector!='' ) {
                    qtys = document.querySelectorAll(simulatorObject.quantity_selector);
                    if(qtys.length == 1) {
                        qty = parseFloat(document.querySelector(simulatorObject.quantity_selector).value);
                        price = price * qty;
                    }
                }

                if( price < simulatorObject.min_amount || price > simulatorObject.max_amount ) {
                    document.getElementsByClassName('PmtSimulator')[0].style.display = 'none';
                    document.getElementsByClassName('PmtSimulator')[0].setAttribute('data-pmt-amount', '');
                } else if (currentPrice != price ) {
                        document.getElementsByClassName('PmtSimulator')[0].style.display = '';
                        document.getElementsByClassName('PmtSimulator')[0].setAttribute('data-pmt-amount', price);
                        pmtClient.simulator.reload();
                        }
            }
        }
    }
}

// CONFIG VARS
var min_amount = '<? echo $settings['min_amount'];?>';
min_amount = (min_amount != '') ? parseFloat(min_amount) : '0.00';

var max_amount = '<? echo $settings['max_amount'];?>';
max_amount = (max_amount != '') ? parseFloat(max_amount) : '10000000.00';

var quantity_selector = '<? echo html_entity_decode($settings['quantity_selector']); ?>';

var selector = '<? echo html_entity_decode($settings['price_selector']);?>';
if (selector != '') {
    var simulatorObject = {min_amount:min_amount,
                            max_amount:max_amount,
                            selector:selector,
                            quantity_selector:quantity_selector};
    setInterval(function () {
        getWoocommercePrice(simulatorObject);
    }, 2000);
}

</script>
<script>
    if (typeof pmtClient !== 'undefined') {
        pmtClient.setPublicKey("<? echo $public_key; ?>");
        pmtClient.simulator.reload();
    }
</script>
