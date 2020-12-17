<script type="text/javascript" src="https://cdn.pagantis.com/js/pg-v2/sdk.js"></script>
<script type="application/javascript">
    if (typeof pgSDK !== 'undefined') {
        document.addEventListener("DOMContentLoaded", function(){
            pgSDK.modal.open(
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
        pgSDK.modal.onClose(function() {
            window.location.href = "<?=$checkoutUrl?>";
        });
    }
</script>