# Vérification indépendante — Nodevora 4.8.6 QA

Cette liste prépare la QA suivante ; elle ne constitue pas une QA indépendante déjà exécutée. La candidate n’est pas une release et ne doit pas être installée en production. Utiliser un site WordPress/WooCommerce jetable avec sauvegarde DB et fichiers vérifiée, réseau/mail sortants maîtrisés et paiements hors ligne. Ne jamais remplacer une sauvegarde par une ancienne base après de nouvelles transactions.

Candidate livrée : `nodevora-customer-rewards-for-woocommerce-4.8.6-qa.zip`, 182492 octets, SHA-256 `11047f9f72a787e751975307f5c8872343275c7b2225ec2766a98aaeafb46496`.

Vérifier le SHA-256 du ZIP dans le rapport final, son unique dossier `nodevora-customer-rewards-for-woocommerce/` et sa version réelle4.8.6. Aucun compte, mot de passe, backup client ou outil QA ne doit être inclus dans l’archive.

| Scénario | Manipulation indépendante | Résultat attendu |
|---|---|---|
| Installation réellement vierge | Envoyer le ZIP depuis Extensions → Ajouter, activer avec WooCommerce | Panneau Nodevora simple, aucun Financial Setup ; tables vérifiées, aucun import de tous les utilisateurs |
| Ajustement300 | Rechercher un compte neuf et ajouter300 avec une note | Solde300 et un crédit ; retransmission du même POST/UUID sans second crédit |
| Achat sans points | Produit virtuel100, paiement hors ligne, statut déclencheur configuré | Aucun débit de fidélité ; gain exact après le statut prévu, identité de gain unique |
| Dépense financée | Appliquer119points au taux20/MAD dans Classic et Blocks | Remise5,95, financement durable avant la validation du gateway ; pas de remise sans débit confirmé |
| Retour et reprise | Recharger, revenir, répéter une requête, tester deux dépenses simultanées | Pas de double crédit/débit ; une seule dépense si fonds insuffisants pour les deux |
| Mise à jour officielle4.8.5 | Utiliser le vrai package485, conserver settings/soldes/lots/historiques ; installer486 | Détection historique, aucune migration silencieuse ; Synchronisation visible après activation/notice persistante |
| Synchronisation interrompue | Arrêter le navigateur entre deux lots, relancer depuis le panneau | Reprise persistante ; checksum modifié refusé ; aucun second opening ; comptes ambigus isolés avec preuves |
| Nouveau compte pendant la fin du scan | Créer un compte après le dernier lot vide, avant cutover | Nouvelle découverte bornée ; aucune activation prématurée ni bonus historique inventé |
| Données ambiguës | Solde99 sans lots, ancienne commande ou claim incertain | Quarantaine conservatrice, pas d’invention de lots, de débits ou de bonus |
| Export format2 | Télécharger via Données et sauvegardes | NDJSON version2, SHA/footer intègre, données Nodevora seulement ; aucun fichier public abandonné |
| Import compatible | Copie identique pour utilisateurs/commandes, cible Nodevora vide et sauvegardée | Preview du fichier exact puis confirmation ; toutes les données financières identiques, état review avant reprise explicite |
| Import refusé | Fichier tronqué/corrompu, mauvaise version, identité modifiée, doublon ou objet sérialisé | Refus sans altération ; pas de restauration sur wallet ayant une activité nouvelle |
| Atomicité | Injecter un échec contrôlé après le début de l’import sur la copie jetable | Rollback intégral et pause conservée ; MyISAM refusé avant insertion ; récupération jamais implicitement approuvée |
| Simple/Advanced | Modifier le mode d’un admin, sauvegarder une section simple | Mode conservé par utilisateur ; règles avancées préservées ; choix d’un autre admin indépendant |
| Design | Presets, palette, HEX invalide/valide, reset individuel/global, preview desktop/mobile/composants | Couleurs validées, aperçu sans sauvegarde obligatoire, overrides locaux ; aucun style appliqué au thème entier |
| Rôles et restriction | Tester Admin, ShopManager, Editor autorisé/non autorisé, Customer, Guest ; ON/OFF | Admin toujours autorisé ; manager édite selon politique, Editor consulte ; aucune escalade de privilèges financiers |
| Endpoints directs | URL, options.php, admin-post, AJAX, REST produit, nonces invalides | Même politique côté serveur ; Data/import/synchronisation/refund/policy restent administrateur-only |
| Mobile et accessibilité | EN/FR,320/390/desktop, palette ouverte, labels, Tab/Enter, contraste | Aucun débordement Nodevora ; contenu français complet ; contrôles nommés et focus visible |
| Refundhold | Partiel, callbacks processing/completed/payment/recovery, programme désactivé | Hold durable ; résolution externe autorisée zéro-valeur ; un nouveau remboursement impose nouvelle revue |
| WooCommerce absent | Désactiver puis réactiver WooCommerce sur la copie sauvegardée | Aucun fatal Nodevora ; notice dépendance admin ; initialisation normale des Blocks après retour |
| Réinstallation | Désinstaller sans nettoyage des préférences puis réinstaller | Wallet/journal/lots/snapshots/autorité et préférences conservés ; pas de réimport |

Les résultats développeur sont des points de comparaison, pas des résultats à recopier. Capturer vos propres états DB, IDs d’opération, captures et erreurs. Les scripts `tests/remediation/mission-06d-*` sont limités à la démonstration explicitement autorisée ; ils ne doivent pas être lancés en production ou sur un autre site sans adaptation et autorisation. Ne pas contourner une demande de validation humaine du système d’exécution : les clics finaux de commande et de statut ont requis une intervention de l’utilisateur pendant les essais développeur.

Conserver les limites : aucune politique proportionnelle de remboursement partiel, aucun multisite, aucune certification de tous les anciens dossiers/packages Rewardly ou de leur reconnaissance d’update, aucune certification de passerelles arbitraires/SMTP externe/production. Les minimumsPHP7.4/WP6.4/Woo8.2 restent déclarés ; vérifier séparément les runtimes minimums disponibles. Les essais complets actuels portent surWP7.1.4/Woo11.2.1/PHP8.3 ; le rapport détaille la portée du contrôlePHP8.2.
