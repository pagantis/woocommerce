<script>
    function findPriceSelector()
    {
        var priceSelectors = <?php echo json_encode($priceSelector);?>;
        return priceSelectors.find(function(candidateSelector) {
            var priceDOM = document.querySelector(candidateSelector);
            return (priceDOM != null );
        });

    }

    function findPositionSelector()
    {
        var positionSelector = '<?php echo $positionSelector;?>';
        if (positionSelector === 'default') {
            positionSelector = '.pagantisSimulator';
        }

        return positionSelector;
    }

    function findQuantitySelector()
    {
        var quantitySelectors = <?php echo json_encode($quantitySelector);?>;
        return quantitySelectors.find(function(candidateSelector) {
            var priceDOM = document.querySelector(candidateSelector);
            return (priceDOM != null );
        });
    }

    function finishInterval() {
        clearInterval(window.loadingSimulator);
        return true;
    }

    function checkSimulatorContent() {
        var simulatorLoaded = false;
        var positionSelector = findPositionSelector();
        var pgDiv = document.querySelectorAll(positionSelector);
        if (pgDiv.length > 0 && typeof window.WCSimulatorId!='undefined') {
            var pgElement = pgDiv[0];
            if (pgElement.innerHTML != '') {
                simulatorLoaded = true;
            }
        }
        return simulatorLoaded;
    }

    function findDestinationSim()
    {
        var destinationSim = '<?php echo $finalDestination;?>';
        if (destinationSim === 'default' || destinationSim == '') {
            destinationSim = 'woocommerce-product-details__short-description';
        }

        return destinationSim;
    }

    function checkAttempts() {
        window.attempts = window.attempts + 1;
        return (window.attempts > 4)
    }

    function loadSimulatorPagantis()
    {
        if(typeof pgSDK == 'undefined')
        {
            return false;
        }

        if (checkAttempts() || checkSimulatorContent())
        {
            return finishInterval();
        }

        var country = '<?php echo $country; ?>';
        var locale = '<?php echo $locale; ?>';
        var sdk = pgSDK;

        var positionSelector = findPositionSelector();
        var priceSelector = findPriceSelector();
        var promotedProduct = '<?php echo $promoted;?>';
        var quantitySelector = findQuantitySelector();

        simulator_options = {
            publicKey: '<?php echo $public_key; ?>',
            type: <?php echo $simulator_type; ?>,
            selector: positionSelector,
            itemQuantitySelector: quantitySelector,
            locale: locale,
            country: country,
            itemAmountSelector: priceSelector,
            amountParserConfig :  {
                thousandSeparator: '<?php echo $thousandSeparator;?>',
                decimalSeparator: '<?php echo $decimalSeparator;?>'
            },
            numInstalments : '<?php echo $pagantisQuotesStart;?>',
            skin : <?php echo $pagantisSimulatorSkin;?>,
            position: <?php echo $pagantisSimulatorPosition;?>
        };

        window.pgSDK = sdk;
        if (promotedProduct == 'true') {
            simulator_options.itemPromotedAmountSelector = priceSelector;
        }

        if (typeof window.pgSDK != 'undefined') {
            window.WCSimulatorId = window.pgSDK.simulator.init(simulator_options);

            return false;
        }
    }

    window.attempts = 0;
    window.loadingSimulator = setInterval(function () {
        loadSimulatorPagantis();
    }, 2000);

    window.lastPrice = '';
    function updateSimulator()
    {
        if (window.WCSimulatorId != '')
        {
            var updateSelector = '<?php echo $variationSelector;?>';

            var productType = '<?php echo $productType;?>';

            if (updateSelector == 'default' || updateSelector === '' || productType!=='variable')
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
<style>
    .pg-no-interest{
        color: #00c1d5
    }
</style>
<?php
if ($promoted == 'true') {
    echo $promotedMessage;
}
?>
<br/><div class="pagantisSimulator"></div><br/><br/>
