=== Nodevora Customer Rewards for WooCommerce ===
Contributors: ahmedxy
Tags: woocommerce, loyalty, reward points, points and rewards, loyalty program
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 4.8.5
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

This development package requires verified financial setup. Activation does not migrate customer accounts or enable financial writes. Keep existing points and historical records; never uninstall the previous package to perform migration. Only one Nodevora/Rewardly package may be active.

1. Install the official nodevora-customer-rewards-for-woocommerce folder with WooCommerce active. Financial operations currently support single-site stores only.
2. As an administrator, open WooCommerce > Financial setup. Shop managers must ask an administrator for financial setup and refund resolution.
3. Export the full database and plugin files outside the web root and test restoring them on an isolated copy. Enter only a non-secret backup reference. Enable store maintenance; stop external payment callbacks and legacy loyalty workers; wait for in-flight requests to finish. Confirm both attestations and select Prepare and pause.
4. Select Install / verify schema, then Inspect next 20 accounts. Review balances, lot totals and diagnostic reasons before Migrate reviewed batch. Repeat until an empty inspection confirms discovery is complete. Changed evidence refuses migration. Keep maintenance enabled while investigating; retry interrupted batches with the same reviewed evidence. Already committed opening identities cannot import twice.
5. Inconsistent accounts retain snapshots and remain blocked. Do not invent missing lots, expiry dates, historical debits or a proportional-refund policy. A separately approved reconciliation plan is required before changing quarantined financial value. Activation of ready accounts does not resolve quarantine.
6. Explicitly confirm quarantine and wallet cutover before Activate reviewed wallets. Server pause/deny overrides take precedence; this page never changes server configuration. Background recovery needs its own confirmation after review of pending effects; otherwise leave it unchecked. Never drain unknown effects.
7. Verify a supported offline checkout on staging before deliberately restoring maintenance and callbacks. Locally verified financial payment paths are BACS, COD and zero-total orders. Arbitrary payment gateways and production cutover are not certified by this development package.

Pause new financial writes preserves wallet authority and history. Resume requires the activation confirmations again. Before cutover, rollback requires stopped writers and a complete consistent restore. After committed wallet activity, pause and reconcile; never switch back to legacy authority or restore a pre-cutover database.

A partial refund holds fulfillment through delayed status/payment/recovery callbacks even if loyalty is disabled. After an authorized external review, an administrator can record the matching order/refund IDs and a decision/reference under Financial setup > Resolve a reviewed partial refund. Resolution records a zero-value immutable review; it does not adjust points or advance the order. A later refund needs a new review.

Schema/database errors, changed migration evidence, incomplete account discovery and server safety overrides must be resolved while maintenance remains enabled. The interface displays errors and current progress. Normal activation never silently bypasses these requirements. The public version/stable tag remains 4.8.5; this development work is not a published release.

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
