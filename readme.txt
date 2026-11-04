=== Nodevora Customer Rewards for WooCommerce ===
Contributors: ahmedxy
Tags: woocommerce, loyalty, reward points, points and rewards, loyalty program
Requires at least: 6.4
Tested up to: 7.1.4
Requires PHP: 7.4
Stable tag: 4.8.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Reward repeat purchases with points customers can use for WooCommerce discounts.

== Description ==

Give customers a reason to come back. Nodevora lets customers earn points on eligible purchases and use available points for discounts in your WooCommerce store.

Set your earning and redemption rules, choose your colors, and manage the program from one clear administration panel. Customers can see their points and history in My Account.

= Features =

* Points for eligible purchases and discounts using available points.
* Minimum redemption, maximum discount and points expiration settings.
* Customer balance and points history in My Account.
* Manual points adjustments with a note for your records.
* Simple mode for daily tasks and Advanced mode for detailed settings.
* Color presets, HEX controls and a live preview for Nodevora components.
* Classic Cart and Checkout, and WooCommerce Cart and Checkout Blocks.
* Shortcodes for placing loyalty information on your pages.
* Optional points activity emails.
* Guided synchronization for existing stores, and Nodevora data backups.

= Safe updates and backups =

Back up your full site before updating and test on a staging copy. Fresh stores are prepared on activation. If earlier loyalty data is found, follow the guided Synchronization page before enabling points. Stop previous loyalty tools and external processing first; accounts needing review remain protected. Checkout may be unavailable during synchronization.

Data & Backups exports Nodevora data only, not WordPress, customers or orders. Keep exports private. Restore requires an empty Nodevora destination with matching customers and orders, the same plugin version and compatible backup format. Existing loyalty activity is never overwritten. Preview the file, confirm the import, then review the restored data before enabling points. Limits: 32 MiB and 100,000 records.

A site administrator must resolve a safety pause before points can resume. Pending background tasks require a separate review. For partial refunds, automatic order fulfillment remains on hold until an administrator records a review; Nodevora does not apply an automatic proportional points refund.

Classic and Blocks checkout have been tested with offline BACS, cash on delivery and zero-total orders. Test your own theme and payment gateway before going live. Multisite and all third-party gateway combinations are not certified.

== Installation ==

1. Install and activate WooCommerce.
2. Install Nodevora Customer Rewards for WooCommerce and activate it.
3. Open Nodevora, enable the loyalty program and set your earning and redemption rules.
4. Choose your colors in Design, test a purchase, and start rewarding customers.

Updating an existing store? Take a full backup first. If Synchronization appears, complete its guided steps before reopening checkout. Deactivate any older Rewardly copy without deleting its data, then use the official Nodevora plugin folder. Reinstallation preserves loyalty data by default.

== Frequently Asked Questions ==

= How do customers earn points? =

Customers earn points when eligible purchases reach the order status selected in your settings. You control the earning rate and exclusions.

= How can customers use points? =

Signed-in customers can apply available points in the cart or checkout. Your minimum redemption and discount limits apply.

= Can I change the colors? =

Yes. Open Design to choose a preset or your own colors and preview the result. These settings affect Nodevora components only.

= Where can customers see their points history? =

Their balance and history appear in WooCommerce My Account. You can also place loyalty information using the shortcodes listed in Nodevora.

= Can I adjust points manually? =

Yes. Authorized staff can add or remove points in Adjustments and include a note.

= How do I update without losing points? =

Back up your full site, test the update on a staging copy, then update the existing official plugin folder. Follow Synchronization if prompted. Never restore an older database over newer loyalty activity.

= Does a Nodevora backup include my entire store? =

No. It includes Nodevora data only. Keep a separate full WordPress backup. A restore cannot create missing customers or orders or overwrite newer points activity.

= Who can manage the program? =

Administrators retain full access. Shop managers have their permitted daily controls unless access is restricted. Synchronization, backup restore and sensitive reviews are administrator-only.

== Screenshots ==

1. Loyalty program settings in Simple mode.
2. Design colors, presets and preview.
3. Customer points and history in My Account.
4. Manual points adjustments.
5. Points redemption in Classic cart and checkout.
6. Points redemption in Cart and Checkout Blocks.

== Changelog ==

= 4.8.6 =

* Simplified fresh installation and guided synchronization for existing stores.
* Added Nodevora data backup, import preview and reviewed restoration.
* Unified the administration panel with Simple and Advanced modes.
* Added color presets, HEX controls and a live design preview.
* Improved access controls, points safety and partial-refund review protections.
* Fixed repeated backup restoration and settings save feedback.
* Clarified safety pauses and improved English and French messages.
* Improved stability and the mobile administration experience.

= 4.8.5 =

* Added official WooCommerce dependency and compatibility metadata.
* Fixed product earning overrides, points activity emails and history refresh.
* Updated Nodevora branding while preserving existing loyalty data.
* Improved refund notes, expiration processing and WooCommerce integration.

= 4.8.4 =

* Added Cart and Checkout Blocks support, points expiration, history, adjustments and email notifications.

== Upgrade Notice ==

= 4.8.6 =

Back up your site and test on staging. Follow Synchronization if prompted. Newer loyalty activity cannot be replaced by an older backup.

= 4.8.5 =

Compatibility and reliability improvements. Test on staging before updating your live store.
