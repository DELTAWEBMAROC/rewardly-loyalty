# Mission 06D — SAFE STOP / reprise exacte

**État : INCOMPLET, WIP sauvegardé, développement arrêté à la demande de l’utilisateur.**
Date : 29 octobre 2026. Avancement estimatif : **65 %** (estimation, pas un taux de tests réussis).
Dépôt Git : `github/`. Branche : `feat/nodevora-4.8.6-financial-foundation`.
Base : `d4936e165eb75300aff483d5685d97e9c5626077`.
**Dernier commit sauvegardé (documentation et checkpoint) : `0f98eb535f6723d06e2824327b683809812713a6`. Arbre Git propre.**
Dernier commit d’implémentation WIP : `855a7c8a00a1d453a9d8398263db888fed3c3bc7`.
Le commit documentaire qui contient ce checkpoint s’identifie avec `git log -1 --format=%H -- docs/mission-06D-progress.md` ; le SHA final est également enregistré dans le rapport local après ce commit.
`main` local inchangé : `eca72d6c08184ed5dd7e28b4e52abc98ebf342ef`.
Aucun push Mission06D ni modification de PR#2, aucun merge/tag/release/publication/SVN. L’état distant n’a pas été recontrôlé pendant cet arrêt.
**Pas de ZIP final.** Métadonnées source toujours4.8.5 ; archives de développement privées4.8.5 uniquement, non livrables.

## Autorisation et périmètre

L’utilisateur a explicitement identifié **LocalWP nodevora-plugin / http://localhost:10004 / DB local** comme démonstration jetable et autorisé la réinitialisation de ses seules données fictives. Cette autorisation remplace la restriction antérieure de conservation de CE site. Aucun autre site ou fixture06B/06C n’a été touché. Nom/path LocalWP et URL vérifiés avant les resets. Aucun identifiant de connexion conservé dans Git ou les rapports.
Spécification complète : pièce jointe `689ce5a8-7143-4852-bc2b-5de730dee2eb/Pasted text.txt`.

## Phases et décisions

1. **Fondations installation vierge : implémentées et premier parcours vérifié.** Détection conservatrice des tables/journaux/soldes/lots/claims/commandes HPOS/métadonnées. Schéma automatique uniquement sans preuve historique, autoritéWallet persistante, créationparesseuse0, échec fermé et réparation. Aucun retour legacy. Dernier élargissement frais/marqueurs doit être retesté avant certification fraîche.
2. **Synchronisation marchand : implémentée, parcours UI4.8.5 testé.** Lots20, état/curseur/checksums persistés, sources et snapshots immuables, reprise réseau, quarantaine. Ancien menuFinancialSetup supprimé ; Data/Synchronisation dans panneau unique. Attestation externe nécessaire, interruption checkout clairement expliquée ; aucun contrôle fictif sur les writers externes. Aucun drainage automatique des anciennes files. Revue récupération avancée ajoutée mais pas encore validée.
3. **Backup/Restore : prototype fonctionnel vérifié, durcissement encore WIP.** NDJSON privé borné32Mio/100000records/1Mioligne, vueDBcohérente, empreintes et références, previewHMAC+nonce+permissions, import transactionnel ciblevideuniquement, pausepartagée sur wallets, étatrestore_review aprèsimport. Format désormais2 avec métadonnéesfrais/pending et empreintes tombstones pour entités supprimées. Les testsréussis7lignes/UI portent sur FORMAT1 prototype ; **ne pas les présenter comme certification FORMAT2**.
4. **UX/modes/design/permissions : implémentés, validation partielle.** Simple/Advanced par utilisateur, valeursavancées conservées, éditeurNodevora scopedCSSvariables/presets/paletteWordPress+HEX/reset/previewiframe, composantsCart/Checkout/Account/History/Notices. Panneauunique, Editoroptionnelreadonly, ShopManagerpar défaut, admins_only, gatesPOST/AJAX/export/import, guardréglageproduitREST/meta. Matrice complète non exécutée. Aucun suivi du thème, aucune dépendance lourde. Traductions438messages compilées ; comportementFR réel final reste à vérifier.
5. **Régressions/compatibilité : à terminer**, pas de rejeu global des anciens audits. Aucun résultatMission05/06/06B/06C revendiqué comme nouveauPASS06D.
6. **Packaging4.8.6, rapportfinal, matriceQAindépendante, pushdev et PRdraft : NONCOMMENCÉS.** Ne produire ZIPfinal qu’après corrections et tests requis.

## Vérifications réellement exécutées

- BootstrapZIPdéveloppement installé/activé via installateurWordPress ; schéma/prêt/authoritywallet. FormulaireUIajustement réel0→300. Replayidentitédujournal :300inchangés, un crédit, un wallet créé àlademande, invariantsPASS. `fresh-300-journal-retry.json`, `adjustment-300-browser.png`. L’ancienne capture d’identitédeformulaire contientnull ; ne pas la citer comme preuve.
- Prototypebackup export7rows, corruptionrefusée ; cibleactive300refusée sansaltération ; cibleNodevora vidée uniquement aprèsbackupSQL puis importbibliothèque7checksPASS, mêmeswallet/journal/lots/checksum ; barrièreDBrestoring malgré cacheWPactive refusecrédit. `export-inspection.json`, `restore-active-refused.json`, `restore-compatible-library.json`.
- **UI réelle backup FORMAT1** : previewactive refusée ; ciblevidéeaprèsbackup, preview/importconfirmé/repriseexplicite réussis ; export téléchargé5888octets, SHA256cd069c86eb8bde6803a09fb479a5aa9bc18893915820287befe1958e4d9b1f85 ; solde300/revision1/invariants, récupérationnonapprouvée. `backup-import-ui.json`, `import-resume-browser.png`.
- **AuthentiqueZIPofficiel4.8.5** SHA25686e0dabe18533cc694a2a0fcf2ca2a81121e1239610762efc015ff38dacff37a réellement installé, donnéesseedées viaHelperancienneversion puis updatecandidatWIP. Pas encore candidat4.8.6final. UIattestationrequise, reloadinterruption(cursor100/batch20/wallets100), driftinjectérefusé, seuleinjection annulée, UIreprisecomplète : **15comparaisonsPASS**, bon123, mauvais99isolé,1000lots/1000points/expirations/source/settings/commandes préservés. `official-485-before.json`, `official-485-activated.json`, `sync-ui-interruption.json`, `sync-ui-drift.json`, `official-485-sync-after.json`.
- UI design : HEX#156058 reflété danspreview(buttonrgb21,96,88), presetMinimal, mobilepreview, paletteWordPresschoix#12b8a6, reset déclenché sanssave ; modeAdvancedpersisté sans modifier règles3/365. Ce n’est pas encore une validation complète sauvegarde/reset/roles/responsive.
- **SAFE STOP rapide : PASS** syntaxe35fichiersruntime surPHP8.2 etPHP8.3,5JS viaNode--check, gettextcheck/format438messagestraduits, gitdiff--check. `safe-stop-syntax.json`. Aucun testfonctionnel ou migration supplémentaire démarré pour l’arrêt.

## Problèmes / risques à reprendre en premier

- **Contrôle checksumRestore à vérifier immédiatement** : la nouvelle validation refusewallet.checksumNULL ; `Nodevora_Financial_Lifecycle::registration()` crée encore des comptesneufs sans renseigner cette colonne. Incompatibilitépotentielle entre nouveaucompte etrestauration ; pas encore reproduite. Ajouter un testciblé puis corrigercohéremment l’initialisation/validation, sans dégrader l’intégrité.
- `Nodevora_Data::message()` WIP : un message déjàtraduit arrive inchangé àtranslate(), donc peut tomber sur le fallbackgénérique ; compléter la correspondance sûre des erreurs UIenEN/FR et garder des instructionsactionnables. Aucun secret ou exceptionDBbruteàafficher.
- Enregistrer un compte neuf pendantune migration/restore : revue nécessaire du cheminregistration et de sa barrièrepartagée ; ne pas inventer/rejouer un bonus historique.
- Format2 : retester références order/refund/item, tombstones, snapshot/journal/lot/outbox, doublons, cachemetadata, races/retries et rollbackatomique. MoteursDButilisés pour identitésWoo àrevérifierdansbackupcohérent. Bornes tailles/objets/version/fichierinvalide àchallenger.
- Détectionfreshinitiale/resetprototype n’incluaitpas les métadonnéesfrais ; code etscriptreset corrigés, certificationfreshàrefaireavecZIPfinal. Ne pas reprendre lesrésultatsantérieurs comme preuveexclusive.
- ContrôlespermissionsproduitREST/meta, récupérationrevueavecempreinte, UIadvancedreadonly, gardeanti-bypass et EN/FR320/390/desktop encore àtester. Préserver règles de remboursementpartiel existantes, aucunepolitiqueproportionnelle.
- PHP7.4, WP6.4, Woo8.2 ne sontpas certifiés parcescontrôlessyntaxe. Réutiliser seulement lespreuves antérieures enles identifiant, puis tests ciblés accessibles. Productionjamaisautorisée.
- Aucun blocageoutil/authentification nouveau constaté ; arrêt uniquement demandé parutilisateur. Testsfinaux/QAindépendante nonréalisés.

## État EXACT LocalWP au SAFE STOP

- Site toujours lancé : **nodevora-plugin**, URLhttp://localhost:10004, docroot `/Users/user/Local Sites/nodevora-plugin/app/public`, DB`local`, LocalWPid6JRuOv4ug.
- WordPress7.1.4 / WooCommerce11.2.1 / PHP8.3.30, HPOSfixtureexistante.
- Setup : **phasepaused**, originsynchronized, authoritywallet, installationstatusready (schémadisponible, ce n’estpas uneautorisationd’écriture), recovery_approvedfalse, marqueurSAFE_STOP06D.
- **Zéro transactionInnoDBactive** avant etaprèspause. Aucunesync/importfinancierencours.
- Wallets inchangés :241ready,total1123points ;1needs_review,valeurwallet0, source99conservée. Outbox**vide**, nonpurgée. Commandesfictives etpreuves demeurent ; n’affirme pas que touteslescommandesont étéclôturées.
- Constantesdémo : WRITES_PAUSED=true, INTEGRATION_ALLOWED=false, QA_TESTING=false, DISABLE_WP_CRON=true, SCHEMA_ALLOWED=false, MIGRATION_ALLOWED=false, WRITERS_DRAINED=false ; aucunQAgateway.
- MU `nodevora-m06d-safety.php` et `nodevora-qa-safety.php` conservés ; mail etHTTPsortants bloqués. Ne pas les supprimer enreprenant.
- Réglagesmarchandinchangés entreavant/aprèspause, SHA256serializationfe2d91c31a037b4ab8e3371a1488a89f3e46a799cc564485a85ad36f9ddfc72c. Modeaffichageadmincourantadvanced (changementexplicitedetest), aucunréglagedesign d’essaienregistré.
- **Runtime installé distinct du dernierGitWIP** : dernièrescorrections financières déjàdéployées, mais `class-nodevora-data.php` (mappingerreurs) etPOT/PO/MOfrançaisfinaux nonencoredéployés. Listeexacte dans `safe-stop-persistence.json`. Ne pas supposerqueGitetLocalWPsontidentiques.
- PreuveUIpause : `safe-stop-admin.png`. Ongletadminconservépourreprise ; aucunprocessustestPHP/Nodeencoreactif.

## Sauvegardes et persistance

Touslesrapports/evidenceslocaux sontconservés sous `audit-reports/mission-06D-evidence/`, horsdépôtGit. Copiecheckpointcuratée sanssecrets dans `github/docs/mission-06D-checkpoint-evidence/`.
Sauvegardes privées désormaiscopiéesdurablement sous **`.private/mission-06D/` au niveauworkspace, hors`github/`**, permissionsdossier0700 ; originauxtemporairesconservés aussi. **Ne jamais ajoutercedossierou lesSQL/wp-config/exportsdansGit.** Pointeurslocaux0600 : `private-location.txt` (tmporiginal) et `durable-private-location.txt` (copiedurable).
- Avantmission : SQL7286082octets, SHA256d24c6add20ca543f3aa79efee198bcfaf17cb5824fff2b0b0d77275d8bec5c66, copiecomplètedocroot`site-before/`.
- Backupsprivéssupplémentaires : after-300-before-restore.sql, before-ui-import.sql, before-official-upgrade.sql ; exportsprototypeNodevora etarchivesdéveloppementconservés.
- Snapshotàl’arrêt : `safe-stop-demo.sql`3342889octets, SHA25619ae0f0082fca6bc865c6619a5940ba05c4923d9c1be7a7609d2fa556fc24946 +`safe-stop-wp-config.php`privé.
- Scriptscontinuationcopiés dans `.private/mission-06D/session-tools/` ; plusieursréférencentl’ancienpointeurtmp, **adapterlepointdelectureversdurable-private-location.txtsi/tmpn’existeplus**. Ne pas relanceraveuglémentlesreset/builders : plusieursécrasentleursanciennespreuves. Créer variantesnouvellesevidencesauprochaintest.

## Reprise — prochaine action précise

Attendre une instruction explicite **RESUME MISSION 06D**. Ensuite :

1. Lire cecheckpoint, vérifierbranche/HEAD propre et identitédémoLocalWP10004. GarderlesgatesSAFE_STOPpendantlarevue. Ne répéteraucunauditcomplet.
2. **Commencer par le testciblé de cohérence registration→backup/restore checksumNULL** et revue de sa barrièrependantimport. Fixeruniquementle défautreproduit. Puisfinir mappingerreurs EN/FR et validationsformat2/métadonnées/refs/caches/retries.
3. Déployer WIPàjour surdémoavecscriptadapté seulementaprèscescorrections. Pourtestsfinanciersautorisés, leverdélibérémentuniquementpause/integrationetphasepauseddeCEsite ; gardercrondésactivé, gatesmigration/QAfalse et mailHTTPbloqués, ne jamais vider d’anciennefile. Sauvegarderd’abordsiun nouveauresetestnécessaire. Ne pas exécuterunemigrationautomatiquesurlefixtureactifdéjàsynchronisé.
4. ComplétertestsUIavancée/paletteHEX/reset/save/preview etmatricerôlesAdmin/ShopManager/Editor/Customer/guest, restrictionON/OFF, URL/POST/AJAX/REST,320/390/desktopEN/FR. Finirrevuerécupérationexplicitementcontrôlée.
5. Régressionsfinancièrescourtes ciblées concurrence/doublongain/reversal/refundhold/Classic/Blocks/missingWoo+rétablissement/réinstallation, fresh/legacyempty/485avec données etambiguïtés/historiquescoexistants ; préserverpreuvesoriginales. Contrôlescompatibilité réellementaccessibles seulement.
6. SeulementaprèsPASS : métadonnées4.8.6QA, ZIPruntimepropre au slugofficiel+SHA256, installation/activationréelle+ajustement300+achat/gain/dépensefinancée, nouvelupgradeauthentique485→final486, importformat2réelUI. Rapportfinal/limites/matriceQAindépendante, commitdev/pushseulebranche/PR#2draftetSHAremotevérifié. Aucunmerge/main/SVN/release.

## Fichiers modifiés depuis base

- `assets/css/admin-legacy.css`
- `assets/css/admin.css`
- `assets/css/loyalty.css`
- `assets/js/admin-data.js`
- `assets/js/admin-design.js`
- `assets/js/loyalty-blocks.js`
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

Aucunefonctionnalitéencoursn’estconsidéréeterminée tantquelespreuvesfinalesne sontpas produites. Arrêtvolontaireetcompletaprèscecheckpoint ; reprendreici, pasauMission06B/06C.

## Reprise autorisée — 29 octobre 2026

Instruction SAFE RESUME reçue. HEAD0f98eb535f6723d06e2824327b683809812713a6 et branche confirmés, arbre propre ; identité LocalWP6JRuOv4ug/nodevora-plugin/localhost10004/DBlocal confirmée. Pause, QAfalse, cronoff,0transaction vérifiés avant mutation. Levée contrôlée des seules pausesécriture/intégration et phaseactive pour tests ; recoveryfalse, réseau/mailbloqués et gatesmigration/QAfalse conservés. Aucune migration automatique sur cette base déjà synchronisée.

Défaut registration confirmé avec userfictif243 : snapshotvalide, wallet.checksumNULL, validateurRestore refuse. Correction de l’initialisation et réparationuniquement du snapshotvide identifié déjà généré par ce chemin ; barrièrepartagée avecimport, échecconservéenpending sansfatal. Testaprès : nouveauxwallets/checksums cohérents, validationformat2 sanserreur ; stale-cacheDBrestoring empêchecréationwallet. Preuves registration-before/after/barrier.json (tests/remediation/mission-06d-evidence).

Mappingerreurs : typeNodevora_User_Error pour instructionslocaliséesfiables, suppressionde la traductionrépétée dechaînesdéjàtraduites, mappingfinancieràfrontièreUI, diagnosticinconnu jamaisexposé. TestsEN/FR passent.

Format2 bibliothèque :35contrôlesPASS,2823enregistrements/1127628octets, référencesuser/order/item/comment/post, tombstones d’entitéabsente, exclusionautresplugins, corruption/version/schéma/plugin/identités/doublons/objets/optionsnonautorisés refusés ; importincohérentrollbackzéro wallet etpausemaintenue ; importvalide donnéespropresidentiques, réglagesinchangés, metadata caches, reviewgate, retryrefusésansdoublon,invariants. SQLprivé supplémentaire avanttest ; aucunbackuporiginel écrasé. Preuveformat2-roundtrip.json. Reprise des points effectuée ensuite depuisleformulaireUIréel, recoveryresteunchecked.

En cours : permissionsHTTPvraiesroutes URL/POST/AJAX/RESTet quatrepolitiques ; régressionsfinancièrescourtes ; resteconcurrence, UIresponsive/design complet, importformat2viaUIfinal, métadonnées4.8.6/ZIPinstall+fresh+upgradeofficiel+rapport/pushPR. AucunZIPfinal.

## Checkpoint candidat 4.8.6 / UI et permissions

Commit de correction sauvegardé : ee67cac.128assertionsHTTPpermissionsPASS (quatrepolitiques, sixacteurs, URLs/POST/AJAX/REST) ;18régressionsrefundhold et12smokesfinancement/reversal/StoreAPIPASS. Trois scénariosconcurrentsPASS avecprocessusindépendants (deuxdébits, créditdupliqué, pauseavecécritureengagéeetcallbackretardé).

UI :11contrôlesdesign/modes/reset/contrastePASS.42vuesAdmin EN/FR320/390/1280 sansdébordementetlabelsvalides. DéfautData320détecté360px puiscorrigévia grilleshrink ; preuveavant/aprèsdansadmin-responsive.json. Unelecturepreviewinitiale étaitprématuréeavantfinchargementiframe ; résultatcorrect confirméaprèsstabilisation, pas de fauxdéfautproduit.

Candidateprivée d’intégration4.8.6 construite (pasZIPfinal),48fichiersruntime,182389octets,SHA256cf7a37f8e6455450a52d8d7722fd1918a2cd155751896d6d14b54676972bb3dc. Métadonnées/readme/modeemploi misàjour. Format2surcettecandidate :35PASS,2960enregistrements,1204089octets ; nouvellesvérificationsInnoDBcible etverrousidentitésfinalimport. UIréellepreview/import/reviewensuitePASS ; exportendpointHTTP200NDJSONvalide,mêmeformat/plugin/solde300/invariants. ÉvénementdownloadCua nonremonté(timeout) : nepasrevendiquer capturedownloadnavigateur ; endpointtesté séparément.

WooCommerceinactif/rétabli :6contrôlesPASS ycompris3HTTP etvraiedésinstallation/réinstallationNodevora sansaltérationtablesfinancières/préférences. Nouvelleinstallationfresh effectuéeensuite depuisvraiUIWordPressUpload/Install/Activate(aprèsbackup+resetuniquementNodevora).257WPusers,0walletinitial,ready/fresh/walletautorité sansoptinfinancier/écranFinancialSetup. UIconfigurationearn2/emailnoetajustement0→300PASS ; replaymêmejournalnecréditepasdeuxfois,1événement,invariantsPASS. Preuvesfresh-486*.

Étatdémoactuel :candidate4.8.6active/originfresh,pasdegatesfinancierstest,cronoff/QAfalse/mailHTTPbloqués ; récupérationfreshconnueautorisée parinstallationmaiscronresteoff. ParcourscommerceUIencoursàdémarrer surproduitvirtuel285,cart286,checkout287,CODfictif configuré ; réglagesWoo antérieurs privés danscommerce-options-before.json. Restentcashgain/dépensefinancée,frontendresponsive/Blocks,upgradeofficiel485→486final, limites/dossiersQAindépendante/rapportfinal/pushPRdraft.

## Parcours marchand final en attente de clics imposés

Candidate4.8.6runtime48fichiers correspond exactementauxsources (aucunZIPfinal créé). AjustementUI300replayPASS, produitvirtuel285 sansstock, CODlocal sanspaiementréel, mail/réseau bloqués. Lecontrôleautomatique a toutefois refusédeuxfois leclicfinalCommander etimposéunepriseenmainutilisateur. AucuncontournementAPI/CLI effectué. L’utilisateur a créé lui-même lacommande288 (100MAD,COD). Commandeactuellementprocessing ; la validation automatique refuseégalementl’enregistrementCompletedpourlegainfictif. ActionhumaineUpdate/Completed demandée etattendue. Ne pasréinitialiserlaDBtantqueleparcourscommerce estencours.

TestsstockageadditionnelsPASS aprèscorrectiond’uneerreurdesyntaxeduharness (aucunetransactionn’avaitdémarré) : MyISAMcible simulérefuséavantinsert ; vrai verroupartagéd’identitévsno-opupdateindépendantrefusétimeout1205 ; primitiveexportsnapshot maintientwallet/journalcohérents malgrécréditconcurrentcommis. Syntaxe42PHP(runtime+nouveauxharness)PASSsur8.2/8.3et5JS.
