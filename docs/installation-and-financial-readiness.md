# Nodevora 4.8.6 QA — installation et données financières

Candidate privée non publiée, réservée à une démonstration sauvegardée.

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


## Anciennes identités Rewardly

Archiver le package et la base, arrêter les writers externes, désactiver l’ancienne copie sans désinstallation, puis installer le dossier officiel. Les clés historiques restent conservées. Une seule copie doit être active ; la coexistence de fichiers inactifs n’est pas une migration automatique. Les packages historiques non testés doivent faire l’objet d’une validation de staging indépendante. Aucun rename automatique ni updater GitHub.

## Schéma et sécurité

Le schéma financier reste en révision3 ; ce travail ne remplace pas le wallet, le journal immuable, les lots ou les identités d’opération. Un site déjà doté d’une autoritéWallet n’est jamais réimporté. Les writes utilisent la barrière partagée d’état ; import et cutover ont un mutex d’opération. Les transactions n’exécutent pas de callbackWordPress.

Les imports exigent InnoDB pour les données et les identités référencées. Les références sont vérifiées puis verrouillées au passage final. Un rollback garde la pause ; les marqueurs et snapshots ne constituent pas une autorisation de production. Les tombstones conservent l’historique d’entités déjà absentes, sans recréer leur compte/commande. Une identité réutilisée ou modifiée rend la cible incompatible.

Les sauvegardes sont streamées vers un fichier temporaire privé et supprimé à la fermeture ; aucune sauvegarde publique dansuploads. Elles n’incluent ni utilisateursWordPress complets, ni commandes complètes, ni secrets de configuration. L’import ne remplace aucun wallet actif et ne crée aucun compte/commande. Une sauvegarde avant synchronisation reste une preuve exportable, mais ne certifie pas à elle seule un état financier prêt à activer.

L’uninstall conserve l’intégralité des preuves financières ; l’option de nettoyage ne retire que les préférences. Le multisite et les passerelles arbitraires ne sont pas certifiés. PHP7.4/WP6.4/Woo8.2 restent des minimums déclarés : consulter le rapport de QA pour les environnements réellement vérifiés.
