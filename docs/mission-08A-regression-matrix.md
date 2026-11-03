# Mission 08A — matrice de régression Developer

3 novembre 2026. Chaque PASS est un résultat 08A. Les groupes se recoupent ; ne pas les additionner comme des scénarios QA indépendants. Un refus attendu est un PASS de protection, pas une reprise réussie.

| Groupe | Cas et résultat observé | Statut | Preuve dans evidence/ |
|---|---|---|---|
| NV07-03 avant | Perte de racine après réexport review ; reprise refusée ; solde et invariants conservés | FAIL reproduit avant correction | nv07-03-before.json |
| Source active | Import, nouvelle revue et reprise explicite | PASS | mission-08a-restore-after.json |
| Source paused | Wallet terminé, reprise soumise au gate opérateur | PASS | mission-08a-restore-after.json |
| Revues successives | Trois réexports de revue : racine/autorité conservées, nouvelle revue à chaque fois | PASS | mission-08a-restore-after.json |
| Provenance non justifiable | Migration incomplète, racine incohérente, origine inconnue, legacy perdue, autorité source/cible non validée | PASS refus ; vieux fichiers non prouvables toujours bloqués | mission-08a-restore-after.json |
| Gate opérateur | Override interdit l’activation, état inchangé | PASS | operator-override-after.json |
| Quarantine / recovery | Compte bloqué conservé ; recoveryfalse ; outbox unknown/pending sans tentative | PASS | mission-08a-restore-after.json |
| Imports dangereux | Cible active, doublons/retry, corruption et hash modifié refusés | PASS | mission-08a-restore-after.json |
| SQL import | Vrai SIGNAL pendant insertion event : rollback complet, pause et retry sans duplication | PASS | mission-08a-restore-after.json |
| Format 2 | 35 contrôles : identités/références/tombstones/métadonnées/options/objets/versions/cache/rollback/invariants | PASS | mission-08a-format2-after.json |
| UI legacy | Alerte explicite et zéro bouton pour ancien réexport sans provenance vérifiable | PASS refus | legacy-review-blocked-ui.png |
| UI nouveau réexport | Upload/preview77records/import/revue/bouton reprise ; données/références identiques ; active/recoveryfalse | PASS | restore-ui-after.json ; reexport-*.png |
| NV07-02 avant | Vrai refus SQL sans notice ; anciennes valeurs conservées | FAIL reproduit avant correction | nv07-02-before.json |
| Settings normal / identique | Première création et update, Simple/Advanced/Design EN/FR, identiques avec/sans trigger | PASS | settings-after.json |
| Settings panne | Vrai refus SQL General/Design EN/FR ; erreur claire, valeurs et sections voisines conservées | PASS | settings-after.json |
| Settings invalides | HEX/section invalide, nonce invalide et succès query forgé refusés | PASS | settings-after.json |
| Droits | Manager autorisé ; Editor/Customer avec nonce valide refusés ; restriction ON bloque Manager | PASS | settings-after.json |
| UI panne SQL | Deux POST navigateur EN/FR sous trigger, #234567 conservé, pas de SQL brut, trigger retiré | PASS | settings-ui-after.json ; settings-ui-preservation.json ; settings-sql-failed-*.png |
| Smoke financier | 300/replay, débit119, funding20 Classic/Blocks, paid/retry, hold malgré disabled/callback, invariants/Blocks | PASS — 11 assertions services/WC CRUD | financial-smoke-after.json |
| Syntaxe | 7 PHP sur8.2.29/8.3.30, 2 Python, 443 traductions compilées | PASS | final-validation.json |
| JS | Aucun fichier modifié | N/A lint modifié | package-validation.json |
| Package | 185392octets/48fichiers, 7modifiés/0ajout/0suppression ; source=install=archive ; ancienneZIP inchangée | PASS | package-validation.json |
| Installation | Vrai Upload/Install/Activate, schéma/fresh/wallet et zéro wallet initial sans opt-in | PASS | package-validation.json ; candidate-installed-active-ui.png |
| Clôture | Primaire inchangé ; copies paused/QAoff/recoveryfalse/tx0 ; queues conservées ; triggers absents ; serveurs arrêtés | PASS | final-safety.json ; final-validation.json |
| Réserves07 | Les10 NOT TESTED et CWV BLOCKED restent inchangés ; pas de nouveaux résultats08A PHP7.4/WP6.4/Woo8.2 | NOT TESTED/BLOCKED hors correction08A | Rapports07 et08A |
| QA indépendante08B | Non lancée | NOT STARTED | Instruction utilisateur |

Comptes d’assertions Developer retenus : **52 provenance, 35 Format2, 55 HTTP Settings, 11 smoke**, plus sondes/UI/package/syntaxe identifiées. Aucun pourcentage de couverture ou nouveau verdict indépendant.
