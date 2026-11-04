# Mission09 — plan de nettoyage confirmé et exécuté

Autorisation explicite reçue dans le chat après le refus automatique initial. Inventaire ci-dessous inchangé depuis le checkpoint6998c01. Suppression définitive des13bases/dossiers et payloads listés exécutée et vérifiée ; journal : evidence/cleanup-ledger.json. Les13SQL restent récupérables dans une archive froide privée vérifiée hors du périmètre supprimé.

Avant suppression :13copies suspendues, recovery/QA désactivées, cron arrêté, zérotransaction et zérotrigger. Après suppression :13bases/dossiers absents et13ports fermés. Les rapports/preuves restent conservés.

| Copie | Base | Chemin | Port |
|---|---|---|---:|
|fresh|nodevora_m08b_fresh|/Users/user/Desktop/work/nodevora-plugin/.private/mission-08B/fresh|18301|
|target1|nodevora_m08b_target1|/Users/user/Desktop/work/nodevora-plugin/.private/mission-08B/target1|18302|
|target2|nodevora_m08b_target2|/Users/user/Desktop/work/nodevora-plugin/.private/mission-08B/target2|18303|
|work|nodevora_m08b_work|/Users/user/Desktop/work/nodevora-plugin/.private/mission-08B/work|18304|
|legacy|nodevora_m08b_legacy|/Users/user/Desktop/work/nodevora-plugin/.private/mission-08B/legacy|18305|
|ui|nodevora_m08b_ui|/Users/user/Desktop/work/nodevora-plugin/.private/mission-08B/ui|18306|
|capacity|nodevora_m08b_capacity|/Users/user/Desktop/work/nodevora-plugin/.private/mission-08B/capacity|18307|
|legacyorder|nodevora_m08b_legacyorder|/Users/user/Desktop/work/nodevora-plugin/.private/mission-08B/legacyorder|18308|
|emptyupgrade|nodevora_m08b_emptyupgrade|/Users/user/Desktop/work/nodevora-plugin/.private/mission-08B/emptyupgrade|18309|
|orderupgrade|nodevora_m08b_orderupgrade|/Users/user/Desktop/work/nodevora-plugin/.private/mission-08B/orderupgrade|18310|
|restoreextra|nodevora_m08b_restoreextra|/Users/user/Desktop/work/nodevora-plugin/.private/mission-08B/restoreextra|18311|
|volume|nodevora_m08b_volume|/Users/user/Desktop/work/nodevora-plugin/.private/mission-08B/volume|18313|
|minimum|nodevora_m08b_minimum|/Users/user/Desktop/work/nodevora-plugin/.private/mission-08B/minimum|18312|

Après suppression des13bases et dossiers : retirer les SQL/NDJSON/configurations/logs fictifs résiduels de .private/mission-08B après manifeste des empreintes ; vérifier absence des13bases, dossiers et ports. Retirer uniquement les deux anciens ZIP QA du dossier artifacts, dont les noms/SHA sont conservés dans les rapports06D/08A/08B et les manifestes. Conserver le ZIP RC, son manifeste/SHA, tous rapports/preuves historiques, Git, assets, outils/tests permanents, SVN et les archives privées06D/08A hors de ce périmètre.

Le primaire http://localhost:10004/ et sa base local sont exclus. Aucun autre site/projet ne doit être supprimé. État Git/main/SVN inchangé par ce plan.

Exécution clôturée : evidence/cleanup-confirmed-preflight.json (identités/sauvegardes/inventaire exact), evidence/cleanup-ledger.json (chaque opération), evidence/cleanup-final-verification.json (ressources restantes). Archive froide13SQL : .private/mission-09-cleanup-backups/qa-final-snapshots.tar.gz ;740632octets ;SHA256bd07b45c658dd6eda673a95b09402f60bb518e426ea75354d638a804a4d8f990. Aucune autre base supprimée.
