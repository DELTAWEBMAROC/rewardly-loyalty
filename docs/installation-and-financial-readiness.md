# Installation, upgrades and retained financial data

This development branch is restricted to a reviewed local validation environment. It does not authorize production cutover. The public plugin version remains 4.8.5.

## Fresh installations and official-package updates

Use the official folder and main file `nodevora-customer-rewards-for-woocommerce/nodevora-customer-rewards-for-woocommerce.php`. Existing option, user, product, comment and order metadata identifiers remain unchanged. Activation does not migrate accounts or install a second financial authority. Before verified wallet setup, historical balances remain readable, financial mutations are refused and a loyalty discount cannot pass checkout. Ordinary cash checkout remains available.

Use **WooCommerce → Financial setup** as an administrator (`manage_options`). The supported interface requires POST and a valid nonce for every action; opening the page changes no financial state. The local tooling in `tests/financial/README.md` is for disposable regression fixtures, not the merchant setup path.

1. Export the complete database and plugin files outside the web root; verify a restore in an isolated copy. Record only a non-secret backup reference.
2. Enable store maintenance, stop external payment callbacks and legacy loyalty workers, and drain in-flight requests. The two confirmations are operator attestations; the page cannot stop external services. Select **Prepare and pause**. Classic and Blocks checkout are blocked during preparation/migration.
3. Select **Install / verify schema**, then **Inspect next 20 accounts**. Review each balance, lot total and reason. Select **Migrate reviewed batch**. Repeat inspection/migration until an empty inspection confirms discovery is complete. A changed checksum refuses migration; keep writers stopped, investigate, then inspect again only after the evidence is understood. An interrupted batch can be retried; committed opening identities are not imported twice.
4. Review quarantined accounts against the retained backup/snapshots. Inconsistent accounts remain blocked, including their earning and spending. No value-changing historical reconciliation policy is approved or provided: retain evidence and obtain an approved reconciliation plan before any such correction. Ready accounts can be activated while quarantine remains blocked; activation does not certify or resolve those accounts.
5. Explicitly confirm quarantine and cutover, then select **Activate reviewed wallets**. Server pause/deny constants take precedence and this interface never rewrites server configuration. Background recovery requires its own separate confirmation; leave it unchecked unless pending effects have been reviewed. Verify a supported offline checkout before deliberately restoring maintenance and callbacks.
6. **Pause new financial writes** retains durable wallet authority, committed history and recovery identities. Resume requires the explicit activation confirmations again. Before cutover, rollback requires stopped writers and a complete consistent restore. After committed wallet activity, pause and reconcile; never restore an old pre-cutover database or switch authority back to legacy.

Schema errors, database permissions, stale evidence and incomplete discovery prevent activation. Keep the store in maintenance while resolving them; do not bypass the gates.  Consistent accounts retain opening balances, lots, expiry provenance and prior bonus identities. Accounts without reliable lots, conflicting records, ambiguous orders or interrupted evidence remain frozen for reconciliation. Never fabricate expired value, historical debits or restoration claims. Never drain an unknown recovery queue.

After migration, wallet authority is durable. A wallet/database outage cannot select legacy writes as a fallback. New earning, bonuses, debit and dispatch stay paused until readiness is deliberately verified. Reconciliation and previously committed recovery can continue while new rewards or the programme are disabled. Only locally verified BACS, COD and zero-total flows are supported. Unsupported gateways and altered/ambiguous orders remain restricted.

## Historical Rewardly packages

The historical identity `rewardly-loyalty/rewardly-loyalty.php` does not receive an automatic folder rename or activation bridge from this package. On staging: export the database and archive the old package; stop checkout/writers; deactivate the old package **without uninstalling it**; install the official package; confirm only the official package is active; compare settings, user balances/lots/history, review claims and WooCommerce order metadata against the backup; inspect/migrate with the same conservative contract above. Preserve the existing cron hook identity. Remove the inactive package only after independent validation and retained backups. No GitHub updater is installed.

The official package refuses its runtime when the known historical package is active or another Rewardly/Nodevora runtime is already loaded. This does not rewrite another vendor package. Other historical folder names require the same single-active-package procedure. Historical 3.0.1/4.8.4 end-to-end updates and WordPress.org update recognition still require independent staging verification.

## Moderation and partial refunds

An eligible registered customer's top-level product review can claim one reward after approval. Replies and unapproved reviews cannot earn. Unapproval, spam, trash or deletion reverse a known wallet credit once; debt remains non-spendable. Reapproval never creates a second entitlement. Imported legacy claims remain permanent identities; no unproven historic debt is invented. Pending moderation/reconciliation is retained when a wallet is unavailable.

Earning retains the existing gross, pre-coupon product-subtotal policy. A partial refund creates a durable decision keyed by the actual WooCommerce refund ID and a reconciliation warning/fulfillment hold; it does not invent a proportional points policy. The hold survives delayed processing/completed/payment/recovery callbacks even when the loyalty programme is disabled. An administrator can use **Financial setup → Resolve a reviewed partial refund**, supplying the matching order/refund IDs, a 10–2000 character decision/reference and explicit confirmation. The immutable zero-value resolution records the reviewer; it neither adjusts points nor advances fulfillment. Review those outcomes separately. Replays retain the same resolution; a later refund requires a new review. Full refunds use the original award and debit references. Commercial proportional-refund policy and any restoration grace period remain explicit product decisions; original lot expiry is retained.

## Single site and uninstall

Financial operations support single-site WordPress. Network activation and cross-network financial migration are unsupported; activation refuses multisite and uninstall performs no network cleanup. No multisite certification is claimed.

Uninstall retains every customer balance, lot, transaction history, wallet/journal/allocation, snapshot, order claim and authority marker. The optional cleanup setting removes only plugin preferences and installed-version metadata on a single site. Financial erasure requires a separate reviewed export/reconciliation procedure. Reinstallation must inspect retained authority and migration state rather than creating a second opening balance.

## Roles

Administrators, shop managers and custom roles explicitly granted `manage_woocommerce` can save loyalty settings and request audited adjustments. Customers and subscribers cannot. Adjustments require POST, a valid nonce, an existing customer, bounded positive whole points and a stable operation UUID. Repeated financial operation identities cannot create repeated effects. The Financial setup and refund-resolution interface requires `manage_options`; shop managers must ask an administrator. Diagnostic migration/schema CLI tools retain their separate local-only gates.

## Development schema revision3

The explicitly authorized Financial setup schema step (or the local diagnostic CLI installer) adds `active_lots(user_id,remaining,expires_at,id)` to existing revision2 tables and verifies its exact shape. It preserves every lot and journal row. Deploying this development revision before the index is installed fails closed; normal activation never runs the ALTER or a production migration. Back up and pause financial writers before invoking an explicitly authorized schema step.
