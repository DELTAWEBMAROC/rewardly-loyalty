# Mission09 — préparation release4.8.6

4 novembre2026. Candidate Developer préparée pour Mission10. **Livraison finale en attente du nettoyage obligatoire : confirmation humaine demandée par la validation automatique.** Aucun avis de publication, merge ou production.

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
- Installation : reset borné de la seule copie QA fresh après SQL privé vérifié ; install/activate du ZIP via installateur WP-CLI, ready/fresh/active, zéro wallet initial, sans opt-in opérateur technique sur cette copie. La variante finale texte/CSS a été réinstallée et réactivée ; les48fichiers correspondent exactement.
- **11 assertions smoke PASS** : crédit300/replay unique, débit119/disponible181, financement20 via callbacks Classic et Blocks, paid/retry, hold remboursement partiel malgré programme désactivé/callback tardif, invariants journal et lecture autoritative Blocks. Services/Woo CRUD réels, aucun appel de gateway externe.
- Interface : configuration minimum1 et emailsno sauvegardée ; presetSoft, HEX#234567 et passage Advanced→Simple conservés. Ajustement UI300 : wallet300, révision1, journal/historique et invariants vérifiés.
- Parcours panier réel : produit100MAD ;119points sélectionnés en Classic, remise5,95 et total94,05 ; valeurs conservées dans le panier Blocks. Aucun nouveau clic final de commande navigateur ni nouveau paiement externe revendiqué. Compte client affiche300 et l’ajustement unique.
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

**Nettoyage : BLOCKED par validation automatique avant toute exécution. Zéro site/base supprimé.** Les13copies à supprimer, leurs bases/chemins/ports et protections sont listés dans mission-09-cleanup-plan.md et cleanup-inventory-before.json. Elles sont toutes en pause, QA/recovery désactivées, zérotransaction/trigger ;18306 arrêté. Les payloads08B et anciens ZIPQA restent en place jusqu’à confirmation. La liste des suppressions réellement exécutées sera ajoutée après cette autorisation ; aucune suppression fictive n’est annoncée.

Le primaire10004 a été comparé en lecture à primary-before08B : état/réglages/empreintes financières exactement égaux et48fichiers d’origine égaux. Aucun accès modifiant le primaire. Autres projets/sites, main et SVN préservés.

## Limites et suite

PHP7.4 fonctionnel non testé ; CWV/Lighthouse toujours non qualifiés ; capacité100000enregistrements non certifiée (résultats bornés08B conservés). Multisite, passerelles/SMTP externes, production, reprise de sauvegardes sans provenance fiable et remboursement proportionnel restent hors certification. Les archives SHA vérifient l’intégrité, pas une signature d’auteur. Les réserves08B acceptées par le propriétaire ne deviennent pas des PASS supplémentaires.

Mission10 doit valider indépendamment cette nouvelle empreinte, le gate opérateur, le CSS Classic et les scénarios d’acceptation. Mission10 non lancée. PR#2 doit rester Draft. Aucun merge/tag/publication/release ou SVN. Finalisation Git/push/PR et nettoyage à consigner au reçu de clôture après confirmation.
