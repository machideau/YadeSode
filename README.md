# 📚 Système de Gestion des Bulletins Scolaires

Un système complet pour la gestion des notes et la génération automatique de bulletins scolaires avec support d'import de fichiers (Excel, CSV, PDF, Images).

## 🚀 Fonctionnalités Principales

### ✅ Gestion Complète
- **Établissements** multi-sites
- **Classes** avec professeurs principaux
- **Élèves** avec informations détaillées
- **Matières** avec coefficients personnalisables
- **Utilisateurs** (admin, professeurs, élèves, parents)

### 📊 Système de Notes
- **Évaluations** multiples (devoirs, compositions, TP...)
- **Saisie de notes** par les professeurs
- **Import intelligent** depuis Excel, CSV, PDF, Images (OCR)
- **Calculs automatiques** des moyennes et classements

### 📋 Génération de Bulletins
- **Bulletins PDF** automatiques
- **Moyennes** par matière et générale
- **Classements** et rangs
- **Téléchargement** et archivage

## 🛠️ Technologies Utilisées

### Backend
- **PHP 8+** avec architecture MVC
- **MySQL/PostgreSQL** pour la base de données
- **PHPSpreadsheet** pour Excel/CSV
- **TCPDF** pour la génération de PDF
- **Tesseract OCR** pour l'extraction de texte des images

### Frontend
- **HTML5/CSS3/JavaScript** (Vanilla)
- **Tailwind CSS** pour le design
- **Font Awesome** pour les icônes
- Interface responsive et moderne

## 📋 Prérequis

### Serveur
- **PHP 8.0+** avec extensions :
  - PDO MySQL/PostgreSQL
  - GD (traitement d'images)
  - Zip
  - XML
- **MySQL 5.7+** ou **PostgreSQL 12+**
- **Apache/Nginx** avec mod_rewrite

### Outils Externes (Optionnels)
- **Tesseract OCR** pour l'extraction de texte des images
- **pdftotext** (poppler-utils) pour l'extraction PDF
- **ImageMagick** pour le traitement d'images

## 🔧 Installation

### 1. Cloner le Projet
```bash
git clone https://github.com/votre-repo/bulletin-system.git
cd bulletin-system
```

### 2. Installer les Dépendances PHP
```bash
composer install
```

### 3. Configuration Base de Données
```bash
# Créer la base de données
mysql -u root -p
CREATE DATABASE bulletins_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# Importer le schéma
mysql -u root -p bulletins_system < database/schema.sql
```

### 4. Configuration
Modifier le fichier `config/database.php` :
```php
private $host = 'localhost';
private $db_name = 'bulletins_system';
private $username = 'votre_utilisateur';
private $password = 'votre_mot_de_passe';
```

### 5. Permissions des Dossiers
```bash
chmod 755 uploads/
chmod 755 bulletins/
chmod 755 logs/
```

### 6. Configuration Apache
```apache
<VirtualHost *:80>
    DocumentRoot /path/to/bulletin-system
    ServerName bulletins.local
    
    <Directory /path/to/bulletin-system>
        AllowOverride All
        Require all granted
    </Directory>
    
    # Redirection API
    Alias /api /path/to/bulletin-system/api
</VirtualHost>
```

### 7. Installation OCR (Optionnel)
```bash
# Ubuntu/Debian
sudo apt-get install tesseract-ocr tesseract-ocr-fra poppler-utils

# CentOS/RHEL
sudo yum install tesseract tesseract-langpack-fra poppler-utils

# macOS
brew install tesseract tesseract-lang poppler
```

## 🎯 Utilisation

### 1. Accès Initial
- URL : `http://bulletins.local`
- Créer un utilisateur administrateur via la base de données :

```sql
INSERT INTO users (nom, prenoms, email, type_user, matricule, mot_de_passe, etablissement_id, actif)
VALUES ('Admin', 'Système', 'admin@etablissement.com', 'admin', 'ADM2024001', 
        '$2y$10$hash_du_mot_de_passe', 1, 1);
```

### 2. Configuration Initiale
1. **Créer l'établissement** avec ses informations
2. **Ajouter l'année scolaire** active
3. **Créer les matières** avec coefficients
4. **Ajouter les classes** et niveaux
5. **Inscrire les professeurs** et élèves

### 3. Workflow Quotidien

#### Pour les Administrateurs :
1. Gérer les utilisateurs et classes
2. Configurer les périodes d'évaluation
3. Superviser les imports de notes
4. Générer les bulletins en masse

#### Pour les Professeurs :
1. Se connecter avec ses identifiants
2. Créer des évaluations pour ses classes
3. Saisir les notes ou importer depuis Excel
4. Consulter les moyennes de ses élèves

### 4. Import de Notes

#### Formats Supportés :
- **Excel** (.xlsx, .xls)
- **CSV** (séparateur ; ou ,)
- **PDF** (avec extraction de texte)
- **Images** (JPG, PNG avec OCR)

#### Structure CSV Attendue :
```csv
nom;prenoms;matricule;note;commentaire
Dupont;Marie;EL2024001;15.5;Bon travail
Martin;Paul;EL2024002;12.0;Peut mieux faire
```

## 📊 API Endpoints

### Classes
- `GET /api/classes` - Liste des classes
- `POST /api/classes` - Créer une classe
- `PUT /api/classes/{id}` - Modifier une classe
- `DELETE /api/classes/{id}` - Supprimer une classe

### Élèves
- `GET /api/eleves` - Liste des élèves
- `POST /api/eleves` - Ajouter un élève
- `GET /api/eleves/classe/{id}` - Élèves d'une classe

### Notes
- `POST /api/notes` - Ajouter une note
- `POST /api/notes/batch` - Import en lot
- `GET /api/notes/{eleve_id}/moyenne/{matiere_id}` - Moyenne élève

### Upload
- `POST /api/upload` - Upload fichier
- `GET /api/upload/status/{id}` - Statut conversion
- `POST /api/upload/import/{id}` - Importer en BDD

### Bulletins
- `POST /api/bulletins/{eleve_id}/generate/{periode_id}` - Générer bulletin
- `GET /api/bulletins/{id}/download` - Télécharger PDF

## 🎨 Personnalisation

### Templates de Bulletins
Modifier le fichier `services/BulletinService.php` pour customiser :
- Layout du bulletin
- Couleurs et logos
- Informations affichées
- Format des moyennes

### Thème Interface
Modifier les classes Tailwind dans `index.html` pour :
- Changer les couleurs principales
- Adapter le layout
- Ajouter des animations

## 🔒 Sécurité

### Recommandations :
1. **Mots de passe** : Hash avec `password_hash()`
2. **Requêtes SQL** : Prepared statements obligatoires
3. **Upload** : Validation stricte des fichiers
4. **Sessions** : Configuration sécurisée
5. **HTTPS** : Obligatoire en production

### Configuration PHP :
```ini
session.cookie_secure = 1
session.cookie_httponly = 1
upload_max_filesize = 10M
post_max_size = 10M
```

## 📝 Maintenance

### Sauvegardes
```bash
# Sauvegarde base de données
mysqldump -u root -p bulletins_system > backup_$(date +%Y%m%d).sql

# Sauvegarde fichiers
tar -czf files_backup_$(date +%Y%m%d).tar.gz uploads/ bulletins/
```

### Logs
Les logs sont stockés dans :
- `logs/api.log` - Erreurs API
- `logs/upload.log` - Imports de fichiers
- `logs/bulletin.log` - Génération bulletins

### Performance
1. **Index BDD** : Optimiser selon l'usage
2. **Cache** : Implémenter Redis/Memcached
3. **CDN** : Pour les assets statiques
4. **Compression** : Gzip sur Apache/Nginx

## 🐛 Dépannage

### Erreurs Communes

#### "Connexion à la base échouée"
- Vérifier les credentials dans `config/database.php`
- Tester la connexion MySQL

#### "Erreur upload fichier"
- Vérifier `upload_max_filesize` en PHP
- Permissions du dossier `uploads/`

#### "OCR ne fonctionne pas"
- Installer Tesseract : `which tesseract`
- Vérifier les langues : `tesseract --list-langs`

#### "PDF non généré"
- Extension PHP GD installée
- Permissions dossier `bulletins/`
- Vérifier logs TCPDF

## 📞 Support

### Documentation
- **Wiki** : Documentation détaillée des fonctionnalités
- **API Docs** : Spécifications complètes des endpoints

### Contact
- **Email** : support@bulletin-system.com
- **GitHub Issues** : Pour les bugs et améliorations

## 🔄 Roadmap

### Version 2.0
- [ ] Interface React/Vue.js
- [ ] API GraphQL
- [ ] Notifications temps réel
- [ ] Mobile App (React Native)
- [ ] Intégration SMS/Email

### Version 2.1
- [ ] Tableaux de bord avancés
- [ ] IA pour suggestions pédagogiques
- [ ] Export multi-formats
- [ ] Workflow d'approbation

## 📄 Licence

Ce projet est sous licence MIT. Voir le fichier `LICENSE` pour plus de détails.

## 🙏 Contributeurs

- **Développeur Principal** : [Votre Nom]
- **UI/UX Design** : [Nom Designer]
- **Tests** : [Nom Testeur]

---

**⭐ N'hésitez pas à laisser une étoile si ce projet vous aide !**