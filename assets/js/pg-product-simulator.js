/* global simulatorData  */
if (simulatorData.pagantisSimulator4x === 'enabled') {
    var simulator4xdiv = document.getElementsByClassName('mainPagantisSimulator');
    if (simulator4xdiv.length > 0 && typeof simulator4xdiv != 'undefined') {
        simulator4xdiv[0].innerHTML = simulatorData.simulatorMessage;
        var position = simulatorData.positionSelector4x;
        if (position !== 'default') { // Move to desired class
            var desiredPosition = document.getElementsByClassName(position);
            if (desiredPosition.length > 0 && typeof desiredPosition != 'undefined') {
                desiredPosition[0].appendChild(simulator4xdiv[0]);
            }
        }
    }
}


function findPriceSelector() {
    var priceSelectors = simulatorData.priceSelector;
    if (typeof priceSelectors == 'undefined') console.log("simulator priceSelectors is undefined");
    return priceSelectors.find(function (candidateSelector) {
        var priceDOM = document.querySelector(candidateSelector);
        return (priceDOM != null);
    });

}

function findPositionSelector() {
    var positionSelector = simulatorData.positionSelector;
    if (positionSelector === 'default') {
        positionSelector = '.pagantisSimulator';
    }

    return positionSelector;
}

function findQuantitySelector() {
    var quantitySelectors = simulatorData.quantitySelector;
    return quantitySelectors.find(function (candidateSelector) {
        var priceDOM = document.querySelector(candidateSelector);
        return (priceDOM != null);
    });
}

function finishInterval() {
    clearInterval(window.loadingSimulator);
    return true;
}

function checkSimulatorContent() {
    var simulatorLoaded = false;
    var positionSelector = findPositionSelector();
    var pgDiv = document.querySelectorAll(positionSelector);
    if (pgDiv.length > 0 && typeof window.WCSimulatorId != 'undefined') {
        var pgElement = pgDiv[0];
        if (pgElement.innerHTML !== '') {
            simulatorLoaded = true;
        }
    }
    return simulatorLoaded;
}

function findDestinationSim() {
    var destinationSim = simulatorData.finalDestination;
    if (destinationSim === 'default' || destinationSim == '') {
        destinationSim = 'woocommerce-product-details__short-description';
    }

    return destinationSim;
}

function checkAttempts() {
    window.attempts = window.attempts + 1;
    return (window.attempts > 10)
}

function loadSimulatorPagantis() {
    if (typeof pgSDK == 'undefined' || typeof simulatorData == 'undefined') {
        return false;
    }

    if (checkAttempts() || checkSimulatorContent()) {
        return finishInterval();
    }

    var country = simulatorData.country;
    var locale = simulatorData.locale;
    var sdk = pgSDK;

    var positionSelector = findPositionSelector();
    var priceSelector = findPriceSelector();
    var promotedProduct = simulatorData.promoted;
    var quantitySelector = findQuantitySelector();

    var simulator_options = {
        publicKey: simulatorData.public_key,
        type: eval(simulatorData.simulator_type),
        selector: positionSelector,
        itemQuantitySelector: quantitySelector,
        locale:locale,
        country: country,
        itemAmountSelector: priceSelector,
        amountParserConfig: {
            thousandSeparator: simulatorData.thousandSeparator,
            decimalSeparator: simulatorData.decimalSeparator,
        },
        numInstalments: simulatorData.pagantisQuotesStart,
        skin: eval(simulatorData.pagantisSimulatorSkin),
        position: eval(simulatorData.pagantisSimulatorPosition),
    };

    window.pgSDK = sdk;
    if (promotedProduct === 'true') {
        simulator_options.itemPromotedAmountSelector = priceSelector;
    }

    if (typeof window.pgSDK !== 'undefined') {
        window.WCSimulatorId = window.pgSDK.simulator.init(simulator_options);
        return false;
    }
}

window.attempts = 0;
if (simulatorData.pagantisSimulator === 'enabled') {
    window.loadingSimulator = setInterval(function () {
        loadSimulatorPagantis();
    }, 2000);
}

if (simulatorData.promoted === 'true') {
    simulatorData.promotedMessage;
}

