# Mission09 — préparation release4.8.6

4 novembre2026. Candidate Developer préparée pour Mission10. **Mission09 terminée côté Developer : candidate livrée pour validation indépendante Mission10, nettoyage autorisé exécuté et vérifié.** Aucun avis de publication, merge ou production.

## Candidate

ZIP : artifacts/nodevora-customer-rewards-for-woocommerce-4.8.6-release-candidate.zip. 184238 octets,48fichiers sous le seul dossier officiel. SHA256 : **59efe948230675ab668d1f84dcb39da037bd25531dc1509a62456522db9b1c26**. Source, installation et archive identiques ; intégrité ZIP et exclusions vérifiées. Manifeste et fichier.sha256 dans artifacts.

Base0282e17b0797c50707e9037fe83f1ca6a4c9a509 ; checkpoint implémentationdea01b8 ; branchefeat/nodevora-4.8.6-financial-foundation. Aucun changement de version publique ni des minimums WordPress6.4/Woo8.2/PHP7.4. Tested up to7.1.4 et WC11.2.1 cohérents avec les preuves08B disponibles. **PHP7.4 fonctionnel non certifié**, décision du propriétaire de conserver la déclaration.

## Corrections et expérience marchande

NV08B-01 : Data & Backups masque la reprise quand WRITES_PAUSED est vrai ou INTEGRATION_ALLOWED est explicitement faux. Message court EN/FR : demander à l’administrateur de lever la pause de sécurité avant d’activer les points restaurés. Le contrôle provenance et le contrôle opérateur côté serveur sont conservés intégralement. Aucune protection n’est levée par l’interface. Quatre combinaisons des constantes testées ; refus serveur et empreintes des sept tables financières inchangés. Reprise autorisée effectuée réellement dans le navigateur, recoveryfalse.

Défaut concret découvert lors du contrôle Classic : le formulaire occupait toute la ligne flex et écrasait le résumé en une colonne de lettres. Correction CSS limitée à la ligne inférieure au bureau (grille à deux colonnes bornées). Le mobile conserve son empilement. Résumé EN/FR lisible,320/390/1280 sans débordement après stabilisation. Les premières mesures FR avant stabilisation sont conservées comme observations, pas comme résultat final.

Relecture des textes visibles Général/Design/Ajustements/Shortcodes/Advanced/Aide/Data et compte client. Changements limités aux alertes de reprise non vérifiable, échec d’opération et pause ; Aide retire un doublon et l’ancienne instruction de template gratuit, et décrit les contrôles détaillés sans promesse inutile. Les avertissements de restauration, synchronisation et suppression restent explicites. Anglais de référence ;445messages FR traduits, POT/PO/MO synchronisés et compilation check-format réussie. Les textes internes, identités historiques Rewardly, calculs et règles de remboursement restent inchangés.

README réorganisé autour des avantages, fonctionnalités, installation, FAQ concrète, captures, changelog4.8.6 et précautions de mise à jour. Description courte, URL officielle et licence cohérentes, WooCommerce déclaré comme dépendance. Le package garde le basename/dossier officiels ; aucune nouvelle mécanique d’update ni deuxième identité. L’ancienne copie Rewardly doit être désactivée avant installation officielle, sans suppression de ses données.

## Tests Developer

- NV08B-01 : quatre combinaisons EN/FR ; bouton absent sous pause, présent si autorisé ; vraie reprise UI réussie. Vues320/390/1280 EN/FR pour les deux états, aucun débordement.
- Installation : reset borné de la seule copie QA fresh après SQL privé vérifié ; install/activate du ZIP via installateur WP-CLI, ready/fresh/active, zéro wallet initial, sans parcours d’attestation technique dans le plugin ; les protections de cette copie ont été temporairement ajustées pour les tests, puis rétablies avant nettoyage. La variante finale texte/CSS a été réinstallée et réactivée ; les48fichiers correspondent exactement.
- **11 assertions smoke PASS** : crédit300/replay unique, débit119/disponible181, financement20 via callbacks Classic et Blocks, paid/retry, hold remboursement partiel malgré programme désactivé/callback tardif, invariants journal et lecture autoritative Blocks. Services/Woo CRUD réels, aucun appel de gateway externe.
- Interface : configuration minimum1 et emailsno sauvegardée ; presetSoft, HEX#234567 et passage Advanced→Simple conservés. Ajustement UI300 : wallet300, révision1, journal/historique et invariants vérifiés.
- Parcours panier réel : produit100MAD ;119points sélectionnés en Classic, remise5,95 et total94,05 ; valeurs conservées dans le panier Blocks. Aucun nouveau clic final de commande navigateur ni nouveau paiement externe revendiqué. Le compte fictif connecté affiche300 et l’ajustement unique.
- PHP :37fichiers runtime+harness PASS sur8.2.29 et8.3.30. JS runtime inchangé ; aucun nouveau lint JS revendiqué (binaire node absent). Aucune erreur fatale observée dans les parcours exécutés.
- Aucun replay inutile des missions précédentes ni nouvelle QA indépendante. Preuves dans evidence ; tests permanents mission-09-gate.php et mission-09-smoke.php.

## Plugin Check2.1.0

Archive officielle WordPress.org, exécutée sur la candidate installée en copie jetable : catégories proposées General/Plugin Repo/Security/Performance/Accessibility, vérifications statiques et bootstrap runtime officiel via --require=plugin-check/cli.php. Aucune exclusion/ignore-code. Exit0 n’est pas assimilé à une absence de résultats.

Initial :35ERROR/85WARNING. Après corrections : **30ERROR/84WARNING**, y compris sur le ZIP final. Fichiers bruts et normalisation par fichier dans evidence. Corrections réelles :3commentaires traducteur manquants, échappement explicite de2attributs déjà issus d’une liste fixe, variable locale uninstall préfixée. Aucun changement du cœur financier pour satisfaire un scanner.

Triage des30ERROR restants :22ExceptionNotEscaped concernent la construction d’exceptions, pas une sortieHTML (rendu de notices échappé) ;2OutputNotEscaped concernent un attribut disabled construit uniquement avec des littéraux ;5alertes fopen/fwrite/fclose concernent des flux privés bornés d’export/import ;1mysqli_init concerne la connexion dédiée aux transactions, que remplacer par la connexion WordPress partagée modifierait l’architecture. Ces observations ne sont pas supprimées du résultat brut ; revue indépendante recommandée, pas de certificat WordPress.org sans réserve.

Triage des84WARNING :40nonceMissing et5nonceRecommended sur entrées dont le garde nonce/capability se trouve en amont ou dont le résultat est non mutateur ;15InputNotSanitized/13MissingUnslash sur comparaisons strictes, nombres validés et upload vérifié (ne pas passer un contenuNDJSON dans sanitize_text_field) ;4DirectQuery/4NoCaching sur vérifications SQL de persistance et lectures contrôlées ;2meta_key sur migration bornée ;1load_plugin_textdomain pour le catalogue embarqué de cette candidate hors diffusion WordPress.org. Aucun nouveau défaut exploitable confirmé par ces alertes dans les chemins examinés ; ne pas convertir cette analyse en validation de toutes les entrées possibles.

Documentation officielle consultée : https://github.com/WordPress/plugin-check/blob/trunk/docs/CLI.md ; source et bootstrap runtime inclus dans le plugin officiel. Plugin Check ne remplace ni une revue WordPress.org ni une certification complète des Coding Standards.

## Assets et workspace

Assets préparés dans github/wordpress-org-assets, exclus du ZIP : icônes128/256, bannières772×250/1544×500 et6captures actuelles correspondant au README. PNG originaux copiés en lecture depuis SVN ; captures nativesJPEG correctement nommées, non recompressées. Manifestes dimensions/SHA. Anciennes illustrations non présentées comme interface actuelle. Capture6 : Nodevora FR, Woo EN ; seule la traduction du plugin est qualifiée.

Structure conservée : github(code/tests/build/docs/assetsWordPress.org), artifacts(RC/manifeste/SHA), audit-reports(rapports/preuves historiques etMission09), .private(archives protégées hors périmètre), svn(strictement intact).

**Nettoyage terminé après confirmation explicite dans le chat du propriétaire.** Le premier refus automatique a été respecté : aucune suppression avant cette confirmation. Les13identités, leurs URLs/bases/chemins, pauses et absence de transactions ont été revérifiées. Les11anciens SQL non modifiés correspondent aux empreintes08B ; fresh et ui ont reçu des exports à jour. Les13SQL sont conservés dans une archive privée unique de740632octets, entièrement relue et vérifiée, hors des dossiers supprimés : `.private/mission-09-cleanup-backups/qa-final-snapshots.tar.gz`, SHA256 `bd07b45c658dd6eda673a95b09402f60bb518e426ea75354d638a804a4d8f990`.

Suppressions effectivement exécutées, chacune enregistrée dans `evidence/cleanup-ledger.json` :

| Copie supprimée | Base supprimée | Port vérifié fermé |
|---|---|---:|
|fresh|nodevora_m08b_fresh|18301|
|target1|nodevora_m08b_target1|18302|
|target2|nodevora_m08b_target2|18303|
|work|nodevora_m08b_work|18304|
|legacy|nodevora_m08b_legacy|18305|
|ui|nodevora_m08b_ui|18306|
|capacity|nodevora_m08b_capacity|18307|
|legacyorder|nodevora_m08b_legacyorder|18308|
|emptyupgrade|nodevora_m08b_emptyupgrade|18309|
|orderupgrade|nodevora_m08b_orderupgrade|18310|
|restoreextra|nodevora_m08b_restoreextra|18311|
|volume|nodevora_m08b_volume|18313|
|minimum|nodevora_m08b_minimum|18312|

Les13dossiers sous `.private/mission-08B/` ont été supprimés, ainsi que les80éléments associés inventoriés (SQL/NDJSON/configurations/logs et extraction temporaire). Le dossier08B devenu vide a été retiré. Le staging SQL créé pour la sauvegarde a été retiré après vérification de sa conservation exacte dans l’archive privée. Aucun payload inconnu ni extérieur à l’inventaire n’a été purgé.

Les deux ZIP QA obsolètes ont été identifiées par leur SHA avant suppression :
- `nodevora-customer-rewards-for-woocommerce-4.8.6-qa.zip` :182492octets, SHA256 `11047f9f72a787e751975307f5c8872343275c7b2225ec2766a98aaeafb46496`.
- `nodevora-customer-rewards-for-woocommerce-4.8.6-qa-m08a.zip` :185392octets, SHA256 `526f8b53d5185d81baa64fed4fa099447267b68f21763159937c1b24a476a5a8`.

Leurs rôles et empreintes restent documentés dans les rapports historiques inchangés et le journal09. Les anciens liens vers ces deux archives ou vers les environnements08B ne désignent plus des fichiers présents ; ils sont désormais des références historiques, pas des instructions de reprise.

Vérification après suppression :13bases/dossiers absents,13ports fermés, zérotransactionQA. Le jeu de toutes les autres bases est **exactement égal** au jeu initial moins les13bases autorisées. L’état/réglages/empreintes financières du primaire et ses48fichiers sont inchangés. Les archives privées06D/08A et les assetsSVN sont conservés et leurs empreintes contrôlées. L’archive de sauvegarde09 et le ZIPRC ont été relus et vérifiés.

Structure de livraison conservée :
- `github/` : source, historiqueGit, tests/build permanents, documentation et `wordpress-org-assets/` séparé du runtime.
- `artifacts/` : uniquement le ZIPReleaseCandidate, son fichierSHA256 et `release-candidate-manifest.json`.
- `audit-reports/` : rapports et preuves historiques nécessaires, rapports09 et checklist10. Aucun rapport historique supprimé.
- `.private/mission-06D/` et `.private/mission-08A/` : archives précédentes conservées explicitement à la demande du propriétaire ; pas de nettoyage élargi à ces ressources.
- `.private/mission-09-cleanup-backups/` : une archive froide vérifiée des13SQL, sans serveur ni base active.
- `svn/` : strictement conservé, aucune opération de publication.

Le nettoyage est limité à l’inventaire autorisé ; aucun autre projet ou siteLocalWP n’a été supprimé.


Le primaire10004 a été comparé en lecture à primary-before08B : état/réglages/empreintes financières exactement égaux et48fichiers d’origine égaux. Aucun accès modifiant le primaire. Autres projets/sites, main et SVN préservés.

## Limites et suite

PHP7.4 fonctionnel non testé ; CWV/Lighthouse toujours non qualifiés ; capacité100000enregistrements non certifiée (résultats bornés08B conservés). Multisite, passerelles/SMTP externes, production, reprise de sauvegardes sans provenance fiable et remboursement proportionnel restent hors certification. Les archives SHA vérifient l’intégrité, pas une signature d’auteur. Les réserves08B acceptées par le propriétaire ne deviennent pas des PASS supplémentaires.

Mission10 doit valider indépendamment cette nouvelle empreinte, le gate opérateur, le CSS Classic et les scénarios d’acceptation. Mission10 non lancée. PR#2 doit rester Draft. Aucun merge/tag/publication/release ou SVN. La branche de développement seule est sauvegardée et la PR#2 reste Draft. Le commit final est le commit contenant cette version du rapport ; le reçu local de livraison enregistre son SHA exact après création et les vérifications distantes. Main reste `eca72d6c08184ed5dd7e28b4e52abc98ebf342ef`. Aucun tag ni publication. Mission09 est terminée ; aucun travail Mission10 n’est lancé automatiquement.
