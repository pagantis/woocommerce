/* global variationSimulatorData   */

if (typeof variationSimulatorData == 'undefined'){
  throw new Error("variationSimulatorData is undefined");
}

window.lastPrice = '';
function updateSimulator()
{
    if (window.WCSimulatorId !== '')
    {
        var updateSelector = variationSimulatorData.variationSelector;
        if (updateSelector === 'default') {
            updateSelector = 'div.woocommerce-variation-price span.price span.woocommerce-Price-amount';
        }

        var productType = variationSimulatorData.productType;

        if (productType !=='variable')
        {
            clearInterval(window.variationInterval);
        }
        else
        {
            var priceDOM = document.querySelector(updateSelector);
            if (priceDOM != null) {
                var newPrice = priceDOM.innerText;
                if (newPrice !== window.lastPrice) {
                    window.lastPrice = newPrice;
                    window.pgSDK.simulator.update(window.WCSimulatorId, {itemAmountSelector: updateSelector})
                }
            } else {
                return false;
            }
        }
    }
}
window.variationInterval = setInterval(function () {
    updateSimulator();
}, 5000);