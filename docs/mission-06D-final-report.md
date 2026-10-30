# Mission 06D — rapport développeur final

Date : **30 octobre 2026**. Statut : **candidate QA 4.8.6 livrable ; validation indépendante à effectuer avant toute release**. La mission développeur est terminée dans les limites explicites de ce rapport. Aucun avis d’autorisation de production.

## Candidate et traçabilité

| Élément | Valeur |
|---|---|
| Archive | `nodevora-customer-rewards-for-woocommerce-4.8.6-qa.zip` |
| Chemin local absolu | `/Users/user/Desktop/work/nodevora-plugin/artifacts/nodevora-customer-rewards-for-woocommerce-4.8.6-qa.zip` |
| Taille | **182492 octets** |
| SHA-256 | **`11047f9f72a787e751975307f5c8872343275c7b2225ec2766a98aaeafb46496`** |
| Structure | Un seul dossier `nodevora-customer-rewards-for-woocommerce/`, 48 fichiers runtime, intégrité ZIP vérifiée |
| Base Mission06D | `d4936e165eb75300aff483d5685d97e9c5626077` |
| Source runtime finale | `93eb9f35bf23a74c953a8cecb11943a2186927aa` ; aucun changement runtime depuis ce commit |
| Commits intermédiaires | `855a7c8` WIP initial ; `0f98eb5` checkpoint ; `ee67cac` registration/format2 ; `5a801e0` candidate/UI ; `93eb9f3` pause02 et corrections finales |
| Branche | `feat/nodevora-4.8.6-financial-foundation` |
| Commit de livraison | Commit contenant ce rapport : `git log -1 --format=%H -- docs/mission-06D-final-report.md`. Le SHA exact après création est enregistré dans le rapport local hors Git et le reçu de livraison |
| PR | https://github.com/DELTAWEBMAROC/rewardly-loyalty/pull/2 — doit rester Draft, sans merge |
| Main préservé | `eca72d6c08184ed5dd7e28b4e52abc98ebf342ef` |

`final-package-manifest.json` donne les empreintes des 48 fichiers, l’égalité source/runtime installé/ZIP, les différences entre archives d’intégration et la syntaxe ciblée finale. Aucun test, fixture, log, secret, config locale, SQL, `.git`, `.DS_Store` ou `__MACOSX` n’est inclus. Le header PHP, la constante de version et le stable tag de cette candidate sont4.8.6. Cette préparation sur branche de développement n’est pas une publication WordPress.org. Le ZIP reste un artefact local, pas un asset de release GitHub.

## Architecture livrée

La mission conserve le wallet transactionnel, journal immuable, lots, allocations, dette non dépensable et identités idempotentes. Les clés/classes historiques Rewardly sont conservées ; pas de renommage global ni de remplacement du cœur financier. Les transactions financières n’exécutent pas de callbacks WordPress. Le schéma reste en révision3.

### Installation neuve et synchronisation

Une activation réellement sans données de fidélité crée et vérifie automatiquement le schéma, sauvegarde l’autorité wallet et ouvre le panneau principal en mode Simple. Les utilisateurs WordPress préexistants reçoivent un wallet vide seulement à la demande. Pas de Financial Setup, référence backup ou autorisation MySQL à saisir par le marchand. Échec DB/schéma ou pause opérateur : état fermé, message exploitable, aucune écriture legacy de secours.

La détection inspecte wallets/journaux/lots/snapshots/claims et métadonnées pertinentes des utilisateurs, commandes, éléments et commentaires ; le seul numéro de version ne suffit pas. Une autorité wallet déjà établie n’est jamais réimportée. Les données anciennes imposent le parcours Synchronisation, redirection quand possible ou notice persistante, et état visible dans Nodevora.

L’interface recueille l’attestation d’arrêt des anciens outils et traitements externes. Le plugin ne prétend pas arrêter un writer externe qu’il ne contrôle pas. Des lots de20, curseur et états persistants, checksums des sources, snapshots immuables et mutex de cutover permettent reprise et refus des preuves modifiées. Les comptes incohérents sont isolés sans inventer de lots ni d’expirations. Un nouveau compte après le dernier lot vide est redécouvert avant activation. Le checkout peut être protégé pendant la synchronisation, interruption expliquée au marchand. Aucune ancienne file inconnue n’est exécutée automatiquement.

### Backup / Restore format2

Exports Nodevora uniquement, NDJSON version2, snapshot cohérent, empreintes de contenu/footer, références utilisateur/commande/item/commentaire/post et tombstones d’entités déjà absentes. Métadonnées financières pertinentes conservées, aucune sauvegarde générale WordPress. Streaming vers fichier temporaire privé supprimé à la fermeture ; pas de sauvegarde publique sous uploads.

Limites :32Mio,100000 enregistrements,1Mio par ligne. Version plugin et schéma doivent correspondre. Preview et confirmation sont liées au fichier exact par nonce/empreinte ; droits sensibles administrateur uniquement. La cible Nodevora doit être vide, avec identités externes compatibles. Import atomique InnoDB, vérification et verrouillage final des références, mutex d’opération et barrière partagée avec les writers. Erreur : rollback et pause conservée. Succès : revue puis reprise explicite, recovery inconnue non approuvée. Aucun import d’utilisateurs/commandes ni écrasement d’un wallet ayant une activité nouvelle.

Le défaut `registration → checksum NULL` a été reproduit puis corrigé. La réparation automatique est limitée au snapshot vide reconnu créé par ce chemin. En cas de barrière restore/migration, l’inscription conserve son marqueur pending sans fatal ni crédit inventé. Les anciens essais format1 restent des preuves historiques et ne valident pas le format2.

### Panneau, modes, design et droits

Un panneau Nodevora avec Général, Design, Ajustements, Shortcodes, Fonctions avancées, Aide et Synchronisation/Données lorsque pertinent. L’ancien Financial Setup technique disparaît de la navigation normale. La revue sensible des remboursements reste dans la zone avancée administrateur.

Simple est la vue initiale ; Advanced est une préférence par utilisateur, conservant les règles enregistrées. Le design utilise des variables CSS limitées aux composants Nodevora, presets, palette WordPress, HEX validé, overrides locaux, preview sans sauvegarde, reset individuel/global et avertissement de contraste. Aucun suivi automatique du thème ou bibliothèque lourde ; pas d’AJAX à chaque déplacement de palette.

Administrator conserve l’accès ; Shop Manager possède ses droits ordinaires existants ; Editor peut être autorisé explicitement en lecture seule. Restriction ON réserve le panneau aux administrateurs. Les gates sont appliqués aux URLs/POST/AJAX/REST et non uniquement aux menus. Consultation du panneau ne confère pas backup/import/synchronisation/autorité/refund/policy. Les messages métier déjà localisés ne sont plus retraduits ; diagnostics DB internes masqués.

## Validation développeur exécutée

Les preuves sélectionnées sans secrets sont versionnées dans `tests/remediation/mission-06d-evidence/`. Copies locales et captures : `/Users/user/Desktop/work/nodevora-plugin/audit-reports/mission-06D-evidence/`. Les anciens audits/suites363 assertions n’ont pas été rejoués. Les nombres ci-dessous se recoupent : ne pas les additionner en un pourcentage de couverture.

| Domaine | Résultat / preuve |
|---|---|
| Inscription/checksum | Défaut NULL reproduit puis nouveaux comptes/réparation bornée/barrière restoring vérifiés : `registration-before.json`, `registration-after.json`, `registration-barrier.json` |
| Erreurs EN/FR | Mapping connu, instruction fiable préservée, erreur interne masquée PASS : `messages-en-fr.json` |
| Permissions | **128 assertions PASS**, quatre politiques, six acteurs Admin/Manager/Editor/Customer/Subscriber/Guest, URL/options.php/admin-post/AJAX/REST : `permissions-http.json` |
| Refund hold | **18 assertions PASS** : programme désactivé, statuts/callbacks tardifs, recovery, idempotence et résolution autorisée : `financial-targeted.json` |
| Smoke financier | **12 assertions PASS** : BACS/COD/zéro, gain dupliqué/reversal, extension StoreAPI/Blocks et invariants : `smoke-targeted.json` |
| Concurrence | **3 scénarios PASS** : deux débits80 sur100, crédit100 dupliqué, writer engagé avant pause/callback tardif refusé : `concurrency-targeted.json` |
| Format2 | **35 contrôles PASS** sur candidate4.8.6 ;2960 enregistrements/1204089 octets ; corruptions, versions, schéma, identités, doublons, objets et options non autorisés refusés ; rollback intégral puis import compatible/égalité/invariants/retry sans doublon : `format2-roundtrip-486.json` |
| Format2 interface | Preview/import/revue/reprise via interface supportée ; endpoint export réelHTTP200 NDJSON/en-têtes valides, wallet300/invariants : `format2-ui-486.json`. Capture événement download navigateur non obtenue, voir limites |
| Verrous/stockage | **3 contrôles PASS** : MyISAM cible simulé refusé avant insert, vrai verrou partagé d’identité contre update indépendant (timeout1205), primitive snapshot export stable malgré crédit concurrent : `storage-and-reference-locks.json` |
| Modes/design | **11 contrôles PASS** : Simple/Advanced, préservation, HEX invalide/valide, palette réelle, presets/reset/save/preview/contraste : `ui-design.json` |
| Responsive Admin | **42 vues PASS**, sept onglets EN/FR ×320/390/1280, après correction Data360→320 : `admin-responsive.json` |
| Dernier formulaire refund | **6 vues PASS**, EN/FR ×320/390/1280 ; labels persistants et Tab OrderID→RefundID : `final-refund-responsive.json` |
| Frontend | **36 vues PASS**, Classic/Blocks Cart+Checkout, compte et shortcodes/history, EN/FR ×320/390/1280, pas de débordement ni Dispatch : `frontend-responsive.json` |
| Woo absent/rétabli et reinstall | **6 contrôles PASS**, bootstrap/shortcodes/assets, trois HTTP200 sans fatal et retour init Blocks ; vraie désinstallation/réinstallation conserve données/préférences : `dependencies-reinstall-486.json` |
| Installation fraîche finale | **ZIP livré réellement Upload/Install/Activate depuis WordPress**, sans opt-in financier ni parcours technique, schéma ready/fresh/wallet,332 WPusers et0 wallet initial ;48 fichiers identiques source/install/ZIP : `final-qa-fresh-bootstrap.json` |
| Ajustement final300 | Configuration puis recherche/ajustement réel depuis interface : un wallet300, un événement crédit300, invariants PASS : `final-qa-adjustment-300.json`, capture `final-qa-adjustment-300-confirmed.png` |
| Retry ajustement | Replay du journal d’ajustement300 sans second crédit, wallet identique/invariants : `fresh-486-adjustment-replay.json` (variante1, même code financier final) |
| Classic cash/gain | Commande fictive288,100MAD/COD, achevée par utilisateur : gain200 après ajustement300, wallet500, événements/invariants : `fresh-486-cash-earning-ui.json` |
| Dépense financée Blocks |119points appliqués Classic et persistés dans Blocks ; commande fictive289, total94,05MAD/fee−5,95, débit119 effectif, wallet381/reserved0/debt0, financial_state dispatched : `fresh-486-blocks-funded-ui.json` |
| Vrai485 sans données | Package officiel485→486 : ready/fresh,zéro wallet, réglages conservés : `official-485-empty-to-486.json` |
| Vrai485 avec données | **15 comparaisons persistantes PASS** : activation sans import automatique, fin via interface marchande ;123 cohérent,99 protégé,1000points/1000lots/expirations, settings/sources/commande/opening unique/invariants : `official-485-final-before.json`, `official-485-final-activated.json`, `official-485-final-after.json` |
| Arrivée tardive | Nouveau user332 après scan vide redécouvert avant cutover, sans activation prématurée : `sync-final-new-account-race.json` |
| Recovery | Ancienne empreinte refusée ; revue/autorisation explicite UI sans exécution de file, puis pause restaurée : `recovery-review-stale.json`, `recovery-review-ui.json` |
| Identités historiques | Fichiers officiels485 sous chemin legacy reconnu inactif ; double runtime refusé en processus isolé sans mutation active_plugins : `historical-file-identity-guard.json`. Pas une certification de véritables packages3.0.1/4.8.4 |
| Syntaxe |42 fichiers PHP(runtime+harness) sur8.2/8.3,5JS et438 messagesFR compilés/check-format ; dernières2 classes relintées sur8.2/8.3 à la livraison : `candidate-syntax-486.json`, `final-package-manifest.json` |
| Sécurité finale | Pauses persistées, zéro transaction, tables et réglages inchangés par la pause : `delivery-localwp-safety.json` |

### Correspondance précise des variantes et du ZIP livré

Le manifeste prouve que le ZIP final est identique octet pour octet à la variante privée3. La variante1 a servi au parcours cash/gain/Blocks, format2, UI et réinstallation. La variante2 n’a changé que la redécouverte du compte après dernier scan vide dans `class-nodevora-financial-setup.php` ; cette voie legacy a été vérifiée dans la migration authentique finale. La variante3 n’a ajouté que `class="nodevora-data-form"` au formulaire refund ; ses six vues et labels/clavier viennent d’être vérifiés.

Aucun code de wallet, débit/gain, checkout, backup/restore ou gateway ne diffère entre la variante1 et le ZIP final. Les preuves de ces parcours sont conservées avec cette portée exacte ; aucune prétention à un nouveau replay complet sur la dernière archive. L’archive livrée elle-même a été réinstallée/activée et a crédité300 depuis l’interface. La fraîcheur désigne l’absence de données Nodevora ; WordPress/WooCommerce, utilisateurs et autres plugins de démonstration préexistaient. Pas de certification d’un thème ou assemblage minimal universel.

Le package officiel485 utilisé, SHA256 `86e0dabe18533cc694a2a0fcf2ca2a81121e1239610762efc015ff38dacff37a`, est conservé dans `audit-reports/mission-06-evidence/checkpoint-2/`. Ce n’est pas un simple changement de constante sur le code486. Les interruptions et checksum drift des premiers essais UI sont conservés sous `docs/mission-06D-checkpoint-evidence/`, clairement distincts des migrations finales.

## Mesures de performance et bornes

`admin-assets-performance.json` : deux requêtes GET authentifiées par onglet sur le site LocalWP complet, thème/autres plugins compris. Pas de mesure de Core Web Vitals, pas de comparaison production ni benchmark large.

| Onglet | HTML ms | Octets HTML |
|---|---|---:|
| General |1011,9 /891,8|285574|
| Design |848,3 /1347,5|325290|
| Data |1336,5 /1114,8|283247|

DesignJS8517 octets et DataJS2243 octets ne chargent que sur leurs onglets ; Color Picker uniquement Design. Aucun appel AJAX de palette. Export32Mio/100000 lignes et imports bornés ; synchronisation lots20, aucune lecture globale des utilisateurs pour afficher une page. Mesures descriptives locales, pas de promesse de temps de réponse serveur.

## Échecs corrigés et limites non résolues

Défauts confirmés et corrigés : registration avec checksum NULL incompatible restore, overflow Data à320, absence de reprise exploitable quand un utilisateur arrive après le dernier scan vide. Le formulaire de revue refund a reçu la grille commune puis son contrôle responsive ciblé. Pas de défaut produit restant connu dans les scénarios exécutés.

Erreurs de harness corrigées et non comptées comme PASS initial : syntaxe avant démarrage de transaction ; détection d’asset WordPress concaténé ; dernier sélecteur responsive trouvait le formulaire export au lieu du refund. Une première lecture de preview était antérieure au chargement iframe. Les résultats finaux sont ceux après correction/stabilisation ; aucune sécurité produit n’a été affaiblie pour satisfaire un test.

Limitations explicites :

1. **QA indépendante non encore exécutée.** Cette livraison est une candidate de développement, pas une release ni une autorisation de production.
2. **Remboursements partiels :** holds et revue zéro valeur conservés ; aucune politique proportionnelle automatique approuvée. Les comptes historiques ambigus restent en quarantaine avec leurs preuves ; aucun outil de réconciliation de valeur arbitraire.
3. **Compatibilité historique :**485 officiel réellement vérifié ; reconnaissance/upgrade de tous les véritables anciens packages Rewardly3.0.1/4.8.4 et coexistence de copies actives non certifiés. Désactiver l’ancienne copie, sans effacer ses données, avant le dossier officiel.
4. **Environnements :** stack complète WP7.1.4/Woo11.2.1/PHP8.3.30 ; PHP8.2.29 bootstrap WordPress/Woo+schéma+lecture wallet seulement (`php82-bootstrap-486.json`) et syntaxe. PHP7.4/WP6.4/Woo8.2 restent minimums déclarés, pas une nouvelle certification06D. Les preuves06B sur versions antérieures restent distinctes.
5. **Intégrations :** pas de multisite, passerelles arbitraires, SMTP externe, writers externes ni cutover production certifiés. Flux locaux financiers supportés testés BACS/COD/zéro. Les interactions externes sont bloquées sur la démo.
6. **Capture download :** l’événement navigateur du téléchargement format2 a expiré. Export supporté testé par endpoint authentifiéHTTP200 avec vrai NDJSON/en-têtes ; preview/import/reprise testés par interface. Ne pas présenter le timeout comme un téléchargement navigateur réussi.
7. **Automatisation financière :** le contrôle automatique a imposé des clics humains pour deux commandes et leur étape Completed. L’utilisateur les a effectués ; aucune voie API/CLI de contournement. Les résultats financiers sont vérifiés, mais le parcours n’a pas été exécuté entièrement sans intervention humaine.
8. **Restore :** Nodevora uniquement, cible vide avec identités compatibles, même version/schéma/format2 et bornes ci-dessus. Ne restaure ni comptes ni commandes complètes et ne remplace jamais une base avec activité financière plus récente.

## État de la démonstration à la livraison

Site autorisé uniquement : LocalWP **nodevora-plugin**, `http://localhost:10004/`, id6JRuOv4ug, docroot `/Users/user/Local Sites/nodevora-plugin/app/public`, DBlocal. Aucune production ou autre site modifié.

- Plugin4.8.6 installé depuis le ZIP livré ; phase**paused**, origin**fresh**, authoritywallet, installationready (schéma prêt, pas une permission d’écriture), recovery_approvedfalse.
- Écritures financières suspendues, integrationfalse, recoveryfalse, QAfalse, cronoff, schema/migration/writers_drainedfalse, aucun gatewayQA. MU mail etHTTP bloquants conservés.
- **0 transaction active**, aucune migration/restauration/checkout en cours. **1 wallet ready de300 points**, journal d’ajustement vérifié, **2 effets outbox pending**, attempts0 (projection/notification de ce seul ajustement). Aucun effet exécuté ni purgé.
- Commandes fictives288 et289 toujours **on-hold**, aucun paiement/remboursement pour la livraison. Le dataset freshfinal est distinct des preuves cash/gain/Blocks et migration ; celles-ci restent archivées avant reset.
- Options Woo de cart/checkout/COD, WPLANG et locale opérateur remises aux valeurs sauvegardées avant le parcours commerce. Configuration Nodevora finale de démonstration earn2/redeem20/emailno conservée ; modeSimple.
- Tables financières et réglages Nodevora identiques avant/après la pause finale. Les données migrées antérieures sont dans `before-final-qa-acceptance-demo.sql`,3429835octets, SHA256 `dcb92cc4e2d92e57cd8182c24d810ccdb875b7ff19350e63ea3ae23d9c554303`.
- Backup final privé `delivery-safe-demo.sql`,2743058octets, SHA256 `78154b52f08198cb116fe559e96d598a0971b2ccdaa2f54491e06cfcf7057571` ; config finale sauvegardée. Les backups initiaux et pauses précédentes demeurent.

Tous les SQL/configs/exports et scripts privés sont horsGit dans `/Users/user/Desktop/work/nodevora-plugin/.private/mission-06D/` (dossier0700, fichiers sensibles0600). Aucun identifiant de connexion dans Git/rapports. Le ZIP n’embarque pas les pauses de la démo : un autre site jetable réellement vide suit son activation normale, sans gate locale ajoutée.

## Installation, mise à jour et reprise

1. Choisir une copie jetable sauvegardée, WooCommerce actif ; vérifier le SHA256, puis Extensions→Ajouter→Téléverser le ZIP→Installer→Activer. Un seul package Rewardly/Nodevora doit être actif.
2. Installation sans historique : Général→configurer gains/échanges, Ajustements→rechercher le compte→ajouter300 avec note. Ne pas chercher un Financial Setup technique.
3. Ancien485 avec historique : sauvegarder DB+fichiers, désactiver les anciens writers/outils, installer le dossier officiel sans désinstaller les données, ouvrir Synchronisation. Ne confirmer que l’arrêt réellement effectué des callbacks externes. Reprendre le même écran après interruption ; ne pas recréer les crédits.
4. Si preuves modifiées ou compte ambigu : conserver la pause/quarantaine, faire examiner les sources ; ne pas modifier directement wallet/journal ni revenir aux writers legacy. Les comptes cohérents peuvent progresser seulement dans le périmètre isolé prévu. Recovery se revoit séparément, aucun drain inconnu.
5. Backup format2 : Données et sauvegardes→export privé ; pour restaurer une copie compatible, preview fichier exact→confirmation→import→revue→reprise explicite. Refus sur cible active ou références incompatibles ; conserver la pause après erreur.
6. La démo de développement est volontairement arrêtée financièrement. Toute nouvelle QA doit être explicitement lancée sur fixture sauvegardée. Son opérateur lève seulement les overrides temporaires de cette démo après vérification ; **pas de nouvelle synchronisation sur un dataset déjà wallet**, pas de reprise d’outbox inconnue ni de modification d’un autre site.

Détails opérateur : `docs/installation-and-financial-readiness.md`. Scénarios et attentes pour le prochain agent : `docs/mission-06D-independent-QA.md` et copie locale `audit-reports/mission-06D-independent-QA.md`. **Cette mission ne démarre pas cette QA.**

## Critères de livraison et travail restant

Installation automatique, ajustement300, historique/synchronisation, suppression du Financial Setup, backup/import autorisés, modes/design, permissions, finance/Classic/Blocks et archive réelle : **vérifiés dans la portée documentée**. ZIP final installé/activé et intégrité vérifiée. Rapport et matrice QA préparés. Restent uniquement après ce commit les opérations de transport autorisées : push de la seule branche dev, actualisation de PR#2 en Draft et vérification SHA/main. Leur résultat exact sera inscrit au reçu et au rapport local après exécution ; ne pas interpréter cette phrase comme preuve anticipée de push.

Aucun travail d’implémentation Mission06D restant connu. Les limitations ci-dessus sont des frontières de livraison à vérifier par la QA indépendante, pas des PASS implicites. Aucun merge, changementmain/SVN, tag, publication, déploiement production ou mission suivante.

## Fichiers modifiés depuis la base Mission06D

Inventaire Git des chemins modifiés/ajoutés (les preuves privées/SQL et le ZIP local sont hors dépôt) :

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
- `docs/mission-06D-final-report.md`
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
- `tests/remediation/mission-06d-evidence/delivery-localwp-safety.json`
- `tests/remediation/mission-06d-evidence/dependencies-reinstall-486.json`
- `tests/remediation/mission-06d-evidence/final-package-manifest.json`
- `tests/remediation/mission-06d-evidence/final-qa-acceptance-prepared.json`
- `tests/remediation/mission-06d-evidence/final-qa-adjustment-300.json`
- `tests/remediation/mission-06d-evidence/final-qa-fresh-bootstrap.json`
- `tests/remediation/mission-06d-evidence/final-refund-responsive.json`
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
- `tests/remediation/mission-06d-evidence/resume-03-initial-state.json`
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
