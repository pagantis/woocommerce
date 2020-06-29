# Configuration

## :house: Access

To access to Pagantis admin panel, we need to open the Woocommerce admin panel and follow the next steps:

1. Woocommerce => Ajustes/Settings  
![Step 1](./woocommerce_configuration_1.png?raw=true "Step 1")

2. Pagos/Payments => Pagantis  
![Step 2](./woocommerce_configuration_2.png?raw=true "Step 2")

3. Pagantis  
![Step 3](./woocommerce_configuration_3.png?raw=true "Step 3")

## :clipboard: Options
In Pagantis admin panel, we can set the following options:

| Field &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;| Description<br/><br/>
| :------------- |:-------------| 
| Activate plugin   | * Checked => Module enabled<br/> * Not Checked => Module disabled (Default)
| Public Key(*) |  String.
| Secret Key(*) |  String. 
| Product Simulator  |  * Checked => Displays the installments simulator on the product page. (Default) <br/> * Not Checked => Does not display the simulator

:information_source: - Your keys are located on your [Pagantis profile](https://bo.pagantis.com/shop)

## :clipboard: Advanced configuration:
While we recommend to use the Pagantis module as is , you can customize some settings as shown below.

You have to ways to edit your settings:
* [Database queries](./configuration.md#edit-your-settings-using-database-queries)
* [HTTP requests](./configuration.md#edit-your-settings-using-postman)


##### List of settings and their description.

:information_source: - __Static__ values can not be edited. 

| Field | Description<br/><br/>
| :------------- |:-------------| 
| PAGANTIS_TITLE                           | Payment method title displayed on the checkout page. By default:"Pago en cuotas".
| PAGANTIS_SIMULATOR_DISPLAY_TYPE          | Installments simulator on the product page. Static value: 'pgSDK.simulator.types.PRODUCT_PAGE'.
| PAGANTIS_SIMULATOR_DISPLAY_SKIN          | Static value: 'pgSDK.simulator.skins.BLUE'.
| PAGANTIS_SIMULATOR_DISPLAY_POSITION      | Choose the place where you want to watch the simulator.
| PAGANTIS_SIMULATOR_START_INSTALLMENTS    | Number of installments by default to use in simulator.
| PAGANTIS_SIMULATOR_MAX_INSTALLMENTS      | Number of maximum installments to use in simulator.
| PAGANTIS_SIMULATOR_DISPLAY_CSS_POSITION  | The position where the simulator widget will be placed. Recommended value: 'pgSDK.simulator.positions.INNER'.
| PAGANTIS_SIMULATOR_CSS_PRICE_SELECTOR    | CSS selector of the DOM element having totalAmount value.
| PAGANTIS_SIMULATOR_CSS_POSITION_SELECTOR | CSS Selector to place the widget. (Example: '#simulator', '.PgSimulator')
| PAGANTIS_SIMULATOR_CSS_QUANTITY_SELECTOR | CSS selector of the DOM element having the quantity selector value.
| PAGANTIS_FORM_DISPLAY_TYPE               | Allows you to select the way to show the payment form in your site
| PAGANTIS_DISPLAY_MIN_AMOUNT              | Minimum price to use the module and show the payment method on the checkout page.
| PAGANTIS_DISPLAY_MAX_AMOUNT              | Maximum price to use the module and show the payment method on the checkout page.
| PAGANTIS_URL_OK                          | Location where user will be redirected after a successful payment. This string will be concatenated to the base url to build the full url
| PAGANTIS_URL_KO                          | Location where user will be redirected after a wrong payment. This string will be concatenated to the base url to build the full url  
| PAGANTIS_ALLOWED_COUNTRIES               | Array of country codes where Pagantis can be used 
| PAGANTIS_SIMULATOR_DISPLAY_SITUATION     | Place to move the text simulator. To disable set to: "default"
| PAGANTIS_SIMULATOR_SELECTOR_VARIATION    | Selector to use for products with variations. To disable set to: "default"

##### Edit your settings using database queries
1 - Open your database management (Commonly Cpanel->phpmyadmin depending on your hosting solution) 

2 - Connect to the wordpress database. (Frequently called wordpress)

3 - Launch a query to check if the table exists: 
  * Query: 
        ```
        SELECT * FROM wp_pagantis_config;
        ```

    ![Step 3](./sql_step3.png?raw=true "Step 1")

4 - Find the setting PAGANTIS_TITLE, in this example we are going to change 'Instant Financing' to 'New Title'  

5 - Launch the following query to edit the value:
  * Query: 
        ```
        UPDATE wp_pagantis_config set value='New title' WHERE config='PAGANTIS_TITLE';
        ```

   ![Step 5](./sql_step5.png?raw=true "Step 5")



6 - After the modification, you can verify it with the following query :
  * Query:
        ```
        SELECT * FROM wp_pagantis_config;
        ```
        
   ![Step 6](./sql_step6.png?raw=true "Step 6")


7 - Finally you can see the result on checkout page:

   ![Step 7](./sql_step7_.png?raw=true "Step 7")

##### Edit your settings using Postman

To modify the configuration you only need to make a post to:

> <strong>{your-domain-url}/?rest_route=/pagantis/v1/configController/{your-secret-key}</strong>

Sending in the form data the key of the option you want to change and the new value.

1. Open the application  
![Step 1](./postman_step1.png?raw=true "Step 1")

2. Set the mode of the request  
2.1 - Click on BODY tag  
2.2 - Click on x-www-form-urlencoded  
![Step 2](./postman_step2.png?raw=true "Step 2")

3. Set your request  
3.1 - On the upper-left side, you need to set a POST request    
3.2 - Fill the url field, setting your domain and your secret key which is located on your [Pagantis profile](https://bo.pagantis.com/shop).   
3.3 - Set the config key to modify. [List of config keys](./configuration.md#list-of-settings-and-their-description).  
3.4 - Set the value for the selected key   
![Step 3](./postman_step3.png?raw=true "Step 3")

4. Press SEND  
![Step 4](./postman_step4.png?raw=true "Step 4")

5. If everything works correctly, you should see the edited config as show below  
![Step 5](./postman_step5.png?raw=true "Step 5")

6. Finally you can see the change in checkout page:  
![Step 6](./postman_step6.png?raw=true "Step 6")
