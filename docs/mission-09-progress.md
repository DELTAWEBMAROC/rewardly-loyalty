# Mission 09 — en cours

Base 0282e17b0797c50707e9037fe83f1ca6a4c9a509 ; branche feat/nodevora-4.8.6-financial-foundation.
Références 06D/07/08A/08B consultées. Correction UI NV08B-01 en cours. PHP 7.4 conservé.
Aucune suppression effectuée. Les 13 copies 08B sont inventoriées dans .private/mission-08B/fixtures.json. Aucun accès modifiant le primaire, main ou SVN.
Prochaines actions : tests ciblés UI/gate, textes/traductions/readme, Plugin Check, package et tests, puis nettoyage vérifié et livraison.

## Checkpoint implémentation
NV08B-01 : quatre combinaisons de protections EN/FR PASS ; serveur refusé et empreintes des sept tables financières conservées. UI réelle à 320/390/1280 EN/FR, refus et action disponible : aucun débordement.
README commercial simplifié, métadonnées cohérentes, PHP 7.4 inchangé. Catalogue 446 messages FR compilés. Commentaires traducteur/échappement attributs fixes et préfixe variable uninstall corrigés après Plugin Check. Pas de changement du calcul financier.
RC provisoire construite : a7d1c35d0a0e7372dbdf4ec4dcea3b2ad50451cc7751f2dd51e65b2942e08f0c ; 184155 octets / 48 fichiers. Installation/activation réelle via WP-CLI dans fresh : ready/fresh/active, zéro wallet initial. Aucune certification finale avant tous les contrôles.
Plugin Check : initial 35 ERROR/85 WARNING, après corrections 30 ERROR/84 WARNING ; triage détaillé à terminer, ne pas déclarer un scan sans erreurs.
Copie ui temporairement autorisée (aucune reprise soumise), fresh smoke en cours ; cron et communications externes bloqués. Primaire inchangé. 13 copies vérifiées ; AUCUNE suppression encore.
Reste : fin smoke/design/modes/configuration, assets, vérification finale package/syntaxe, triage Plugin Check, sécurité/cleanup 13 bases+sites, rapports finaux, commit/push/PR Draft.

## Validation finale avant nettoyage
Correctif complémentaire concret découvert en Classic : formulaire à largeur pleine écrasant le résumé à quelques pixels. Grille limitée au bureau, aucune règle de points modifiée. Vérifié EN/FR à 320/390/1280 ; les mesures FR prises avant stabilisation ont été conservées puis remplacées comme preuve par classic-responsive-fr-stable.json (aucun overflow après stabilisation).
Help : retrait d’un doublon et de la consigne obsolète de template gratuit ; deux phrases EN/FR clarifiées. Catalogue final : 445 traductions.
RC finale 184238 octets / 48 fichiers, SHA256 59efe948230675ab668d1f84dcb39da037bd25531dc1509a62456522db9b1c26. Réinstallation et activation réelle de cette variante dans fresh. Source/install/ZIP identiques ; 37 PHP sur 8.2 et 8.3 PASS. JS inchangé ; binaire node absent, aucun nouveau lint JS revendiqué.
11 smoke PASS, ajustement UI300 / wallet et invariants PASS ; Classic→Blocks conserve119 /5,95MAD /94,05MAD, sans nouvelle commande navigateur. Modes et preset/HEX sauvegardés. Captures actuelles dans github/wordpress-org-assets/, séparées du runtime.
Plugin Check2.1.0, statique et runtime officiels : 30ERROR /84WARNING ; aucun masquage de code. Rapport de triage à finaliser. Le site principal correspond exactement à primary-before08B et à ses48fichiers d’origine. Serveur QA18306 arrêté.
Sécurisation pré-nettoyage des13copies en cours, preuves enregistrées progressivement dans pre-clean-safety.json. Aucune suppression encore à ce checkpoint.

## Checkpoint contrôle automatique
Nettoyage refusé AVANT création/lancement du script : il exige une autorisation explicite dans le chat pour ces13copies. Aucun dossier/base supprimé. Toutes13copies sécurisées ;18306 arrêté. Rapports/liste de ressources et candidate finale prêts.
Prochaine action précise : obtenir confirmation chat pour les13noms du plan, puis seulement supprimer leurs bases/dossiers/payloads et anciens ZIPQA inventoriés ; vérifier absence et primaire inchangé ; compléter rapports, commit final/push/PRDraft.
