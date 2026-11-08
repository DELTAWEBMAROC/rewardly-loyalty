=== Nodevora Customer Rewards for WooCommerce ===
Contributors: ahmedxy
Tags: woocommerce, loyalty, reward points, points and rewards, loyalty program
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 4.8.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Reward repeat purchases with points customers can use for WooCommerce discounts.

== Description ==
Nodevora lets customers earn points on eligible WooCommerce purchases, use available points for discounts, and view their wallet and history in My Account.

Configure earning and redemption rates, minimum points, a discount cap, expiration, product/category rules, registration/first-order/review bonuses, notifications, access permissions and appearance. Classic cart/checkout and Cart/Checkout Blocks use one points engine and native WooCommerce coupons. Six shortcodes provide flexible placement.

IMPORTANT: This release uses new financial tables and requires a fresh installation. It does not migrate balances, history or financial backups from earlier Nodevora versions. Do not enable automatic updates from an earlier financial architecture. Existing stores must retain their matching earlier version until a separately reviewed deployment plan is available. Loyalty operations remain disabled when the new store has not been initialized; activation refuses detected legacy storage. Earlier records are never converted or deleted.

Failed payments retain the points reservation for a retry of the same order. Cancel the order to release it. Cancelled rewards orders cannot be reopened for payment. Partial refunds require an administrator points decision. Expired points are not revived on cancellation. Reversed rewards already spent create a visible debt settled by future earnings.

Backups contain personal financial history. Store them privately. Limits: 8 MiB and 20,000 rows per table. Restore requires empty Nodevora financial tables on the same site, matching customers and the same WooCommerce order state. Pause checkout and order changes during backup/restore. The backup is not a complete WordPress or WooCommerce backup. Only the current nodevora-v2-2 archive with presentation preferences is accepted.

Logged-in pages, My Account, cart and checkout must be excluded from full-page caches at the cache layer. Nodevora emits no-cache headers and DONOTCACHEPAGE for logged-in frontend responses. Display caching never authorizes a debit.

The shared WordPress database connection must be idle with autocommit enabled. Nodevora refuses pre-existing transactions. Multisite and custom database-routing drop-ins are not supported. Uninstall preserves customer points and financial evidence; optional cleanup removes preferences only.

== Installation ==
1. Back up the site and confirm it is a fresh Nodevora installation with no earlier loyalty data.
2. Install this archive and activate with WooCommerce active.
3. Open Nodevora Rewards and configure points and appearance.
4. Open My Account > My Points to review the wallet.

== Frequently Asked Questions ==
= Which shortcodes are available? =
[rewardly_points_balance], [rewardly_points_value], [rewardly_points_history], [rewardly_account_block], [rewardly_loyalty_notice], [rewardly_loyalty_card].

= Which versions are required? =
WordPress 6.4, PHP 7.4 and WooCommerce 8.2. Integration validation was performed on WordPress 7.1.4, PHP 8.3.36 and WooCommerce 11.2.1. Other combinations require independent validation.

== Upgrade Notice ==
= 4.8.6 =
Fresh installations only. New financial storage does not migrate earlier balances or history. Do not automatically update an existing loyalty store; retain its matching plugin and data.

== Changelog ==
= 4.8.6 =
* Native WordPress database access with a shared transactional points engine and idempotent journal.
* WooCommerce coupons, HPOS, Classic and Blocks checkout support.
* Restored Nodevora administration, frontend, notifications and appearance controls.
* Verified cache invalidation, backup preview/confirmation and English/French translations.
* Preserve the original database isolation default after COMMIT and refuse uninitialized legacy cutovers.
* Notify confirmed durable points events even when the COMMIT acknowledgement is lost.
