<script>
    var simulatorId = null;

    function loadSimulator()
    {
        var positionSelector = '<? echo $positionSelector;?>';
        if (positionSelector === 'default') {
            positionSelector = '.PagantisSimulator';
        }

        var priceSelector = '<? echo $priceSelector;?>';
        if (priceSelector === 'default') {
            priceSelector = 'div.summary.entry-summary span.woocommerce-Price-amount.amount';
        }

        var quantitySelector = '<? echo $quantitySelector;?>';
        if (quantitySelector === 'default') {
            quantitySelector = 'div.quantity>input';
        }

        if (typeof pgSDK != 'undefined') {
            pgSDK.simulator.init({
                publicKey: '<?php echo $public_key; ?>',
                type: <?php echo $simulator_type; ?>,
                selector: positionSelector,
                itemQuantitySelector: quantitySelector,
                itemAmountSelector: priceSelector
            });
            clearInterval(simulatorId);
        }
    }

    simulatorId = setInterval(function () {
        loadSimulator();
    }, 2000);
</script>
<div class="PagantisSimulator"></div>
