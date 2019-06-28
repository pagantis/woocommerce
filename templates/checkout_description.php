<?php if ($message) { ?>
<p><?php echo $message; ?></p>
<?php } ?>

<?php if ($enabled!=='0' && isset($total) && $simulator_enabled!=='0' && $allowed_country!=='0') { ?>
    <div class="pagantisSimulator"></div>
    <script>
        window.WCsimulatorId = null;

        function loadSimulator() {
            if(typeof pmtSDK == 'undefined' || typeof pgSDK == 'undefined')
            {
                return false;
            }

            var pmtDiv = document.getElementsByClassName("pagantisSimulator");
            if(pmtDiv.length > 0) {
                var pmtElement = pmtDiv[0];
                if(pmtElement.innerHTML != '' )
                {
                    clearInterval(window.WCsimulatorId);
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
                sdk.simulator.init({
                    publicKey: '<?php echo $public_key; ?>',
                    selector: '.pagantisSimulator',
                    totalAmount: '<?php echo $total; ?>',
                    locale: locale
                });
                return false;
            }
        }

        window.WCsimulatorId = setInterval(function () {
            loadSimulator();
        }, 2000);

    </script>
<?php } ?>
