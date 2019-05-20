<script>
    var simulatorId = null;

    function findPriceSelector()
    {
        var priceDOM = document.querySelector("div.summary.entry-summary ins span.woocommerce-Price-amount.amount");
        if (priceDOM != null )
            priceSelector = 'div.summary.entry-summary ins span.woocommerce-Price-amount.amount';
        else
            priceSelector = "div.summary.entry-summary span.woocommerce-Price-amount.amount:last-child";

        return priceSelector;

    }

    function loadSimulator()
    {
        var positionSelector = '<?php echo $positionSelector;?>';
        if (positionSelector === 'default') {
            positionSelector = '.PagantisSimulator';
        }

        var priceSelector = '<?php echo $priceSelector;?>';
        if (priceSelector === 'default') {
            priceSelector = findPriceSelector();
        }

        var quantitySelector = '<?php echo $quantitySelector;?>';
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
