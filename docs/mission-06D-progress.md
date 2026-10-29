# Mission 06D — SAFE PAUSE 02 / reprise exacte

**Mission INCOMPLÈTE, développement arrêté à la demande de l’utilisateur.**
Checkpoint du 29 octobre 2026 ; pause LocalWP vérifiée à 20:08:30 UTC.
Avancement estimé : **90 %**, estimation de travail et non pourcentage de tests réussis.

- Dépôt : `/Users/user/Desktop/work/nodevora-plugin/github`.
- Branche : `feat/nodevora-4.8.6-financial-foundation`.
- Dernier commit avant cette sauvegarde : `5a801e0b7a639fe1c514333ceeb91cc93cfae4f1`.
- Correction précédente : `ee67cac` ; checkpoint de reprise précédent : `0f98eb535f6723d06e2824327b683809812713a6`.
- Le commit WIP contenant ce fichier est le nouveau checkpoint ; son SHA exact est ajouté après commit au rapport local `audit-reports/mission-06D-progress.md`, hors dépôt. Pour retrouver ce commit dans Git : `git log -1 --format=%H -- docs/mission-06D-progress.md`.
- `main` local inchangé : `eca72d6c08184ed5dd7e28b4e52abc98ebf342ef`.
- Dernière vérification distante pendant la reprise : main au même SHA ; développement `d4936e165eb75300aff483d5685d97e9c5626077`. PR #2 ouverte, Draft et non fusionnée. Aucun push Mission 06D, aucun changement de PR pendant cette pause. Le distant n’est pas reconsulté pour créer une nouvelle phase de livraison.
- **Aucun ZIP final QA, release, tag, merge, changement main ou SVN.** Trois archives privées d’intégration existent, non livrables.

## Autorisation et périmètre

Seul le site jetable explicitement autorisé **LocalWP nodevora-plugin / http://localhost:10004/** a été manipulé. Identité recoupée : Local id `6JRuOv4ug`, docroot `/Users/user/Local Sites/nodevora-plugin/app/public`, DB `local`. Les autorisations de reset concernent ses seules données fictives. Aucun autre site, environnement de production ou fixture antérieure n’a été modifié. Aucun identifiant de connexion n’est conservé dans Git ou les rapports.

Spécification : pièce jointe `689ce5a8-7143-4852-bc2b-5de730dee2eb/Pasted text.txt`. Les limitations financières antérieures demeurent : aucune politique proportionnelle de remboursement partiel, aucun contournement des gates, aucun drainage d’une file inconnue, aucune migration de production.

## Phases terminées et décisions techniques

1. Installation vierge et synchronisation : implémentées et testées sur le vrai installateur WordPress. Schéma vérifié, autorité wallet, wallets créés à la demande, aucun import global sur une boutique fraîche. Synchronisation marchande par lots bornés de 20, snapshots/checksums, reprise et quarantaine ; pas de migration silencieuse. L’arrivée d’un compte après le dernier lot vide provoque une nouvelle découverte bornée avant cutover.
2. Registration et messages : défaut checksum NULL reproduit puis corrigé ; réparation limitée au snapshot vide reconnu généré par ce chemin. Barrière partagée avec restauration, échec conservé en attente. Instructions métier localisées conservées par type d’erreur fiable ; exceptions internes masquées, mapping EN/FR vérifié.
3. Backup/Restore format 2 : export NDJSON borné (32 Mio, 100000 enregistrements, 1 Mio par ligne), snapshot cohérent, intégrité, références et tombstones, métadonnées Nodevora uniquement. Import seulement sur cible Nodevora vide, confirmation du fichier exact, transaction/rollback, moteurs transactionnels et verrous d’identités, phase review puis reprise explicite. Aucun remplacement d’une base ayant de nouvelles transactions.
4. Admin et design : panneau unifié, Simple/Advanced par utilisateur, réglages avancés conservés, permissions serveur, palette WordPress/HEX/presets/preview/reset. Grille Data corrigée à 320 px. Dernier ajustement : classe de grille sur le formulaire de revue des remboursements, rendu desktop vérifié ; sa vérification mobile finale ciblée reste à faire.
5. Régressions et intégration : parcours réel ajustement 300, gain 200, dépense 119/remise 5,95 ; Classic/Blocks, concurrence, refund hold, Woo absent/rétabli, réinstallation, vraie migration du package officiel 4.8.5 vers la candidate 4.8.6. Preuves détaillées ci-dessous.
6. Livraison : matrice de future QA indépendante rédigée, **pas exécutée**. Rapport final, acceptation des derniers bits de l’archive, ZIP final, push dev et mise à jour PR Draft **restants**.

## Tests réellement exécutés — PASS

Les preuves JSON citées sont conservées dans `audit-reports/mission-06D-evidence/` et leur sélection sans secrets dans `github/tests/remediation/mission-06d-evidence/`. Les preuves originales ne sont pas écrasées. Ne pas additionner les nombres ci-dessous en un total de couverture : certaines validations se recoupent.

| Domaine | Résultat et preuve |
|---|---|
| Registration | Échec initial checksum NULL confirmé, puis initialisation/réparation bornée et barrière DB restoring PASS : `registration-before.json`, `registration-after.json`, `registration-barrier.json` |
| Messages EN/FR | Instructions fiables conservées et diagnostics inconnus masqués : `messages-en-fr.json` |
| Format 2 bibliothèque | 35 contrôles PASS sur WIP, puis 35 sur candidate 4.8.6 : 2960 enregistrements, 1204089 octets ; corruptions, versions, doublons, références, objets et options non autorisés refusés, rollback intégral, égalité des données/restauration/invariants : `format2-roundtrip-486.json` |
| Format 2 interface | Preview/import/review/reprise via interface réussis ; endpoint export HTTP200 NDJSON valide, en-têtes téléchargement, solde300/invariants : `format2-ui-486.json`. Les anciens tests format1 ne servent pas de preuve format2 |
| Stockage | 3 contrôles PASS : moteur cible non transactionnel simulé refusé avant insertion ; vrai verrou partagé d’identité face à update indépendant (timeout1205) ; primitive de snapshot export stable malgré crédit concurrent : `storage-and-reference-locks.json` |
| Permissions | 128 assertions HTTP PASS, quatre politiques, Admin/ShopManager/Editor/Customer/Subscriber/Guest, URL/options.php/admin-post/AJAX/REST : `permissions-http.json` |
| Finance ciblée | 18 assertions refund hold/callbacks/programme désactivé/résolution/recovery et 12 smoke financement/COD/BACS/zéro/reversal/StoreAPI/invariants PASS : `financial-targeted.json`, `smoke-targeted.json` |
| Concurrence | 3 scénarios PASS : deux débits80 sur100, crédit100 dupliqué, écriture engagée puis pause/callback tardif : `concurrency-targeted.json` |
| Design | 11 contrôles PASS : modes, HEX/palette/presets, sauvegarde, préservation Advanced, resets, preview/contraste : `ui-design.json` |
| Responsive Admin | 42 vues EN/FR ×320/390/1280, sept onglets, labels et bounds PASS après correction Data360→320 : `admin-responsive.json`. Dernière classe du formulaire refund à recontrôler de façon ciblée |
| Responsive frontend | 36 vues Classic Cart/Checkout, Blocks Cart/Checkout, compte et shortcodes, EN/FR ×320/390/1280 PASS ; pas de débordement ni Dispatch : `frontend-responsive.json` |
| Fresh réel | Upload ZIP/Install/Activate via WordPress, 257 utilisateurs existants, zéro wallet initial, aucune étape Financial Setup ; configuration puis ajustement UI0→300, retry journal sans double crédit : `fresh-486-bootstrap.json`, `fresh-486-adjustment-replay.json` |
| Achat/gain réel | Utilisateur : Classic commande288 à100MAD COD, puis Completed ; gain200, wallet500, événement unique/invariants : `fresh-486-cash-earning-ui.json` |
| Dépense financée réelle | Application119 points Classic persistée dans Blocks ; utilisateur : commande289 à94,05MAD, fee−5,95, débit119, wallet381/reserved0/debt0, état dispatched/invariants : `fresh-486-blocks-funded-ui.json` |
| Woo et réinstallation | 6 contrôles PASS : Woo inactif sans fatal, trois HTTP200, retour init Blocks ; désinstallation/réinstallation sans nettoyage conserve tables/préférences/autorité : `dependencies-reinstall-486.json` |
| Authentique485 vide | Package officiel485→486 : ready/fresh, zéro wallet, réglages conservés : `official-485-empty-to-486.json` |
| Authentique485 avec données | Activation candidate sans migration automatique ; reprise par endpoints supportés puis bouton marchand UI ; 15 comparaisons persistantes PASS :123 cohérent,99 isolé,1000 points/1000 lots/expirations, sources/settings/commande/opening/invariants conservés : `official-485-final-before.json`, `official-485-final-activated.json`, `official-485-final-after.json` |
| Nouvelle inscription tardive | Après dernier lot vide, user332 redécouvert dans un lot avant cutover ; aucune autorité activée prématurément : `sync-final-new-account-race.json` |
| Recovery revue | Empreinte périmée refusée, aucun effet livré ; nouvelle revue/autorisation explicite UI réussie sans exécution cron, puis pause rétablie : `recovery-review-stale.json`, `recovery-review-ui.json` |
| Identité historique | Fichiers officiels485 sous chemin legacy reconnu, inactifs et conservés en copie privée ; second runtime refusé dans processus isolé sans changer active_plugins : `historical-file-identity-guard.json`. Ce n’est pas une certification des vrais packages Rewardly3.0.1/4.8.4 |
| Syntaxe | 42 fichiers PHP runtime+harness sur8.2/8.3 et5JS PASS, 438 messages français compilés/check-format. Dernières petites modifications runtime lintées lors des builds privés ; pas de nouveau rejeu global à la pause : `candidate-syntax-486.json` |
| Pause02 | Gates persistés, zéro transaction, settings/tables financières identiques avant/après, commandes fictives mises en attente : `safe-pause-02-localwp.json`, `safe-pause-02-backup.json` |

## FAIL corrigés, BLOCKED et limites

- Défauts reproduits puis corrigés : checksum registration NULL ; overflow Data à320 ; compte arrivé après le dernier scan vide empêchant la fin de synchronisation. Les preuves avant/après sont conservées.
- Deux erreurs de harness ont été corrigées : syntaxe avant toute transaction, détection de CSS WordPress concaténé. Une lecture preview était prématurée et a été reprise après chargement effectif. Aucun échec initial n’est présenté comme PASS produit.
- **BLOCKED capture navigateur seulement** : événement téléchargement export non remonté (timeout CUA). Endpoint réel export vérifié séparément ; ne pas prétendre avoir capturé un téléchargement navigateur format2.
- **Restriction automatique sur les clics financiers** : soumission checkout et enregistrement Completed refusés par le contrôle d’approbation, même sur COD fictif. L’utilisateur a réalisé ces trois clics, puis le journal et les commandes ont été vérifiés. Aucun contournement API/CLI. Parcours validé avec cette intervention humaine, pas en autonomie intégrale.
- Pas de défaut produit connu restant dans les assertions exécutées ; **acceptation des dernières variantes du package et livraison incomplètes**. Ne pas présenter la mission comme terminée ou la candidate comme prête pour production.
- Stack complète testée : WordPress7.1.4 / WooCommerce11.2.1 / PHP8.3.30. Bootstrap WordPress/Woo, schéma et lecture wallet réels sur PHP8.2.29 : `php82-bootstrap-486.json`, pas de certification complète checkout8.2. Les minimums déclarés PHP7.4/WP6.4/Woo8.2 ne sont pas nouvellement certifiés par Mission06D ; preuves anciennes distinctes.
- Pas de certification multisite, gateways arbitraires, SMTP externe, production ou de toutes les anciennes identités/packages historiques. Aucune politique proportionnelle de refund implémentée.

## Mesures de performance locales

`admin-assets-performance.json` : deux GET authentifiés par onglet, site LocalWP complet (thème/autres plugins inclus), pas des Core Web Vitals ni un benchmark production.

| Onglet | Durées HTML (ms) | Taille HTML (octets) |
|---|---|---|
| General |1011,9 /891,8|285574|
| Design |848,3 /1347,5|325290|
| Data |1336,5 /1114,8|283247|

DesignJS8517 octets et DataJS2243 octets chargés seulement sur leurs onglets ; palette WordPress sur Design uniquement. Aucune nouvelle mesure démarrée pour cette pause.

## État EXACT LocalWP sécurisé

Preuve : `safe-pause-02-localwp.json`, capture `safe-pause-02-admin.png`.

- Site toujours lancé, identité ci-dessus ; WP7.1.4/Woo11.2.1/PHP8.3.30, Nodevora4.8.6 installé depuis l’archive privée d’intégration3.
- Setup `phase=paused`, `origin=synchronized`, `authority=wallet`, `installation_status=ready` (schéma prêt, **pas** permission d’écriture), `cursor=332`, `processed=332`, `quarantined=2`, `scan_complete=true`, `batch=[]`, `recovery_approved=false`. Marqueur `safe_pause_checkpoint=02`.
- **0 transaction InnoDB active. Aucune migration/restauration en cours.** Aucun harness financier actif ni checkout en cours.
- Constantes : `NODEVORA_FINANCIAL_WRITES_PAUSED=true`, `NODEVORA_FINANCIAL_INTEGRATION_ALLOWED=false`, `NODEVORA_QA_TESTING=false`, `DISABLE_WP_CRON=true`, `NODEVORA_FINANCIAL_SCHEMA_ALLOWED=false`, `NODEVORA_FINANCIAL_MIGRATION_ALLOWED=false`, `NODEVORA_FINANCIAL_WRITERS_DRAINED=false`.
- QA désactivée, aucun gateway QA. MU `nodevora-m06d-safety.php` et `nodevora-qa-safety.php` conservés ; blocage mail/HTTP sortants maintenu.
- **330 wallets ready, total1123 points ; 2 needs_review, solde wallet0**. Sources123 cohérent,99 ambigu conservé en quarantaine ; compte tardif332 en attente/quarantaine. Ne pas inventer un bonus ni réparer arbitrairement99.
- **1 effet outbox pending connu**, attempts0 : projection de test zéro valeur, aucun effet livré ni purgé. Recovery refusée à nouveau malgré l’historique d’approbation conservé. Ne pas vider la file à la reprise.
- Commandes fictives **288 et289 on-hold** ; aucun paiement, remboursement ou ajustement de points effectué pour la pause. 288 pending→on-hold, total courant95 ; 289 processing→on-hold,94,05. Leur identité fictive a été vérifiée. Données/journal/source immutable conservés.
- Important : après archivage SQL du parcours fresh300→500→381, le dataset Nodevora a été réinitialisé intentionnellement pour la vraie migration485. La commande288 a été réutilisée comme baseline legacy95. **Le dataset courant n’est donc pas celui des preuves cash100/wallet381** ; celles-ci restent dans les archives privées et preuves dédiées. Ne pas les confondre.
- Tables financières inchangées par la pause ; préférences marchandes inchangées, SHA256 sérialisation `fe2d91c31a037b4ab8e3371a1488a89f3e46a799cc564485a85ad36f9ddfc72c`.
- Configurations de test conservées : cart286/checkout287, COD local, langue sitefr_FR, locale opérateuren_US. Valeurs antérieures privées dans `commerce-options-before.json` et `operator-locale-before.json` ; pas de restauration aveugle à la reprise.

## Sauvegardes et archives privées

Dossier durable **`.private/mission-06D/` au niveau workspace, hors `github/`**, permissions0700 ; configs/SQL/scripts privés0600. Ne jamais ajouter ces fichiers, identifiants ou exports à Git. Tous les backups précédents et les preuves restent conservés ; aucun original écrasé. Pointeur local : `audit-reports/mission-06D-evidence/durable-private-location.txt`.

- Avant mission : SQL7286082 octets, SHA256 `d24c6add20ca543f3aa79efee198bcfaf17cb5824fff2b0b0d77275d8bec5c66`, docroot `site-before/`.
- Pause précédente : `safe-stop-demo.sql`3342889 octets, SHA256 `19ae0f0082fca6bc865c6619a5940ba05c4923d9c1be7a7609d2fa556fc24946` ; ancien rapport conservé `progress-before-pause-02.md`.
- **Pause02 : `safe-pause-02-demo.sql`3429833 octets, SHA256 `0895a78ba2b23ac076a5cb77da10d389e79d0dbfbb0980828a474901fe6ecb8e`**, `before-pause-02-wp-config.php`, `safe-pause-02-wp-config.php`.
- Backups uniques avant format2, réinstallation, fresh486 et migration finale conservés, avec exports format1/format2 et options précédentes.
- Scripts de cette session copiés sans écraser l’ancienne copie dans `session-tools-pause-02/`. Les scripts peuvent pointer vers d’anciens fichiers temporaires : adapter les chemins au dossier durable et créer de nouveaux noms de preuves, ne pas relancer aveuglément les resets.
- Package officiel485 utilisé : `audit-reports/mission-06-evidence/checkpoint-2/nodevora-customer-rewards-for-woocommerce.4.8.5.zip`, SHA256 `86e0dabe18533cc694a2a0fcf2ca2a81121e1239610762efc015ff38dacff37a`.

Archives privées non finales, 48 fichiers runtime, pas de tests/docs/credentials :

| Archive | Octets | SHA256 | Portée |
|---|---:|---|---|
|candidate-486-integration-1.zip|182389|`cf7a37f8e6455450a52d8d7722fd1918a2cd155751896d6d14b54676972bb3dc`|Fresh/300/parcours Classic+Blocks/format2|
|candidate-486-integration-2.zip|182487|`c0f64cb5ee6f0b5fbf13ad08400096268d563e149bfb06047181f92c19293b8e`|Seul delta runtime : redécouverte inscription tardive ; migration authentique finale validée|
|candidate-486-integration-3.zip|182492|`11047f9f72a787e751975307f5c8872343275c7b2225ec2766a98aaeafb46496`|Actuellement installée ; seul delta vs2 : classe de grille formulaire refund, rendu desktop vérifié|

Le nom final `nodevora-customer-rewards-for-woocommerce-4.8.6-qa.zip` n’a pas été créé. Métadonnées source4.8.6 sur branche de développement uniquement, aucune publication.

## Reprise — première action précise et reste à livrer

**Attendre une instruction explicite RESUME MISSION 06D. Aucun développement après ce checkpoint.**

1. Lire ce rapport, vérifier branche/HEAD/arbre propre et identité du seul site jetable. Conserver toutes les pauses pendant cette vérification. Ne pas recommencer les anciens audits ni les suites déjà PASS.
2. Reprendre l’acceptation finale de la variante privée3 : vérification mobile EN/FR320/390 du formulaire refund corrigé ; décision documentée et contrôle fresh install/ajustement300 sur les bits finaux si nécessaire. Archiver d’abord le dataset migré courant dans un nouveau backup avant tout reset autorisé. Ne pas répéter inutilement le parcours cash/Blocks : code financier inchangé entre1 et3 hors voie synchronisation legacy, déjà vérifiée sur2.
3. Pour les seuls tests autorisés, retirer délibérément les pauses temporaires écriture/intégration et rétablir phaseactive après contrôles ; conserver QAfalse, cronoff, recoveryfalse, mail/HTTP bloqués. **Ne pas lancer une nouvelle synchronisation sur les wallets déjà migrés.** Ne pas compléter/rembourser automatiquement les commandes en attente ; ne pas purger l’effet pending.
4. Rédiger `audit-reports/mission-06D-final-report.md` + miroir Git : résultats exacts, portée de chaque variante, mesures locales, compatibilités et limites, index de preuves. Finaliser la matrice existante `mission-06D-independent-QA.md` sans exécuter cette QA suivante.
5. Seulement après acceptation : construire la ZIP runtime propre au slug officiel avec `github/tools/build-qa.py`, vérifier installabilité finale/empreinte SHA256 et fournir l’archive QA. Aucun paquet de release officielle.
6. Reconfirmer sécurité LocalWP et éventuelle remise des options de test antérieures documentée, conserver backups/preuves/données. Commit final, push **seule branche de développement**, mise à jour PR#2 qui reste Draft, vérification SHA distant et main inchangé. Aucun merge/main/SVN/production/Mission suivante.

## Fichiers et persistance Git de cette pause

Deux modifications runtime finales sauvegardées :
- `includes/financial/class-nodevora-financial-setup.php` : redécouverte du nouvel utilisateur après scan vide, test `sync-final-new-account-race.json` et migration finale PASS.
- `includes/admin/class-nodevora-data.php` : classe `nodevora-data-form` sur revue refund, rendu desktop vérifié ; recontrôle mobile final restant.

Ajouts : harness concurrence et permissions HTTP, preuves curatées supplémentaires, ignore des caches Python, matrice de QA indépendante, présent checkpoint. Aucun nouveau test fonctionnel démarré pour la pause. Contrôle de cohérence documentaire et `git diff --check` avant commit ; aucun rejeu complet des suites.

L’inventaire ci-dessous couvre l’ensemble des chemins modifiés depuis la base Mission06D `d4936e1`, pas uniquement cette pause. Les backups privés et les rapports locaux hors dépôt ne sont pas inclus.

- `.gitignore`
- `assets/css/admin-legacy.css`
- `assets/css/admin.css`
- `assets/css/loyalty.css`
- `assets/js/admin-data.js`
- `assets/js/admin-design.js`
- `assets/js/loyalty-blocks.js`
- `docs/installation-and-financial-readiness.md`
- `docs/mission-06D-checkpoint-evidence/backup-import-ui.json`
- `docs/mission-06D-checkpoint-evidence/fresh-300-journal-retry.json`
- `docs/mission-06D-checkpoint-evidence/official-485-sync-after.json`
- `docs/mission-06D-checkpoint-evidence/safe-stop-localwp.json`
- `docs/mission-06D-checkpoint-evidence/safe-stop-persistence.json`
- `docs/mission-06D-checkpoint-evidence/safe-stop-syntax.json`
- `docs/mission-06D-checkpoint-evidence/sync-ui-drift.json`
- `docs/mission-06D-checkpoint-evidence/sync-ui-interruption.json`
- `docs/mission-06D-independent-QA.md`
- `docs/mission-06D-progress.md`
- `includes/admin/class-nodevora-access.php`
- `includes/admin/class-nodevora-data.php`
- `includes/admin/class-nodevora-design-admin.php`
- `includes/admin/class-rewardly-loyalty-admin-adjustments.php`
- `includes/admin/class-rewardly-loyalty-admin.php`
- `includes/admin/class-rewardly-loyalty-settings.php`
- `includes/core/class-rewardly-loyalty-helpers.php`
- `includes/core/class-rewardly-loyalty-pro.php`
- `includes/core/class-rewardly-loyalty-redeem.php`
- `includes/design/class-rewardly-loyalty-design.php`
- `includes/financial/bootstrap.php`
- `includes/financial/class-nodevora-backup.php`
- `includes/financial/class-nodevora-financial-checkout.php`
- `includes/financial/class-nodevora-financial-db.php`
- `includes/financial/class-nodevora-financial-lifecycle.php`
- `includes/financial/class-nodevora-financial-migration.php`
- `includes/financial/class-nodevora-financial-readiness.php`
- `includes/financial/class-nodevora-financial-recovery.php`
- `includes/financial/class-nodevora-financial-schema.php`
- `includes/financial/class-nodevora-financial-setup.php`
- `includes/financial/class-nodevora-financial-wallet.php`
- `includes/financial/class-nodevora-installation.php`
- `includes/financial/class-nodevora-restore.php`
- `languages/nodevora-customer-rewards-for-woocommerce-fr_FR.mo`
- `languages/nodevora-customer-rewards-for-woocommerce-fr_FR.po`
- `languages/nodevora-customer-rewards-for-woocommerce.pot`
- `nodevora-customer-rewards-for-woocommerce.php`
- `readme.txt`
- `tests/remediation/mission-06d-backup.php`
- `tests/remediation/mission-06d-concurrency.py`
- `tests/remediation/mission-06d-evidence/admin-assets-performance.json`
- `tests/remediation/mission-06d-evidence/admin-responsive.json`
- `tests/remediation/mission-06d-evidence/candidate-syntax-486.json`
- `tests/remediation/mission-06d-evidence/concurrency-targeted.json`
- `tests/remediation/mission-06d-evidence/dependencies-reinstall-486.json`
- `tests/remediation/mission-06d-evidence/financial-targeted.json`
- `tests/remediation/mission-06d-evidence/format2-roundtrip-486.json`
- `tests/remediation/mission-06d-evidence/format2-roundtrip.json`
- `tests/remediation/mission-06d-evidence/format2-ui-486.json`
- `tests/remediation/mission-06d-evidence/fresh-486-adjustment-replay.json`
- `tests/remediation/mission-06d-evidence/fresh-486-blocks-funded-ui.json`
- `tests/remediation/mission-06d-evidence/fresh-486-bootstrap.json`
- `tests/remediation/mission-06d-evidence/fresh-486-cash-earning-ui.json`
- `tests/remediation/mission-06d-evidence/frontend-responsive.json`
- `tests/remediation/mission-06d-evidence/historical-file-identity-guard.json`
- `tests/remediation/mission-06d-evidence/messages-en-fr.json`
- `tests/remediation/mission-06d-evidence/official-485-empty-to-486.json`
- `tests/remediation/mission-06d-evidence/official-485-final-activated.json`
- `tests/remediation/mission-06d-evidence/official-485-final-after.json`
- `tests/remediation/mission-06d-evidence/official-485-final-before.json`
- `tests/remediation/mission-06d-evidence/permissions-http.json`
- `tests/remediation/mission-06d-evidence/php82-bootstrap-486.json`
- `tests/remediation/mission-06d-evidence/recovery-review-stale.json`
- `tests/remediation/mission-06d-evidence/recovery-review-ui.json`
- `tests/remediation/mission-06d-evidence/registration-after.json`
- `tests/remediation/mission-06d-evidence/registration-barrier.json`
- `tests/remediation/mission-06d-evidence/registration-before.json`
- `tests/remediation/mission-06d-evidence/safe-pause-02-backup.json`
- `tests/remediation/mission-06d-evidence/safe-pause-02-localwp.json`
- `tests/remediation/mission-06d-evidence/smoke-targeted.json`
- `tests/remediation/mission-06d-evidence/storage-and-reference-locks.json`
- `tests/remediation/mission-06d-evidence/sync-final-new-account-race.json`
- `tests/remediation/mission-06d-evidence/ui-design.json`
- `tests/remediation/mission-06d-financial.php`
- `tests/remediation/mission-06d-http-permissions.py`
- `tests/remediation/mission-06d-messages.php`
- `tests/remediation/mission-06d-race-worker.php`
- `tests/remediation/mission-06d-registration.php`
- `tests/remediation/mission-06d-smoke.php`
- `tests/remediation/mission-06d-storage.php`
- `tools/build-qa.py`

Pause volontaire complète après sauvegarde. Reprise possible sans perte ; livraison QA encore incomplète.
