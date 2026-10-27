# Installation, upgrades and retained financial data

This development branch is restricted to a reviewed local validation environment. It does not authorize production cutover. The public plugin version remains 4.8.5.

## Fresh installations and official-package updates

Use the official folder and main file `nodevora-customer-rewards-for-woocommerce/nodevora-customer-rewards-for-woocommerce.php`. Existing option, user, product, comment and order metadata identifiers remain unchanged. Activation does not migrate accounts or install a second financial authority. Before verified wallet setup, historical balances remain readable, financial mutations are refused and a loyalty discount cannot pass checkout. Ordinary cash checkout remains available.

An administrator must preserve a full database backup, stop incoming financial writers and payment attempts, inspect the legacy migration report, install/verify the schema and explicitly migrate eligible accounts using the protected local tooling in `tests/financial/README.md`. Consistent accounts retain opening balances, lots, expiry provenance and prior bonus identities. Accounts without reliable lots, conflicting records, ambiguous orders or interrupted evidence remain frozen for reconciliation. Never fabricate expired value, historical debits or restoration claims. Never drain an unknown recovery queue.

After migration, wallet authority is durable. A wallet/database outage cannot select legacy writes as a fallback. New earning, bonuses, debit and dispatch stay paused until readiness is deliberately verified. Reconciliation and previously committed recovery can continue while new rewards or the programme are disabled. Only locally verified BACS, COD and zero-total flows are supported. Unsupported gateways and altered/ambiguous orders remain restricted.

## Historical Rewardly packages

The historical identity `rewardly-loyalty/rewardly-loyalty.php` does not receive an automatic folder rename or activation bridge from this package. On staging: export the database and archive the old package; stop checkout/writers; deactivate the old package **without uninstalling it**; install the official package; confirm only the official package is active; compare settings, user balances/lots/history, review claims and WooCommerce order metadata against the backup; inspect/migrate with the same conservative contract above. Preserve the existing cron hook identity. Remove the inactive package only after independent validation and retained backups. No GitHub updater is installed.

The official package refuses its runtime when the known historical package is active or another Rewardly/Nodevora runtime is already loaded. This does not rewrite another vendor package. Other historical folder names require the same single-active-package procedure. Historical 3.0.1/4.8.4 end-to-end updates and WordPress.org update recognition still require independent staging verification.

## Moderation and partial refunds

An eligible registered customer's top-level product review can claim one reward after approval. Replies and unapproved reviews cannot earn. Unapproval, spam, trash or deletion reverse a known wallet credit once; debt remains non-spendable. Reapproval never creates a second entitlement. Imported legacy claims remain permanent identities; no unproven historic debt is invented. Pending moderation/reconciliation is retained when a wallet is unavailable.

Earning retains the existing gross, pre-coupon product-subtotal policy. A partial refund creates a durable decision keyed by the actual WooCommerce refund ID and a reconciliation warning/fulfillment hold; it does not invent a proportional points policy. Full refunds use the original award and debit references. Commercial proportional-refund policy and any restoration grace period remain explicit product decisions; original lot expiry is retained.

## Single site and uninstall

Financial operations support single-site WordPress. Network activation and cross-network financial migration are unsupported; activation refuses multisite and uninstall performs no network cleanup. No multisite certification is claimed.

Uninstall retains every customer balance, lot, transaction history, wallet/journal/allocation, snapshot, order claim and authority marker. The optional cleanup setting removes only plugin preferences and installed-version metadata on a single site. Financial erasure requires a separate reviewed export/reconciliation procedure. Reinstallation must inspect retained authority and migration state rather than creating a second opening balance.

## Roles

Administrators, shop managers and custom roles explicitly granted `manage_woocommerce` can save loyalty settings and request audited adjustments. Customers and subscribers cannot. Adjustments require POST, a valid nonce, an existing customer, bounded positive whole points and a stable operation UUID. Repeated financial operation identities cannot create repeated effects. Migration/schema tools additionally require the explicit local CLI gates described in the financial test documentation.
