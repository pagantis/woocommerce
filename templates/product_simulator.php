<script>
    function findPriceSelector()
    {
        var priceSelectors = <?php echo json_encode($priceSelector);?>;
        return priceSelectors.find(function(candidateSelector) {
            var priceDOM = document.querySelector(candidateSelector);
            return (priceDOM != null );
        });

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
        var pmtDiv = document.getElementsByClassName("pagantisSimulator");
        if (pmtDiv.length > 0) {
            var pmtElement = pmtDiv[0];
            if (pmtElement.innerHTML != '') {
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
        if(typeof pmtSDK == 'undefined' || typeof pgSDK == 'undefined')
        {
            return false;
        }

        if (checkAttempts() || checkSimulatorContent())
        {
            return finishInterval();
        }

        var price = '<?php echo $total;?>';

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

        var priceSelector = findPriceSelector();

        var quantitySelector = findQuantitySelector();

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
    window.loadingSimulator = setInterval(function () {
        loadSimulatorPagantis();
    }, 2000);
</script>
<div class="pagantisSimulator"></div>
