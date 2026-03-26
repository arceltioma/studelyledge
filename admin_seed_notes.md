# StudelyLedger - Notes de seed administration

## Base concernée
`studelyledger`

## Comptes utilisateurs injectés

### 1. admin
- Username : `admin`
- Rôle : `Super Administrateur`
- Statut : actif

### 2. manager01
- Username : `manager01`
- Rôle : `Manager`
- Statut : actif

### 3. viewer01
- Username : `viewer01`
- Rôle : `Lecture seule`
- Statut : actif

## Important sur les mots de passe
Les mots de passe présents dans le seed SQL utilisent un hash de démonstration.

Il est fortement recommandé de générer tes propres hashes avec :

```php
<?php
echo password_hash('ton_mot_de_passe', PASSWORD_DEFAULT);