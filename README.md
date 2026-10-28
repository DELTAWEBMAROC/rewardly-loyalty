# Nodevora Customer Rewards for WooCommerce

Development branch for the existing free WordPress.org plugin. Public metadata stays at 4.8.5; this branch is not a published release and does not authorize production cutover.

The transactional InnoDB wallet and immutable journal preserve financial identities, lot provenance and reconciliation evidence. Unconfigured installs retain read-only historical data and refuse loyalty mutations/discounts. Verified integration currently supports isolated single-site local BACS, COD and zero-total flows. Unsupported gateways, uncertain historic accounts and partial-refund policy remain restricted. Ordinary cash checkout remains available.

See [installation and readiness](docs/installation-and-financial-readiness.md) before activation, update or migration. A normal activation does not perform a production financial migration. The historical Rewardly folder requires the documented single-active-package staging procedure.

## Administration

General holds earning/redemption rules; Design holds scoped appearance settings; Points Adjustments requires `manage_woocommerce` and an audited operation identity. Shortcodes, Advanced Features and Help retain their existing navigation identities. Birthday/referral settings remain stored for compatibility but have no supported workflow and no misleading active controls.

Design supports primary/accent, button and hover foreground/background, card background/border, corner radius, spacing and three existing presets. Empty optional color overrides follow the preset. Theme CSS can override the scoped styles. No custom CSS injection or new frontend framework is used. Blocks use Store API extension data, the WooCommerce cart data store and registered inner blocks loaded through IntegrationInterface. Existing display settings control automatic placement; merchants do not need to rewrite saved checkout content. Each supported WooCommerce version still requires integration verification.

## Supported shortcodes

`[rewardly_points_balance]`, `[rewardly_points_value]`, `[rewardly_points_history limit="10"]`, `[rewardly_account_block]`, `[rewardly_loyalty_card]`, `[rewardly_loyalty_notice]`.

Card and notice accept `context="cart"` or `context="checkout"`. Existing account endpoint `rewardly-points` and persistent option/meta names are retained.

## Languages and verification

English source strings and a complete extracted French catalog are under `languages/`, including PO/MO and JavaScript JSON. Generate the POT using WP-CLI `i18n make-pot`, excluding development directories; compile with `msgfmt --check --check-format`, and generate JS catalogs using `wp i18n make-json --no-purge`. Merchant-authored text remains the merchant's content; the default campaign message is translated.

Financial regression tooling and operational gates: [tests/financial/README.md](tests/financial/README.md). Mission05 targeted regressions: `tests/remediation/`. Tests run only on the named isolated LocalWP site, never on production. PHP7.4 static compatibility does not replace unavailable minimum-runtime integration tests.

## Distribution and artwork

Runtime consists of the official main PHP file, `includes/`, `assets/`, `languages/`, `uninstall.php`, `readme.txt` and licensing files. `.distignore` excludes tests, developer documentation, tooling and repository-only metadata. Do not ship temporary fixtures, credentials, debug adapters or macOS files. WordPress.org marketing artwork belongs in the SVN repository's separate assets area; do not assume a `wordpress-org-assets/` directory or image files exist in this development checkout. SVN remains read-only during development missions.

Uninstall retains customer and financial evidence. Optional single-site cleanup removes preferences only; network cleanup is refused.
