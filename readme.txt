=== PagaMasTarde Payment Gateway for WooCommerce ===
Contributors: pgarcess
Tags: WooCommerce, Payment Gateway, PagaMasTarde, pagamastarde payment gateway, gateway for woocommerce, Card payment woocommerce, woocommerce financing
Requires at least: 2.0.1.3
Tested up to: 5.1.0
WC tested up to: 3.5.6
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Allows you to use PagaMasTarde financing payment gateway with the WooCommerce plugin.

== Description ==

This is PagaMasTarde financing payment gateway for WooCommerce.
Allows you to use PagaMasTarde financing payment gateway with the WooCommerce plugin.


== Installation ==

1. Ensure you have latest version of WooCommerce plugin installed
2. Unzip and upload contents of the plugin to your /wp-content/plugins/ directory
3. Activate the plugin through the 'Plugins' menu in WordPress


== Frequently Asked Questions ==

Do you have questions or issues with PagaMasTarde financing payment gateway? mail us on soporte@pagamastarde.com


== Screenshots ==

1. PagaMasTarde checkout page
2. PagaMasTarde WooCommerce configuration panel

== Configuration ==

1. Visit the WooCommerce settings page, and click on the Payment Gateways tab.
2. Click on "Pago con Paga Mas Tarde" to edit the settings. If you do not see "Pago con Paga Mas Tarde" in the list at the top of the screen make sure you have activated the plugin in the WordPress Plugin Manager.
3. Enable the Payment Method, and add the keys from your account (you can obtain it from  https://bo.pagantis.com/api). Click Save.

== Changelog ==

=======
= 7.2.1 =
- Adapt to Worpress 5.1
- Adapt to Woocommerce 3.5.6

= 7.2.0 =
- Adapt to orders
- Product simulator using js
- Iframe using Paylater modal launch
- Extra configuration parameters
- Exceptions using module-utils library

= 6.2.4 =
- SeleniumHelper
- Docker extended domain
- Simulator reload
- Redirect according order status

= 6.2.3 =
- Ready to marketplace
- Updated readme file
- Using actions for loading script
- Panel links

= 6.2.2 =
- Add module version to metadata.
- Remove "100%online" from default checkout title.
- Adapted product simulator to get all price format.
- New field result_description for notifications.

= 6.2.1 =
- Selenium update
- Modify default simulator: Mini
- Simulator default quotes: 3

= 6.2.0 =
- Initial release.
- Refactored module.
- Added selenium complete test using Travis.
- Added build status with travis.
- Complete functional test developed including install, register, buy.
- Product & Checkout simulator.
- Price modifier attribute, simulator reload.
