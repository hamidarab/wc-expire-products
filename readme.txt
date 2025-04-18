=== Product Expiration Easy Peasy ===
Contributors: hamidarab
Tags: woocommerce, product expiration, inventory management, out of stock, persian, jalali, calendar
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.2
WC requires at least: 3.0
WC tested up to: 8.0
Stable tag: 3.0.0
License: GPL v3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Donate link: https://zarinp.al/689844

Manage product expiration with Persian calendar support. Auto mark products as "Out of Stock" before expiry.

== Description ==
Persian WC Product Expiration allows store owners to set expiration dates for products and automatically update stock status when they are near expiration.

**Features:**
- ✅ Add an expiration date to products via the product edit page or Quick Edit.
- ✅ Display the expiration date on the product page.
- ✅ Automatically set products to "Out of Stock" two months before expiration.
- ✅ Send email notifications to administrators and shop managers.
- ✅ Full support for the Persian calendar when `jdate()` is available.
- ✅ Compatible with WooCommerce.

🗓️ **Persian Calendar Support**  
If the `jdate()` function is available (e.g., by using the WP-Parsidate plugin), the expiration dates will be displayed using the Persian (Jalali) calendar. Otherwise, it will default to the Gregorian calendar.

== Installation ==
1. Upload the plugin folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Edit a product and set the expiration date under **Product Data → General**.
4. (Optional) Use Quick Edit in the product list to modify the expiration date quickly.

🗓️ **To enable Persian date format:**  
Install and activate a plugin that provides the `jdate()` function, such as [WP-Parsidate](https://wordpress.org/plugins/wp-parsidate/). The expiration date will then be shown in the Persian (Jalali) calendar format.

== Frequently Asked Questions ==

= Does this plugin work with variable products? =
Yes, expiration dates can be set for individual variations.

= Can I customize the expiration date format? =
Yes, you can choose from different formats (Y/m/d, Y/m, Ym, etc.).

= Is the Persian calendar supported? =
Yes! If the `jdate()` function is available (e.g., via WP-Parsidate), expiration dates will automatically appear in the Persian calendar format. Otherwise, they will use the default Gregorian format.

== Screenshots ==
1. Product edit page showing expiration date field.
2. Example of an expired product marked "Out of Stock".
3. Email notification for expiring products.

== Changelog ==

= 3.0.0 =
- Fixed: Optimized query to get expired products for better performance.

= 2.10.0 =
- Added: Configurable date format options (Y/m/d, Y/m, Ym, etc.).
- Added: Custom styling for expiration dates with `expiration-date` class.
- Fixed: Persian language translation issues.
- Added: Support for Persian calendar when `jdate` is available.
- Improved: Expiration date styling in product pages, cart, and order emails.

= 1.0.4 =
- Improved: Enhanced variation details in expiration notification emails.
- Optimized: Combined query for simple and variable products.

= 1.0.3 =
- Fixed: Products without expiration dates being incorrectly marked as out of stock.
- Added: Additional validation for expiration date format.

== Upgrade Notice ==
= 2.10.0 =
- This update improves the display of expiration dates and adds support for the Persian calendar.
