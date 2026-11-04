# Mission09 — plan de nettoyage soumis à confirmation

AUCUNE suppression exécutée. Refus automatique avant lancement de la commande : autorisation spécifique de ces13copies absente du transcript jugé fiable par le contrôle ; autorisation du fichier joint non retenue. Suppression définitive des bases et fichiers fictifs.

Les13copies sont suspendues, recovery/QA désactivées, cron arrêté, zérotransaction et zérotrigger. Le serveur18306 est arrêté. Les rapports/preuves restent conservés.

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
