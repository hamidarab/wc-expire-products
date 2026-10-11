=== Product Expiration Easy Peasy ===
Contributors: hamidarab
Tags: woocommerce, product, expiration, stock, jalali
Requires at least: 6.6
Tested up to: 7.0
Requires PHP: 8.1
Requires Plugins: woocommerce
WC requires at least: 9.0
WC tested up to: 9.7
Stable tag: 3.3.0
License: GPL v3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Manage WooCommerce expiration dates, automatically update stock, notify store staff, and display built-in Jalali dates.

== Description ==

Product Expiration Easy Peasy lets store managers assign an expiration date to simple products and individual variations. Persian administration screens accept Jalali dates and Persian digits, while other locales accept Gregorian dates. The plugin can display the date on product, cart, account, order, and email views. Values are stored as canonical Gregorian dates for reliable queries, and Persian WordPress installations display them through the bundled Jalali converter; no calendar plugin is required.

A daily, locked background task finds products that expire within the configured period. Eligible in-stock or backordered products are changed through the WooCommerce CRUD API, so product lookup tables, caches, hooks, and variable-parent stock status remain synchronized. The previous stock status is recorded and restored if a manager clears the date or moves it beyond the configured cutoff. Notifications can be sent to the site administrator, every Shop Manager, and an optional custom address.

Features include:

* Validated expiration fields for simple products and variations.
* Quick Edit support for supported product types.
* Working Expired, Not Expired, and No Date list filters.
* Sortable expiration column in the product list.
* Configurable display after the product title or price.
* Purchase-time expiration snapshots for historical orders.
* HTML and plain-text order email output.
* Built-in Gregorian-to-Jalali conversion for Persian locales.
* Jalali administration input with Persian- and Arabic-digit normalization.
* Daily batch processing with an overlap lock.
* WooCommerce CRUD stock updates and variable-parent synchronization.
* RTL-Theme license integration that fails safely instead of breaking wp-admin.
* WooCommerce High-Performance Order Storage compatibility declaration.

The distributed RTL-Theme license runtime is protected with ionCube. The readable bootstrap validates the runtime path and integrity before loading it. A missing loader, missing SDK file, damaged SDK, inactive license, or license exception results in an administrator notice rather than a PHP fatal error.

== Installation ==

1. Confirm that PHP 8.1 or newer and the matching ionCube Loader are enabled.
2. Install and activate WooCommerce 9.0 or newer.
3. Upload the directly installable plugin ZIP from Plugins > Add New > Upload Plugin.
4. Activate Product Expiration Easy Peasy.
5. Complete RTL-Theme license activation when prompted.
6. Open WooCommerce > Expiration, review each tab, and use "Send test email" to confirm notifications arrive.
7. Edit a product or variation and enter the date as Jalali `Y-m-d` on Persian administration screens or Gregorian `Y-m-d` on other locales.

== Frequently Asked Questions ==

= Does the plugin support variable products? =

Yes. Every variation can have its own validated expiration date. The product page updates the visible date when the customer selects a variation.

= Do I need a separate Persian calendar plugin? =

No. Persian locales use the bundled Jalali converter automatically. Dates remain stored in the standard Gregorian `Y-m-d` format for reliable comparisons and integrations.

= Can I enter the date in the Jalali calendar? =

Yes. On Persian administration screens, enter a Jalali value such as `۱۴۰۵-۰۱-۰۱`; Persian, Arabic, and Latin digits are accepted. The plugin validates and stores the equivalent Gregorian date internally.

= When is a product set to out of stock? =

The daily task calculates a cutoff from the configured number of days, weeks, or months. An in-stock or backordered product with an expiration date on or before that cutoff is set to out of stock.

= What happens when I remove or extend the expiration date? =

If this plugin previously changed the stock status, it restores the recorded status when the date is cleared or moved beyond the cutoff.

= Is order history affected when a product date changes later? =

New orders store a hidden purchase-time snapshot. Existing orders created before version 3.2.0 fall back to the product's current date when available.

= Can I change the colours of the expiration badge? =

Yes. The badge follows your theme's text colour by default. Override it from your theme or Appearance > Customize > Additional CSS:

`:root { --peep-badge-color: #065f46; --peep-badge-bg: #e7f7ef; --peep-badge-border: #a7d7c0; }`

= What data is sent externally? =

The expiration feature itself sends no telemetry. The generated RTL-Theme license runtime may communicate with RTL-Theme to validate the license and registered domain. See `PRIVACY.md` and the RTL-Theme terms supplied with the purchase.

== Changelog ==

= 3.3.0 =

* Redesigned settings page: tabs (Automation rules, Storefront display, Notifications, Help & status), live cutoff and badge previews, switches, date-format and position cards, and a sticky save bar with unsaved-changes warning.
* Added status cards: dated products, expired products, products set out of stock by the plugin, and the next scheduled check.
* Added "Run check now" and "Send test email" actions.
* Added inline validation for the custom email address and a warning when no recipient is selected.
* Product list: Expired, Near expiry, and Not expired badges, plus an "Auto out of stock" label.
* Jalali date fields are validated as you type, show the readable date, and block saving invalid values (including Quick Edit).
* The storefront badge uses CSS custom properties with neutral defaults so it fits more themes.
* Fixed: one notification email per daily run instead of one per 50 products.
* Fixed: shortening the notification period now restores stock for products no longer inside the window.
* Fixed: report edit links for variations open the parent product.

= 3.2.0 =

* Fixed the undefined license helper fatal and corrected the SDK path.
* Kept the WordPress plugin header and bootstrap readable.
* Added safe handling for missing ionCube, missing or damaged SDK files, and license exceptions.
* Added activation and deactivation lifecycle hooks for the daily cron event.
* Added a cron overlap lock, bounded batches, validated date intervals, and site-timezone calculations.
* Switched stock changes to WooCommerce CRUD and synchronized variable parents.
* Added previous-stock restoration when managed expiration dates are cleared or extended.
* Fixed custom and Shop Manager email recipients.
* Fixed product-list filters and expiration sorting.
* Added strict settings and calendar-date validation, capability checks, nonce checks, clearing support, and late escaping.
* Implemented the previously offered After Title display position.
* Added built-in Jalali conversion with Persian digits.
* Added validated Jalali date entry on Persian administration screens.
* Added purchase-time order-item snapshots and plain-text email output.
* Limited admin and storefront assets to relevant screens.
* Added deterministic uninstall cleanup and release tooling.

= 3.1.1 =

* Fixed variation expiration saving when the variation form nonce was missing.

= 3.1.0 =

* Added variation-level expiration dates and Quick Edit improvements.
* Added configurable date formats and conditional asset loading.

== Upgrade Notice ==

= 3.3.0 =

Redesigned, accessible settings page with status cards and test actions. Fixes duplicate notification emails and stock not being restored after shortening the notification period. Settings are kept as they are.

= 3.2.0 =

This security and reliability release fixes a fatal license bootstrap error, repairs scheduled processing and list filters, and adds built-in Jalali formatting. Back up the site before updating and verify the RTL-Theme license after installation.

== Privacy ==

Product expiration values and settings are stored locally in WordPress. Notification emails are sent through the site's configured mail transport. The generated RTL-Theme license runtime may send license and domain information to RTL-Theme for activation and validation. The expiration feature does not add analytics or marketing telemetry.

Uninstall restores stock statuses previously managed by the plugin, removes product expiration metadata, removes hidden order-item snapshots, removes options and locks, and clears scheduled events.
