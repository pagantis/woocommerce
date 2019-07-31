<?php if ($enabled!=='0' && isset($total) && $simulator_enabled!=='0' && $allowed_country!=='0') { ?>
    <div class="pagantisSimulator"></div>
    <script>
        window.WCsimulatorId = null;

        function loadSimulator()
        {
            if(typeof pmtSDK == 'undefined' || typeof pgSDK == 'undefined')
            {
                return false;
            }

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

            if (typeof sdk != 'undefined') {
                window.WCSimulatorId = sdk.simulator.init({
                    publicKey: '<?php echo $public_key; ?>',
                    selector: '.pagantisSimulator',
                    totalAmount: '<?php echo $total; ?>',
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
<?php } ?>
