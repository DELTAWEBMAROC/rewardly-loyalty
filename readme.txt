=== Nodevora Customer Rewards for WooCommerce ===
Contributors: ahmedxy
Tags: woocommerce, loyalty, reward points, points and rewards, loyalty program
Requires at least: 6.4
Tested up to: 7.1.4
Requires PHP: 7.4
Stable tag: 4.8.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create a WooCommerce loyalty program with reward points, redeemable discounts, expiration, customer history, emails, and Blocks support.

== Description ==

Nodevora Customer Rewards is a free WooCommerce loyalty points and rewards plugin built around the core workflow stores actually need: customers earn points from purchases, redeem points for discounts, and track their loyalty balance and history.

Run a focused WooCommerce loyalty program with points expiration, redemption controls, manual points adjustments, email notifications, shortcodes, and support for both classic WooCommerce cart and checkout and modern Cart and Checkout Blocks.

No complicated loyalty campaign builder is required. Configure your reward rules, start rewarding purchases, and let customers use their available points directly in the shopping flow.

= Why Choose Nodevora Customer Rewards? =

Nodevora Customer Rewards focuses on the essential purchase-to-reward workflow instead of turning a simple loyalty program into a complex marketing platform.

* Reward customers with points from eligible WooCommerce purchases
* Let customers redeem points for discounts
* Points expiration included
* Minimum redemption and maximum discount controls
* Customer points balance and history in My Account
* Manual points adjustments from the WordPress admin area
* Email notifications for loyalty activity
* Classic WooCommerce cart and checkout support
* WooCommerce Cart and Checkout Blocks support
* Shortcodes for flexible placement
* Clean and focused configuration

= WooCommerce Loyalty Points and Rewards =

Reward customers when they purchase from your WooCommerce store. Customers can collect loyalty points over time and redeem their available reward points for discounts on future orders.

The workflow stays focused:

1. A customer places an eligible WooCommerce order.
2. The customer earns reward points according to your configured rules.
3. Their points are added to their loyalty balance.
4. The customer can view their balance and points history.
5. Available points can be redeemed for discounts according to your redemption settings.

= Earn Reward Points from WooCommerce Orders =

Customers can earn loyalty points from eligible WooCommerce purchases.

Store owners can configure how points are earned and control when points are credited to the customer's balance.

= Redeem Points for WooCommerce Discounts =

Customers can use available loyalty points as discounts during the shopping flow.

Store owners can configure redemption rules including minimum points required before redemption and maximum discount limits.

This gives merchants control over how reward points affect order totals while keeping redemption simple for customers.

= Points Expiration =

Set an expiration period for loyalty points to encourage customers to return and use their available rewards.

Points expiration is included in Nodevora Customer Rewards and can be configured from the loyalty settings.

= Customer Points Balance and History =

Customers can view their current loyalty balance and points activity from their WooCommerce My Account area.

This gives customers a clear view of how their reward balance changes over time.

= Manual Points Adjustments =

Store administrators can manually add or remove customer points when needed.

Manual adjustments are useful for customer service, corrections, promotional rewards, or other store-specific situations.

= Email Notifications =

Keep customers informed when loyalty activity affects their points balance.

Email notifications help customers stay aware of their rewards and loyalty activity.

= WooCommerce Cart and Checkout Blocks Support =

Nodevora Customer Rewards supports modern WooCommerce Cart and Checkout Blocks as well as classic WooCommerce cart and checkout templates.

Customers can interact with their loyalty rewards without requiring stores to stay on legacy checkout layouts.

= Shortcodes =

The plugin includes shortcodes for displaying loyalty information manually in pages, templates, and compatible page builders.

This gives store owners additional flexibility when placing customer rewards information.

== Installation ==

This 4.8.6 package is an unpublished QA candidate. Use a backed-up disposable staging store first. Single-site WordPress and WooCommerce are required. Keep only one Rewardly/Nodevora package active.

1. Upload the ZIP using Plugins > Add New > Upload Plugin, install and activate. The official folder is nodevora-customer-rewards-for-woocommerce.
2. On an empty loyalty installation, Nodevora prepares and verifies its own tables automatically. Open Nodevora > General, configure the earn/redeem rules, then use Points Adjustments or a supported checkout. Existing WordPress users get empty wallets only when needed; no bulk migration is required for a genuinely empty installation.
3. If historical loyalty records are detected, Nodevora opens Synchronization (or shows a persistent notice). Stop previous loyalty tools and external loyalty processing before selecting Synchronize my data. Export a private Nodevora backup if desired. The bounded process preserves source records, resumes interrupted batches, rejects changed evidence and protects inconsistent accounts for review. Checkout is temporarily protected while synchronization runs; finish or resume before reopening purchases.
4. After synchronization, use Data & Backups for private Nodevora-only exports and compatible imports. Files contain personal data. Keep them outside public storage. Import requires an empty loyalty target with matching users, orders and metadata identities; newer wallet activity is never overwritten. Preview the exact file, confirm, then explicitly review restored data before enabling points. Only backup format 2, the same plugin version and schema are accepted. Limits: 32 MiB, 100,000 records and 1 MiB per record. This is not a WordPress backup or an account/order importer.
5. Simple mode shows daily essentials. Advanced mode is a per-user display preference and preserves saved rules. It adds detailed rules, scoped color overrides, permissions and administrator review controls. Design presets, WordPress color palettes, HEX inputs and the live component preview affect Nodevora only.
6. Administrators always retain access. Shop managers initially have their existing editing/adjustment rights. Administrators may authorize editors for read-only panel access or restrict the panel to administrators. Backup/import, synchronization, access policy and refund reconciliation remain administrator-only.

A database/schema failure or server safety pause blocks financial writes and shows an actionable notice. Retry preparation after the administrator resolves the cause; never switch to legacy writes. Already migrated accounts and immutable journals are retained on reinstall. Do not restore a pre-cutover database over later activity.

Inconsistent accounts retain their original evidence and cannot spend. There is no automated value-changing reconciliation policy for historical ambiguity. Partial refunds retain their fulfillment hold even when loyalty is disabled. An authorized administrator may record a matching external review in Advanced mode > Data & Backups > Review a partial refund. This creates a zero-value review, not a points adjustment or fulfillment approval. Later refunds require new review; no proportional-refund policy is invented.

Background recovery after synchronization is not approved automatically. An administrator must review its pending effects in Advanced mode; imported unknown queues stay paused. External writers and payment callbacks cannot be controlled by this plugin. After reviewed setup, restore external processing deliberately and verify staging checkout. Locally tested financial payment paths are BACS, COD and zero-total orders; arbitrary payment gateways and production cutover are not certified.

Declared minimums are not certifications. This candidate was developed on WordPress 7.1.4 / WooCommerce 11.2.1 / PHP 8.3; see the accompanying QA report for exact tested environments, limitations and independent QA scenarios. No official release or WordPress.org update has been published.

== Frequently Asked Questions ==

= Does Nodevora Customer Rewards require WooCommerce? =

Yes. Nodevora Customer Rewards requires WooCommerce to provide its loyalty points and rewards functionality.

= Is Nodevora Customer Rewards free? =

Yes. Nodevora Customer Rewards is free software released under the GPLv2 or later license.

= How do customers earn reward points? =

Customers earn loyalty points from eligible WooCommerce purchases according to the earning rules configured by the store administrator.

= Can customers redeem points for WooCommerce discounts? =

Yes. Customers can redeem available loyalty points for discounts according to the redemption rules configured by the store owner.

= Can I set a minimum number of points before customers can redeem them? =

Yes. Nodevora Customer Rewards includes controls for the minimum points required before redemption.

= Can I limit the maximum discount from loyalty points? =

Yes. Store owners can configure a maximum discount limit for points redemption.

= Does Nodevora Customer Rewards support points expiration? =

Yes. Store owners can configure an expiration period for customer loyalty points.

= Can administrators manually adjust customer points? =

Yes. Administrators can manually add or remove points from a customer's loyalty balance.

= Can customers see their points balance and history? =

Yes. Customers can view their loyalty balance and points activity from their WooCommerce My Account area.

= Does Nodevora Customer Rewards support WooCommerce Blocks? =

Yes. Nodevora Customer Rewards supports WooCommerce block-based Cart and Checkout flows as well as classic WooCommerce cart and checkout templates.

= Does the plugin include email notifications? =

Yes. The plugin can notify customers about loyalty activity that affects their points balance.

= Can I display loyalty information manually? =

Yes. Nodevora Customer Rewards includes shortcodes that can be used for flexible placement in pages, templates, and compatible page builders.

== Screenshots ==

1. Configure earning, redemption, expiration and notification settings.
2. Redeem loyalty points directly in cart or checkout.
3. Customer points balance and loyalty history in My Account.
4. Manual customer points adjustments from the WordPress admin area.
5. Points expiration and automatic email notifications.
6. WooCommerce classic and Cart/Checkout Blocks support.
7. Design controls and loyalty shortcodes.

== Changelog ==

= 4.8.6 — unpublished QA candidate =

* Automatic verified setup for empty stores and bounded resumable synchronization for existing data.
* Nodevora-only format-2 backup/import with preview, identity checks, atomic restore and review gates.
* Unified responsive administration, per-user Simple/Advanced modes, role restrictions and scoped live design editing.
* Financial journal, refund holds and idempotent funding protections retained. Independent QA and production approval remain required.

= 4.8.5 =

* Added official WooCommerce dependency and compatibility metadata for the 11.0 release line.
* Added HPOS and Cart/Checkout Blocks compatibility declarations for validation in QA.
* Fixed product-specific earning-rate overrides so the product setting is actually respected.
* Fixed loyalty email notifications so points activity can trigger customer emails when enabled.
* Improved points-history cache invalidation after balance changes.
* Reduced daily expiration work to customers with a positive loyalty balance.
* Updated visible Rewardly branding to Nodevora while preserving legacy internal identifiers for upgrade safety.
* Updated the default visual identity to Nodevora navy, teal and gold while preserving custom merchant colors.
* Improved refund/cancellation restoration log semantics and admin order notes.
* General compatibility and release hardening for WordPress 7.0 and WooCommerce 11.0.

= 4.8.4 =

* Production-ready free release
* WooCommerce Cart and Checkout Blocks support
* Points expiration support
* Customer points balance and history
* Manual points adjustments
* Email notifications
* Loyalty display controls and shortcodes
* General stability and compatibility improvements

== Upgrade Notice ==

= 4.8.5 =

Compatibility, reliability, branding and WooCommerce integration hardening. Test on a staging site before production deployment.

= 4.8.4 =

Maintenance, stability, and public release preparation update.
