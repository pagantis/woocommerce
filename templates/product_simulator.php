<script>
    var simulatorId = null;

    function loadSimulator()
    {
        if (typeof pmtSDK != 'undefined') {
            pmtSDK.simulator.init({
                publicKey: '<?php echo $public_key; ?>',
                selector: '.woocommerce-product-details__short-description',
                type: <?php echo $simulator_type; ?>,
                totalAmount: <?php echo $total; ?>,
                position: pmtSDK.simulator.positions.BEFORE
            });
            clearInterval(simulatorId);
        }
    }

    simulatorId = setInterval(function () {
        loadSimulator();
    }, 2000);


</script>
