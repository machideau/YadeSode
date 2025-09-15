# YadeSode

Ce projet est une API PHP pour la gestion d'un système scolaire, comprenant la gestion des classes, matières, élèves, établissements, évaluations, notes et utilisateurs.

## Structure du projet

- `api/` : Point d'entrée de l'API (index.php)
- `config/` : Fichiers de configuration (connexion à la base de données)
- `controllers/` : Contrôleurs pour la logique métier de l'API
- `models/` : Modèles représentant les entités de la base de données
- `db.sql` : Script SQL pour la création de la base de données

## Prérequis

- PHP >= 7.4
- Serveur web (Apache, Nginx, etc.)
- MySQL ou MariaDB

## Installation

1. Clonez le dépôt :
   ```sh
   git clone <url-du-repo>
   ```
2. Importez le fichier `db.sql` dans votre base de données MySQL/MariaDB.
3. Configurez les accès à la base de données dans `config/database.php`.
4. Placez le projet dans le répertoire web de votre serveur.
5. Accédez à l'API via `http://localhost/yade_sode/api/`.

## Fonctionnalités principales

- Gestion des classes, matières, élèves, établissements, évaluations, notes et utilisateurs
- Architecture MVC simplifiée
- API RESTful (CRUD)

## Auteur

- machideau

## Licence

Ce projet est sous licence MIT.
