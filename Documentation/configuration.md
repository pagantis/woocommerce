# Configuration

## :house: Access

To access to Pagantis admin panel, we need to open the Woocommerce admin panel and follow the next steps:

1 – Woocommerce => Settings
![Step 1](./woocommerce_configuration_1.png?raw=true "Step 1")

2 – Checkout => Pagantis
![Step 2](./woocommerce_configuration_2.png?raw=true "Step 2")

3 – Pagantis
![Step 3](./woocommerce_configuration_3.png?raw=true "Step 3")

## :clipboard: Options
In Paga+tarde admin panel, we can set the following options:

| Field &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;| Description<br/><br/>
| :------------- |:-------------| 
| Activate plugin   | - Clicked => Module enabled<br/> - Not clicked => Módule disabled (Default)
| Public Key(*) |  String you can get from your [Pagantis profile](https://bo.pagamastarde.com/shop).
| Secret Key(*) |  String you can get from your [Pagantis profile](https://bo.pagamastarde.com/shop). 
| Product Simulator    |  Choose if we want to use installments simulator inside product page.


## :clipboard: Advanced configuration:
The module has many configuration options you can set, but we recommend use it as is.

If you want to manage it, you have a way to update the values via HTTP, you only need to make a post to:

<strong>{your-domain-url}/?rest_route=/paylater/v1/configController/{your-secret-key}</strong>

sending in the form data the key of the config you want to change and the new value.


Here you have a complete list of configurations you can change and it's explanation. 


| Field | Description<br/><br/>
| :------------- |:-------------| 
| PMT_TITLE                           | Payment title to show in checkout page. By default:"Instant financing".
| PMT_SIMULATOR_DISPLAY_TYPE          | Installments simulator skin inside product page, in positive case. Recommended value: 'pmtSDK.simulator.types.SIMPLE'.
| PMT_SIMULATOR_DISPLAY_SKIN          | Skin of the product page simulator. Recommended value: 'pmtSDK.simulator.skins.BLUE'.
| PMT_SIMULATOR_DISPLAY_POSITION      | Choose the place where you want to watch the simulator.
| PMT_SIMULATOR_START_INSTALLMENTS    | Number of installments by default to use in simulator.
| PMT_SIMULATOR_DISPLAY_CSS_POSITION  | he position where the simulator widget will be injected. Recommended value: 'pmtSDK.simulator.positions.INNER'.
| PMT_SIMULATOR_CSS_PRICE_SELECTOR    | CSS selector with DOM element having totalAmount value.
| PMT_SIMULATOR_CSS_POSITION_SELECTOR | CSS Selector to inject the widget. (Example: '#simulator', '.PmtSimulator')
| PMT_SIMULATOR_CSS_QUANTITY_SELECTOR | CSS selector with DOM element having the quantity selector value.
| PMT_FORM_DISPLAY_TYPE               | Allow you to select the way to show the payment form in your site
| PMT_DISPLAY_MIN_AMOUNT              | Minimum amount to use the module and show the payment method in the checkout page.
| PMT_URL_OK                          | Location where user will be redirected after a successful payment. This string will be concatenated to the base url to build the full url
| PMT_URL_KO                          | Location where user will be redirected after a wrong payment. This string will be concatenated to the base url to build the full url 
