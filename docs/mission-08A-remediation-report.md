# Mission 08A — targeted developer remediation

**3 novembre 2026 — DEVELOPER REMEDIATION VERIFIED; INDEPENDENT RETEST REQUIRED.**

Les deux défauts confirmés NV07-03 et NV07-02 ont été reproduits puis corrigés et vérifiés dans de nouvelles copies jetables. Aucun nouveau périmètre produit. La version reste4.8.6. Le verdict indépendant de Mission07 reste **QA CONDITIONAL** jusqu’à Mission08B : ces contrôles sont des tests Developer, pas une certification indépendante ou une autorisation de publication.

## Référence et artefact

| Élément | Valeur |
|---|---|
| Base Developer | 8e795f58fcdcf54bcd8124a120d39dfcb79ffdd8 |
| Branche | feat/nodevora-4.8.6-financial-foundation |
| Checkpoint implémentation | 3c137ed — WIP cohérent, suivi du commit de livraison contenant ce rapport |
| Commit final versionné | Le commit contenant ce rapport : git log -1 --format=%H -- docs/mission-08A-remediation-report.md. Le SHA exact et le résultat de transport sont ajoutés au rapport local hors Git et au reçu après création/push |
| PR | https://github.com/DELTAWEBMAROC/rewardly-loyalty/pull/2 — Draft, jamais fusionnée |
| Main local/distant avant transport | eca72d6c08184ed5dd7e28b4e52abc98ebf342ef |
| Ancienne ZIP conservée | artifacts/nodevora-customer-rewards-for-woocommerce-4.8.6-qa.zip,182492octets/48fichiers |
| Ancien SHA256 inchangé | 11047f9f72a787e751975307f5c8872343275c7b2225ec2766a98aaeafb46496 |
| Nouvelle ZIP | /Users/user/Desktop/work/nodevora-plugin/artifacts/nodevora-customer-rewards-for-woocommerce-4.8.6-qa-m08a.zip |
| Taille / fichiers | **185392 octets /48 fichiers** |
| Nouveau SHA256 | **526f8b53d5185d81baa64fed4fa099447267b68f21763159937c1b24a476a5a8** |

La nouvelle ZIP a réellement été téléversée, installée et activée depuis WordPress sur une copie sans Nodevora. Schéma vérifié, active/fresh/wallet, zéro wallet initial et aucune autorisation financière technique. Source/install/ZIP correspondent octet pour octet. Dossier officiel unique, version/header/constante/readme4.8.6 cohérents, intégrité ZIP valide, aucun fichier privé/log/SQL/credential/fixture/test/.git/__MACOSX. Aucun fichier ajouté ou supprimé par rapport à06D ; **sept fichiers modifiés**, détaillés avec tailles et empreintes dans evidence/package-validation.json. La ZIP n’est pas un asset de release publié.

## Références lues et reproduction avant code

Lecture des rapports, findings et matrice finaux Mission07 et du rapport06D ; consultation des preuves originales de perte de provenance et refus SQL, du harness SQL et des classes concernées. Mission07 :50PASS/2FAIL/1BLOCKED/10NOT TESTED/1N/A, pas de Critical/High. Ses résultats n’ont pas été importés comme nouveaux PASS et ses fichiers n’ont pas été modifiés.

Les reproductions08A ont utilisé le ZIP d’origine exact avant toute modification runtime :
- NV07-03 : export actif, import, réexport pendant restore_review, seconde cible vide puis import ; le service refuse la reprise avec « Complete synchronization before enabling restored accounts. ». restore_previous_phase devient restore_review. Solde300, empreintes et invariants inchangés : evidence/nv07-03-before.json.
- NV07-02 : vrai trigger SQL BEFORE UPDATE limité à rewardly_loyalty_settings, nonce et administrateur valides ; HTTP302/settings-updated=true, anciennes valeurs conservées, aucune notice dans le panneau ni SQL brut : evidence/nv07-02-before.json.

La reproduction SQL08A est sur WP7.1.4/PHP8.2.29, et non sur la pile minimale WP6.4 du constat07. Le même défaut de flux options.php/panneau est confirmé. Aucun nouveau résultat WP6.4 n’est revendiqué.

## NV07-03 — FIXED & VERIFIED dans les conditions justifiables

### Cause racine

L’export format2 conserve l’option d’état courante. apply() remplaçait à chaque import restore_previous_phase par la phase immédiate de cet export. Un second export pris pendant restore_review écrasait donc la racine initiale active/paused. resume_reviewed() exigeait ensuite cette racine, tandis que l’UI proposait toujours sa reprise. La fermeture sûre fonctionnait, mais le parcours acceptait une sauvegarde sans fournir de sortie opérationnelle.

### Correction minimale et frontières

- class-nodevora-restore.php conserve une **provenance plate et bornée**, sans accumulation d’objets de revues imbriquées : racine active/paused et autorité source documentée dans restore_previous_authority.
- La phase seule n’est pas une preuve : autorité wallet, origine fresh/synchronized, cohérence des marqueurs de restauration et absence de migration incomplète/batch restant sont contrôlées. Une racine non justifiée est vide et ne devient jamais une autorisation.
- Chaque nouvel import revient en restore_review, recoveryfalse. Il faut une nouvelle revue explicite, même si une revue précédente avait eu lieu. Schéma, soldes/journaux/lots/snapshots, références et gate opérateur restent vérifiés au service.
- class-nodevora-data.php n’affiche plus le bouton de reprise lorsque le service doit refuser. Un message EN/FR demande de garder la pause et d’utiliser la sauvegarde originale d’un wallet terminé actif/suspendu, ou une réconciliation administrateur. Aucun fichier historique n’est réparé ou réécrit.
- Mutex, transaction, barrière des writers, cible vide, identités, import atomique, quarantine, holds et idempotence ne changent pas. Aucun solde n’est modifié arbitrairement et aucune file n’est exécutée.

| Source de l’export | Import / reprise |
|---|---|
| active, wallet terminé vérifiable | restore_review ; nouvelle revue puis active autorisé |
| paused, wallet terminé vérifiable | même revue, reprise seulement si le gate opérateur de la cible l’autorise |
| restore_review avec racine/autorité conservées | nouvelle restore_review, racine conservée ; reprise après nouvelle revue |
| prepared/migrating, migration incomplète, origine/autorité inconnues ou marqueurs incohérents | données préservées en revue, reprise refusée explicitement |
| ancien réexport dont l’autorité/racine n’est plus démontrable | **pas de reconstitution automatique de confiance** ; utiliser l’original valide ou faire réconcilier |
| cible contenant déjà des données/transactions Nodevora | preview/import refusés sans écrasement |
| panne SQL pendant insertion | rollback intégral, phase restoring conservée ; retry contrôlé sans doublon |

Les vieux exports pris pendant une revue ne portent pas nécessairement la preuve d’autorité introduite par le correctif. Ceux déjà privés de provenance ne deviennent pas automatiquement récupérables. C’est un refus de sécurité documenté, pas un PASS fictif de reprise de tous les fichiers.

### Preuves réellement exécutées

**52 contrôles** de provenance/reprise : active, paused, trois revues successives, import répété, provenance incohérente même avec footer recalculé, migration incomplète, autorités source/cible invalides, quarantine inchangée, scheduled recovery non approuvée et outbox unknown/pending sans tentative, corruption/hash modifié, vrai SIGNAL SQL pendant insertion d’événements, rollback des wallets/événements, reprise et égalité des tables/règles. evidence/mission-08a-restore-after.json.

**35 contrôles Format2** relancés dans08A : références utilisateurs/commandes/items/commentaires/posts, tombstones, métadonnées propriétaires, exclusions, versions/schéma/plugin, objets sérialisés/doublons, corruption/troncature, rollback, cache, invariants et refus d’import dangereux/retry. evidence/mission-08a-format2-after.json. Il ne s’agit pas des anciens résultats06D recopiés.

Gate opérateur testé dans un processus administrateur séparé, sans modifier l’override du primaire : refus et état inchangé, evidence/operator-override-after.json.

**Interface réelle :** l’ancien réexport sans provenance vérifiable affiche l’alerte et zéro bouton de reprise (legacy-review-blocked-ui.png). Pour un nouveau réexport valide : upload, preview77records/46790octets, confirmation, import, nouvelle revue et bouton Enable reviewed points exécutés dans le navigateur. Résultat active, recoveryfalse ; toutes tables financières, références, métadonnées/options propriétaires hors état de contrôle et invariants des wallets présents identiques. evidence/restore-ui-after.json, reexport-import-review-ui.png et reexport-resumed-ui.png.

## NV07-02 — FIXED & VERIFIED

### Cause racine

options.php redirige avec settings-updated=true sans établir que la valeur voulue est effectivement persistée. Le panneau Nodevora ne rendait pas de résultat explicite. Afficher simplement le message générique du cœur risquait d’annoncer « enregistré » lors du refus SQL. Le retour false d’update_option() ne suffit pas non plus : il peut indiquer une valeur déjà identique.

### Correction minimale

- Le flux **Settings API/options.php**, ses capabilities, POST/nonces et le sanitiseur de sections existant sont conservés.
- Le sanitiseur capture la valeur normalisée attendue uniquement pour la soumission valide de ce groupe. Une lecture directe de l’option avant/après persistance évite de prendre le cache pour une preuve SQL.
- Au redirect, comparaison exacte de la valeur réellement stockée : saved, unchanged, failed ou invalid. Le résultat n’est pas déduit du booléen d’update_option().
- Seul un code de résultat et un nonce lié à la session vont dans l’URL, jamais les réglages ou diagnostics SQL. Le panneau rend une notice échappée et localisée ; une query de succès non signée n’est pas affichée.
- Les valeurs invalides restent refusées par les règles existantes ; cette mission n’ajoute ni nouveaux taux ni nouvelles règles commerciales. La première création d’option et le second passage du sanitiseur WordPress sur sa forme complète ont été testés, pour éviter une fausse alerte sur les clés internes conservées.
- Les réglages des autres sections restent conservés. La normalisation monétaire préexistante 0→0.00 est reconnue comme même valeur, pas une perte de préférence.

Messages français : « Vos réglages ont été enregistrés. » ; « Ces réglages sont déjà enregistrés. Aucune modification n’était nécessaire. » ; « Vos réglages n’ont pas pu être enregistrés. Rechargez cette page et réessayez, ou contactez l’administrateur du site. » ; et message de valeurs invalides. Les versions anglaises sont dans le même catalogue.

### Preuves réellement exécutées

**55 assertions HTTP** dans08A : première sauvegarde, normale, identique, identique avec trigger actif, refus SQL réel General/Design EN/FR, réglages Simple/Advanced, sections voisines, couleurs invalides/section invalide, nonce invalide, succès forgé refusé, Shop Manager autorisé, Editor/Customer avec leur propre nonce valide refusés, restriction ON retirant le droit Manager. evidence/settings-after.json.

Deux soumissions **navigateur réelles**, en EN puis FR, sous trigger SQL ciblé, montrent l’erreur compréhensible et gardent #234567 en base et rendu. Aucun diagnostic SQL interne. Trigger retiré immédiatement : settings-ui-after.json, settings-ui-preservation.json, settings-sql-failed-en-ui.png, settings-sql-failed-fr-ui.png. Les sauvegardes originales et sections voisines ne sont pas modifiées pour simuler le résultat.

## Non-régression financière et package

**11 assertions smoke nouvelles**, après restauration : crédit300/replay sans second crédit, débit119/disponible181, financement20points via chaque callback Classic et Blocks, paid/replay sans second débit, remboursement partiel conservé en hold malgré programme désactivé/callback retardé, invariants, registration du bloc et lecture de balance autoritative. Ce sont des services/WC CRUD et callbacks réels ; **aucun nouveau parcours de commande navigateur ou appel de gateway/paiement n’est revendiqué**. evidence/financial-smoke-after.json.

Syntaxe finale :7fichiers PHP(runtime modifié+tests) sur PHP8.2.29 et8.3.30 ;2harness Python ;443 messages français compilés et check-format, MO identique au PO compilé. Aucun JS modifié : lint JS N/A, pas un PASS de replay global. evidence/final-validation.json.

L’installateur WordPress du ZIP correctif a réellement installé/activé le plugin dans la troisième nouvelle copie, sans opt-in financier. Les48fichiers sont égaux à la source et à l’archive ; le dossier est officiel. evidence/package-validation.json et candidate-installed-active-ui.png.

## Erreurs de mise au point et résultats non revendiqués

Un harness tentait CREATE/DROP TRIGGER sur l’API financière préparée : DDL déplacé vers wpdb, sans modifier cette API. Une sonde d’override sans contexte administrateur a été corrigée. Une erreur de syntaxe Python a précédé toute requête de sa suite. Le smoke demandait un redispatch interdit, puis comparait à tort la révision avant le premier contrôle paid zéro-valeur ; il vérifie maintenant le premier paid et son replay. Un contrôle de voisinage traitait la normalisation0→0.00 comme une perte : comparaison de valeur corrigée. La vérification UI cherchait un wallet d’opérateur absent : elle contrôle les wallets réellement présents.

Un lancement de harness partageant restore a été abandonné comme preuve et les suites finales ont été rejouées **séquentiellement**. Ces échecs de harness ne sont ni masqués ni comptés PASS initiaux. Les JSON finaux indiquent les seules exécutions retenues. Aucun contrôle financier produit n’a été affaibli pour satisfaire un test. Détails : evidence/harness-corrections.md et logs privés.

## Environnements, sécurité finale et conservation

Nouvelles copies uniquement, dans /Users/user/Desktop/work/nodevora-plugin/.private/mission-08A/ (dossier0700, SQL/configs/auth/NDJSON privés0600). Chaque base est distincte ; aucun ancien site QA réinitialisé. WordPress7.1.4/Woo11.2.1, thème Twenty Twenty-Five, PHP8.3.30 pour restore/package et8.2.29 pour settings. HPOS activé sur les copies de régression.

Le dossier temporaire privé de Mission07 /private/tmp/nodevora-m07-n4s5q4qv était déjà absent au démarrage08A. Cela limite la récupération de ses anciens SQL/NDJSON ; **08A ne les a ni supprimés ni remplacés**. Ses rapports, preuves publiques et ZIP d’origine sont conservés. Les reproductions08A ont leurs propres bases/snapshots durables.

Clôture vérifiée : toutes les nouvelles copies paused, écritures suspendues, integrationfalse, recoveryfalse, QAfalse, cronoff, aucun trigger ni transaction active. Seul MU de blocage mail/HTTP conservé ; aucun email/paiement externe. Sessions fictives révoquées, serveurs18201/18202/18203 arrêtés, onglets temporaires fermés. Aucune outbox purgée ou exécutée.

| Copie | État final préservé |
|---|---|
| restore18201 |3wallets ready, total602points ;23effets pending(15project/8notify), attempts0 ; ordres fictifs en attente |
| settings18202 |3wallets ready de0point ; outbox vide ; préférences de test conservées |
| package18203 |fresh, zéro wallet/outbox, candidate installée puis suspendue |
| primaire10004 |**exactement inchangé** : phase/settings/empreintes financières et48fichiers runtime d’origine ;300points,2pending/attempts0, zéro transaction |

La pause finale ne change aucune table financière ; invariants des wallets ready vérifiés. Les snapshots SQL après pause et leurs SHA256 sont dans evidence/final-safety.json, contenus hors Git. Le primaire n’a reçu aucune écriture de configuration ni de source ; ses lectures de vérification sont séparées des opérations des copies.

## Limites et risques résiduels

1. Retest indépendant08B requis. Les10NOT TESTED et CWV BLOCKED de Mission07 restent **inchangés** ; aucun benchmark ou audit général nouveau n’est revendiqué. NV07-01INFO, ambiguïté de pause/synchronisation, reste hors des deux corrections demandées.
2. Les vieux réexports sans provenance/autorité démontrables restent fermés. Ne pas éditer leur provenance ; utiliser l’original valide ou réconcilier sous contrôle. Le correctif ne fabrique pas une confiance perdue.
3. Format2 conserve son modèle d’export administrateur : SHA vérifie l’intégrité, pas une signature d’auteur. Les tests refusent les provenances incohérentes, y compris avec un footer recalculé ; ils ne certifient pas l’authenticité d’un fichier entièrement forgé et cohérent. Utiliser uniquement des originaux vérifiés et de confiance.
4. PHP7.4 n’est pas disponible comme runtime testé08A ; support minimum déclaré inchangé, à clarifier dans08B. Pas de nouveau test08A WP6.4/Woo8.2 ; les preuves07 restent distinctes. Aucun élargissement des revendications.
5. Multisite, gateways/SMTP externes, production, remboursement proportionnel et réconciliation de valeur ambiguë restent non certifiés/hors périmètre. Aucun nouveau comportement commercial.
6. Les copies et snapshots08A sont une preuve Developer à conserver, pas un remplacement des preuves07 ni de la future vérification indépendante.

## Fichiers modifiés et justification

- includes/financial/class-nodevora-restore.php : provenance bornée/autorité et garde commune de reprise.
- includes/admin/class-nodevora-data.php : action de reprise seulement quand justifiable, explication sinon.
- includes/admin/class-rewardly-loyalty-settings.php : preuve de persistance réelle, distinction inchangé/erreur/invalides, notices signées/localisées.
- includes/admin/class-rewardly-loyalty-admin.php : rendu de cette notice.
- Trois catalogues POT/PO/MO :5nouveaux messages EN/FR, aucun changement de calcul.
- Tests permanents mission-08a-restore.php, mission-08a-format2.php, mission-08a-smoke.php, mission-08a-settings.py et mission-08a-driver.py ; rapports et sélection de preuves sans secrets.

Aucun autre fichier runtime ne diffère du ZIP06D. Wallet/journal/lots/allocations, politiques, taux, HPOS, identités et slug ne sont pas refactorisés.

## Réexécution et suite

Les tests permanents sont dans github/tests/remediation/. Le driver HTTP lit un manifeste privé de nouvelles copies avec bases nodevora_m08a_*, chemins sous .private/mission-08A et ports18201–18203 ; il refuse le primaire. Les chemins Local/WPCLI/socket sont configurables par NODEVORA_WORKSPACE, NODEVORA_WP_CLI_PHAR, NODEVORA_MYSQL_SOCKET et NODEVORA_MYSQL_BIN. Les tests PHP exigent aussi WP_CLI, la copie18201 et M08A_PRIVATE. **Créer/sauvegarder une copie jetable, ne pas lancer ces tests de mutation sur un site réel ou une ancienne preuve.** Le dossier contient les snapshots/manifeste nécessaires à une reprise explicite des seules copies08A, actuellement arrêtées.

Pour08B : prendre le nouveau SHA256, refaire indépendamment les deux reproductions et refus, vérifier chaque état/autorité/review, SQL success/identique/erreur, permissions, puis évaluer les réserves07 prioritaires. Ne pas convertir les résultats Developer en verdict QA. **08B n’est pas lancée par cette mission.**

Après le commit de livraison : push de la seule branche de développement, mise à jour de PR#2 maintenue Draft, vérification SHA distant et main. Le reçu local enregistre le résultat effectif. Aucun merge, tag, SVN, publication, déploiement ou production.
