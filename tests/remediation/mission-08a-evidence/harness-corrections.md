# Corrections des harness Mission08A

Le premier lancement de la suite de provenance est arrivé à l’injection SQL, mais CREATE/DROP TRIGGER n’est pas pris en charge par la connexion financière préparée. Le harness utilise désormais wpdb pour ce DDL temporaire ; les INSERT d’import restent sur la connexion financière réelle. Aucun succès de cette exécution incomplète n’est annoncé. Le trigger est retiré dans finally.

La sonde séparée d’override avait omis le contexte administrateur et était refusée par permission avant le gate opérateur. Contexte fixé, seule cette sonde est relancée. Le harness settings avait aussi une erreur de syntaxe Python, corrigée avant toute requête de cette suite.

Le premier smoke demandait un second dispatch à une commande déjà dispatched, alors que le cœur refuse volontairement cette opération. Le smoke vérifie maintenant le replay paid sans second débit. Les données du premier essai sont conservées dans la copie et un snapshot SQL dédié.

Le contrôle paid comparait aussi la révision avant le premier contrôle zéro-valeur : celui-ci incrémente légitimement la révision. Le test compare désormais le premier paid et son replay, avec solde conservé. Le contrôle des réglages distingue la normalisation préexistante de max_discount_per_order 0→0.00 d’une modification de valeur ; comparaison Decimal pour ce champ et stricte pour tous les autres. Les suites partageant restore ont été relancées séquentiellement pour exclure une interférence de harness.
