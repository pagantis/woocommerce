<?php if ($message) { ?>
<p><?php echo $message; ?></p>
<?php } ?>

<?php
if ($enabled!=='0'&& isset($total)) { ?>
    <span class="js-pmt-payment-type"></span>
    <div class="PmtSimulator" style="width: max-content"
         data-pmt-num-quota="<?php echo $min_installments;?>" data-pmt-max-ins="<?php echo $max_installments;?>"
         data-pmt-style="blue" data-pmt-type="<?php echo $enabled; ?>" data-pmt-discount="0"
         data-pmt-amount="<?php echo $total; ?>" data-pmt-expanded="no">
    </div>
    <script>

    </script>
    <script>
        if (typeof pmtClient !== 'undefined') {
            pmtClient.setPublicKey("<?php echo $public_key; ?>");
            pmtClient.simulator.reload();
        }

        var paylaterButton = document.getElementById('payment_method_paylater');
        if (paylaterButton !== undefined)
        {
            paylaterButton.addEventListener("click", function(){
                pmtClient.simulator.reload();
            });
        }
    </script>
<?php }?>
