# Nodevora Customer Rewards for WooCommerce 4.8.6

WordPress-native loyalty points for WooCommerce. PHP 7.4, WordPress 6.4 and WooCommerce 8.2 minimums. One transactional points engine, WooCommerce CRUD/HPOS and native coupons serve Classic and Blocks checkout.

## Installation archive

Use `dist/nodevora-customer-rewards-for-woocommerce-4.8.6.zip`. It contains the official `nodevora-customer-rewards-for-woocommerce/` root. GitHub’s automatically generated repository source archives have a repository wrapper and are not the WordPress installation artifact.

## Existing stores: publication gate

This release uses new financial tables and is intended for fresh installations. It does not migrate earlier Nodevora balances, history or backups. Do not enable automatic updates from an earlier financial architecture. Existing storage remains unchanged; activation refuses legacy storage and uninitialized upgrades do not register loyalty mutations. Retain the matching earlier plugin for existing stores until a separately reviewed rollout is available.

## Validation

Plugin Check 2.1.0 completes with zero errors and warnings using documented, narrowly scoped PHPCS exceptions for fresh financial SQL and pending-award metadata lookups. The raw corrected pre-annotation scan contains 73 warnings and zero errors; exceptions do not change transaction behavior. Integration validation uses WordPress 7.1.4, PHP 8.3.36, WooCommerce 11.2.1 and HPOS. The minimum-version combination, persistent cache services and additional payment gateways require separate validation.

My Account, six historical shortcodes, design presets, customer adjustments, notifications, backup preview/confirmation and native WooCommerce coupon flows are retained. Partial refunds require an explicit administrator decision. Backups cover Nodevora only and require matching site/account/order state.

## Repository and distribution

Runtime is at the repository root. Translation PO/POT sources are retained for maintainers; the installation ZIP includes compiled MO/JSON only. Marketing artwork belongs in WordPress.org SVN’s separate assets directory. No WordPress.org SVN deployment is performed by this repository update.
