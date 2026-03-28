STUDELY LEDGER - MODELES D'IMPORT

Ce dossier contient les fichiers modèles à utiliser pour préparer les imports CSV
dans l'application.

1. modele_clients.csv
   Utilisé dans :
   modules/clients/import_clients_csv.php

   Colonnes attendues :
   client_code ; first_name ; last_name ; full_name ; email ; phone ;
   country_origin ; country_destination ; country_commercial ;
   client_type ; client_status ; currency ; treasury_account_code

2. modele_comptes_internes_512.csv
   Utilisé dans :
   modules/treasury/import_treasury_csv.php

   Colonnes attendues :
   account_code ; account_label ; bank_name ; subsidiary_name ; zone_code ;
   country_label ; country_type ; payment_place ; currency_code

3. modele_releve_bancaire.csv
   Utilisé dans :
   modules/imports/import_preview.php

   Colonnes recommandées :
   operation_date ; label ; reference ; debit ; credit ; balance ;
   client_code ; operation_type_code ; service_code ; treasury_account_code

   Remarque :
   le parseur de relevé accepte plusieurs variantes de colonnes,
   mais ce modèle est celui à privilégier pour garantir la meilleure reconnaissance.

4. modele_services.csv
   Fichier de référence utile pour préparer les données métier
   avant alimentation manuelle du référentiel fonctionnel.

   Colonnes :
   code ; label ; operation_type_code ; service_account_code ; treasury_account_code

REGLES IMPORTANTES
- Séparateur recommandé : point-virgule ;
- Encodage recommandé : UTF-8
- Ne pas modifier l’ordre des colonnes si possible
- Ne pas supprimer l’en-tête
- Les montants doivent être au format numérique simple :
  1250.50
  ou
  1250,50 selon le parseur concerné
- Les dates doivent idéalement être au format :
  YYYY-MM-DD

CONSEIL
Toujours tester un petit fichier avant un import massif.