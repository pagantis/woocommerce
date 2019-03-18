<script type="text/javascript" src="https://cdn.pagamastarde.com/pmt-js-client-sdk/3/js/client-sdk.min.js"></script>
<script type="application/javascript">
    if (typeof pmtClient !== 'undefined') {
        document.addEventListener("DOMContentLoaded", function(){
            pmtClient.modal.open(
                "<?=$url?>",
                {
                    closeOnBackDropClick: false,
                    closeOnEscPress: false,
                    backDropDark: false,
                    largeSize: true,
                    closeConfirmationMessage: "{l s='Sure you want to leave?' mod='pagantis'}"
                }
            );
        });
        pmtClient.modal.onClose(function() {
            window.location.href = "<?=$checkoutUrl?>";
        });
    }
</script>