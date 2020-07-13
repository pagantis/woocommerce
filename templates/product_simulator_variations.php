<script>
    window.lastPrice = '';
    function updateSimulator()
    {
        if (window.WCSimulatorId != '')
        {
            var updateSelector = '<?php echo $variationSelector;?>';
            if (updateSelector === 'default') {
                updateSelector = 'div.woocommerce-variation-price span.price span.woocommerce-Price-amount';
            }

            var productType = '<?php echo $productType;?>';

            if (productType!=='variable')
            {
                clearInterval(window.variationInterval);
            }
            else
            {
                var priceDOM = document.querySelector(updateSelector);
                if (priceDOM != null) {
                    var newPrice = priceDOM.innerText;
                    if (newPrice != window.lastPrice) {
                        window.lastPrice = newPrice;
                        window.pgSDK.simulator.update(window.WCSimulatorId, {itemAmountSelector: updateSelector})
                    }
                } else {
                    return false;
                }
            }
        }
    }

    window.variationInterval = setInterval(function () {
        updateSimulator();
    }, 5000);

</script>
