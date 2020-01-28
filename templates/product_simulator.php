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

        var price = '<?php echo $total;?>';

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

        if (promotedProduct == 'true') {
            simulator_options.itemPromotedAmountSelector = priceSelector;
        }

        if (typeof sdk != 'undefined') {
            console.log(window.WCSimulatorId);
            window.WCSimulatorId = sdk.simulator.init(simulator_options);
            return false;
        }
    }

    window.attempts = 0;
    window.loadingSimulator = setInterval(function () {
        loadSimulatorPagantis();
    }, 2000);
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
<div class="pagantisSimulator"></div>
