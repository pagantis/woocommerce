# Configuration

## :house: Access

To access to Pagantis admin panel, we need to open the Woocommerce admin panel and follow the next steps:

1 – Woocommerce => Ajustes/Settings
![Step 1](./woocommerce_configuration_1.png?raw=true "Step 1")

2 – Pagos/Payments => Pagantis
![Step 2](./woocommerce_configuration_2.png?raw=true "Step 2")

3 – Pagantis
![Step 3](./woocommerce_configuration_3.png?raw=true "Step 3")

## :clipboard: Options
In Pagantis admin panel, we can set the following options:

| Field &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;| Description<br/><br/>
| :------------- |:-------------| 
| Activate plugin   | - Clicked => Module enabled<br/> - Not clicked => Módule disabled (Default)
| Public Key(*) |  String you can get from your [Pagantis profile](https://bo.pagantis.com/shop).
| Secret Key(*) |  String you can get from your [Pagantis profile](https://bo.pagantis.com/shop). 
| Product Simulator    |  Choose if we want to use installments simulator inside product page.


## :clipboard: Advanced configuration:
The module has many configuration options you can set, but we recommend use it as is.

If you want to manage it, you have 2 ways to update the values [using database](./configuration.md#edit-using-database) or [via HTTP](./configuration.md#edit-using-postman), see below.

Here you have a complete list of configurations you can change and it's explanation. 


| Field | Description<br/><br/>
| :------------- |:-------------| 
| PAGANTIS_TITLE                           | Payment title to show in checkout page. By default:"Pago en cuotas".
| PAGANTIS_SIMULATOR_DISPLAY_TYPE          | Installments simulator skin inside product page, in positive case. Recommended value: 'pgSDK.simulator.types.SELECTABLE_TEXT_CUSTOM'.
| PAGANTIS_SIMULATOR_DISPLAY_SKIN          | Skin of the product page simulator. Recommended value: 'pgSDK.simulator.skins.BLUE'.
| PAGANTIS_SIMULATOR_DISPLAY_POSITION      | Choose the place where you want to watch the simulator.
| PAGANTIS_SIMULATOR_START_INSTALLMENTS    | Number of installments by default to use in simulator.
| PAGANTIS_SIMULATOR_MAX_INSTALLMENTS      | Number of maximum installments to use in simulator.
| PAGANTIS_SIMULATOR_DISPLAY_CSS_POSITION  | he position where the simulator widget will be injected. Recommended value: 'pgSDK.simulator.positions.INNER'.
| PAGANTIS_SIMULATOR_CSS_PRICE_SELECTOR    | CSS selector with DOM element having totalAmount value.
| PAGANTIS_SIMULATOR_CSS_POSITION_SELECTOR | CSS Selector to inject the widget. (Example: '#simulator', '.PgSimulator')
| PAGANTIS_SIMULATOR_CSS_QUANTITY_SELECTOR | CSS selector with DOM element having the quantity selector value.
| PAGANTIS_FORM_DISPLAY_TYPE               | Allow you to select the way to show the payment form in your site
| PAGANTIS_DISPLAY_MIN_AMOUNT              | Minimum amount to use the module and show the payment method in the checkout page.
| PAGANTIS_DISPLAY_MAX_AMOUNT              | Maximum amount to use the module and show the payment method in the checkout page.
| PAGANTIS_URL_OK                          | Location where user will be redirected after a successful payment. This string will be concatenated to the base url to build the full url
| PAGANTIS_URL_KO                          | Location where user will be redirected after a wrong payment. This string will be concatenated to the base url to build the full url  
| PAGANTIS_ALLOWED_COUNTRIES               | Array of country codes where the method can be used 
| PAGANTIS_SIMULATOR_DISPLAY_SITUATION     | Place to move the text simulator. To disable set to: "default"
| PAGANTIS_SIMULATOR_SELECTOR_VARIATION    | Selector to use for products with variations. To disable set to: "default"

##### Edit using database
1 - Open your database management (Frequently Cpanel->phpmyadmin) 

2 - Connect to wordpress database. (Frequently called wordpress)

3 - Launch a query to check if the table exists: select * from wp_pagantis_config
![Step 3](./sql_step3.png?raw=true "Step 1")

4 - Find the config field to edit, in this example we are going to edit: PAGANTIS_TITlE 

5 - Launch a query to edit their value: Update wp_pagantis_config set value='New title' where config='PAGANTIS_TITLE'
![Step 5](./sql_step5.png?raw=true "Step 5")

6 - After the modification, you can check it launching the query: "select * from wp_pagantis_config"
![Step 6](./sql_step6.png?raw=true "Step 6")

7 - Finally you can see the change in checkout page
![Step 7](./sql_step7.png?raw=true "Step 7")

##### Edit using postman

To modify the configuration you only need to make a post to:

<strong>{your-domain-url}/?rest_route=/pagantis/v1/configController/{your-secret-key}</strong>

Sending in the form data the key of the config you want to change and the new value.

1 - Open the application
![Step 1](./postman_step1.png?raw=true "Step 1")

2 - Set the mode of the request  
2.1 - Click on BODY tag  
2.2 - Click on x-www-form-urlencoded
![Step 2](./postman_step2.png?raw=true "Step 2")

3 - Set your request  
3.1 - On the upper-left side, you need to set a POST request   
3.2 - Fill the url field, setting your domain and your secret key (You can get in:http://bo.pagantis.com) 
3.3 - Set the config key to modify (see previous table) 
3.4 - Set the value for the selected key 
![Step 3](./postman_step3.png?raw=true "Step 3")

4 - Press on SEND
![Step 4](./postman_step4.png?raw=true "Step 4")

5 - If everything works fine, you could see the config 
![Step 5](./postman_step5.png?raw=true "Step 5")

6 - Finally you can see the change in checkout page
![Step 6](./sql_step7.png?raw=true "Step 6")
