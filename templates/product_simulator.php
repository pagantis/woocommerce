<script>
    var simulatorId = null;

    function loadSimulator()
    {
        var positionSelector = '<? echo $pmtCSSSelector;?>';

        if (positionSelector === 'default') {
            positionSelector = '.PmtSimulator';
        }

        if (typeof pmtSDK != 'undefined') {
            pmtSDK.simulator.init({
                publicKey: '<?php echo $public_key; ?>',
                selector: positionSelector,
                type: <?php echo $simulator_type; ?>,
            });
            clearInterval(simulatorId);
        }
    }

    simulatorId = setInterval(function () {
        loadSimulator();
    }, 2000);
</script>
<div class="PmtSimulator"></div>
