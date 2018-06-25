<?php if ($message) { ?>
<p><? echo $message; ?></p>
<?php } ?>

<?php
if( $enabled!=='0' && isset($discount) && isset($total) ) {?>
    <script type="text/javascript" src="https://cdn.pagamastarde.com/pmt-js-client-sdk/3/js/client-sdk.min.js"></script>

    <div class="PmtSimulator" style="width: max-content"
         data-pmt-num-quota="<? echo $min_installments;?>" data-pmt-max-ins="<? echo $max_installments;?>"
         data-pmt-style="blue" data-pmt-type="<? echo $enabled; ?>" data-pmt-discount="<? echo $discount; ?>"
         data-pmt-amount="<? echo $total; ?>" data-pmt-expanded="no">
    </div>
    <script>

    </script>
    <script>
        if (typeof pmtClient !== 'undefined') {
            pmtClient.setPublicKey("<? echo $public_key; ?>");
            pmtClient.simulator.reload();
        }
    </script>
<?php }?>
