<?php if ($enabled==='yes' && isset($total) && $simulator_enabled==='yes' && $allowed_country===true) {
    ?>
    <script>
        window.WCsimulatorId4x = null;
        window.product = "<?php echo $product;?>";

        function loadSimulator4x()
        {
            var product = "<?php echo $product;?>"
            if(typeof pgSDK == 'undefined')

            window.attempts = window.attempts + 1;
            if (window.attempts > 4 )
            {
                clearInterval(loadingSimulator4x);
                return true;
            }
            var pgDiv = document.getElementsByClassName("mainPagantisSimulator");
            if(pgDiv.length > 0) {
                var pgElement = pgDiv[0];
                if(pgElement.innerHTML != '' )
                {
                    clearInterval(loadingSimulator4x);
                    return true;
                }
            }

            var country = '<?php echo $country; ?>';
            var locale = '<?php echo $locale; ?>';

            if (typeof pgSDK != 'undefined') {
                if (typeof sdk == 'undefined') {
                    var sdk = pgSDK;
                }
                window.WCSimulatorId4x = pgSDK.simulator.init({
                    type: <?php echo $simulator_type; ?>,
                    publicKey: '<?php echo $public_key; ?>',
                    selector: '.mainPagantisSimulator',
                    totalAmount: '<?php echo $total; ?>',
                    totalPromotedAmount: '<?php echo $promoted_amount; ?>',
                    skin : <?php echo $pagantisSimulatorSkin;?>,
                    locale: locale,
                    country: country
                });
                return false;
            }
        }

        window.attempts = 0;
        loadingSimulator4x = setInterval(function () {
            loadSimulator4x();
        }, 2000);

    </script>
<?php } ?>
