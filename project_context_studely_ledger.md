# CAHIER DES CHARGES OFFICIEL — STUDELY LEDGER

## 1. Présentation du projet

### 1.1 Contexte
Studely Ledger est une plateforme web de gestion financière conçue pour centraliser, analyser et piloter des données financières de manière professionnelle.

### 1.2 Objectifs
- Centraliser la gestion des comptes financiers
- Suivre et analyser les flux (entrées / sorties)
- Automatiser les opérations récurrentes
- Offrir une vision claire et exploitable des données
- Fournir une base évolutive vers une solution SaaS

### 1.3 Cible
- Professionnels (gestion, IT, finance)
- PME / indépendants
- Usage personnel avancé

---

## 2. Périmètre fonctionnel

### 2.1 Gestion des comptes
Chaque compte doit permettre :
- Création, modification, suppression
- Définition d’un solde initial
- Calcul automatique du solde en temps réel
- Association de moyens de paiement

### 2.2 Gestion des opérations
Deux types d’opérations :
- Entrées (revenus)
- Sorties (dépenses)

Champs obligatoires :
- Compte
- Type d’opération
- Catégorie
- Montant
- Date
- Description
- Commerçant (optionnel)

### 2.3 Gestion des types et services
#### Règle métier centrale :
- Un service est rattaché à un type d’opération
- Plusieurs services peuvent dépendre d’un même type

#### Contraintes :
- Création d’un service :
  - Sélection d’un type existant OU création d’un nouveau type
- Création d’un type :
  - Possibilité d’associer des services existants ou nouveaux

### 2.4 Gestion des catégories
Liste personnalisable de catégories financières :
- Ajout
- Modification
- Suppression

### 2.5 Gestion des opérations récurrentes
- Création d’opérations automatiques
- Fréquence mensuelle
- Calcul et intégration automatique dans les soldes

### 2.6 Gestion des utilisateurs
- Création de comptes utilisateurs
- Attribution de rôles
- Liaison utilisateurs ↔ comptes

### 2.7 Gestion des moyens de paiement
- Association de cartes à un compte
- Gestion des plafonds
- Suivi de validité

---

## 3. Interface utilisateur

### 3.1 Principes UX/UI
- Interface moderne et épurée
- Navigation fluide sans surcharge
- Utilisation de modales
- Messages dynamiques (feedback utilisateur)

### 3.2 Dashboard
Le tableau de bord doit inclure :
- Soldes par compte
- Graphiques (Chart.js)
- Statistiques globales
- Filtres dynamiques (période, compte, catégorie)

---

## 4. Interface d’administration

### 4.1 Rôles
- Administrateur global
- Administrateur limité (accès restreint par périmètre)

### 4.2 Fonctionnalités
- Gestion des utilisateurs
- Gestion des comptes
- Gestion des types et catégories
- Gestion des cartes
- Validation des actions

---

## 5. Architecture technique

### 5.1 Technologies
- Backend : PHP
- Base de données : MySQL / MariaDB
- Frontend : HTML / CSS / JavaScript
- Environnement : XAMPP

### 5.2 Structure du projet
- config/ : configuration
- includes/ : composants communs (header, footer)
- assets/ : CSS et JS
- modules/ : logique métier
- pages/ : vues

### 5.3 Règles de développement
- Centralisation du CSS :
  - style.css
  - dashboard.css
- Centralisation des fonctions :
  - admin_functions.php
- Aucune duplication de logique
- Séparation stricte logique / affichage

---

## 6. Modèle de données (simplifié)

### Tables principales
- compte
- entree
- sortie
- categorie
- type
- service
- utilisateur
- carte

### Calcul du solde
Solde = Solde initial + Somme des entrées - Somme des sorties

---

## 7. Sécurité

- Hash des mots de passe
- Validation des entrées utilisateur
- Protection contre injections SQL
- Gestion des sessions sécurisées

---

## 8. Performance et évolutivité

- Structure modulaire
- Requêtes optimisées
- Possibilité d’évolution vers architecture SaaS
- Support multi-utilisateurs

---

## 9. Planning prévisionnel

### Phase 1
- Stabilisation technique
- Correction base de données
- Mise en place structure

### Phase 2
- Développement fonctionnalités avancées
- Amélioration UX

### Phase 3
- Optimisation
- Préparation SaaS

---

## 10. Livrables

- Code source complet
- Base de données
- Documentation technique
- Documentation utilisateur

---

## 11. Objectif final

Mettre à disposition une solution :
- Professionnelle
- Fiable
- Évolutive
- Centralisée
- Adaptée aux besoins financiers modernes

---

FIN DU CAHIER DES CHARGES

