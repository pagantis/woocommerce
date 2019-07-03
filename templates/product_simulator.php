<script>
    var simulatorId = null;

    function findPriceSelector()
    {
        var priceDOM = document.querySelector("*:not(del)>.woocommerce-Price-amount");
        if (priceDOM != null )
            var priceSelector = '*:not(del)>.woocommerce-Price-amount';
        else
            var priceSelector = 'div.summary.entry-summary ins span.woocommerce-Price-amount.amount';

        return priceSelector;

    }


    function loadSimulator()
    {
        window.attempts = window.attempts + 1;
        if (window.attempts > 4 )
        {
            clearInterval(loadingSimulator);
            return true;
        }

        var pmtDiv = document.getElementsByClassName("pagantisSimulator");
        if(pmtDiv.length > 0) {
            var pmtElement = pmtDiv[0];
            if(pmtElement.innerHTML != '' )
            {
                clearInterval(loadingSimulator);
                return true;
            }
        }

        var locale = '<?php echo $locale; ?>';
        if (locale == 'es' || locale == '') {
            var sdk = pmtSDK;
        } else {
            var sdk = pgSDK;
        }

        var positionSelector = '<?php echo $positionSelector;?>';
        if (positionSelector === 'default') {
            positionSelector = '.pagantisSimulator';
        }

        var priceSelector = '<?php echo $priceSelector;?>';
        if (priceSelector === 'default') {
            priceSelector = findPriceSelector();
        }

        var quantitySelector = '<?php echo $quantitySelector;?>';
        if (quantitySelector === 'default') {
            quantitySelector = 'div.quantity>input';
        }

        if (typeof sdk != 'undefined') {
            window.WCSimulatorId = sdk.simulator.init({
                publicKey: '<?php echo $public_key; ?>',
                type: <?php echo $simulator_type; ?>,
                selector: positionSelector,
                itemQuantitySelector: quantitySelector,
                itemAmountSelector: priceSelector,
                locale: locale
            });
            return false;
        }
    }

    window.attempts = 0;
    loadingSimulator = setInterval(function () {
        loadSimulator();
    }, 2000);
</script>
<div class="pagantisSimulator"></div>
