#!/bin/bash

# install.sh - Script d'installation automatique du système de bulletins
# Usage: ./install.sh

set -e  # Arrêter le script en cas d'erreur

# Couleurs pour les messages
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration par défaut
DB_NAME="bulletins_system"
DB_USER="root"
DB_PASS=""
DB_HOST="localhost"
WEB_DIR="/var/www/html/bulletins"
APACHE_USER="www-data"

echo -e "${BLUE}=================================="
echo -e "  INSTALLATION SYSTÈME BULLETINS"
echo -e "==================================${NC}"

# Fonction pour afficher les messages
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Vérifier les permissions root
check_root() {
    if [[ $EUID -ne 0 ]]; then
        print_error "Ce script doit être exécuté en tant que root (sudo)"
        exit 1
    fi
}

# Détecter la distribution Linux
detect_os() {
    if [[ -f /etc/debian_version ]]; then
        OS="debian"
        PACKAGE_MANAGER="apt-get"
    elif [[ -f /etc/redhat-release ]]; then
        OS="redhat"
        PACKAGE_MANAGER="yum"
    elif [[ -f /etc/arch-release ]]; then
        OS="arch"
        PACKAGE_MANAGER="pacman"
    else
        print_error "Distribution Linux non supportée"
        exit 1
    fi
    
    print_status "Distribution détectée: $OS"
}

# Mettre à jour les paquets
update_packages() {
    print_status "Mise à jour des paquets système..."
    
    case $OS in
        "debian")
            $PACKAGE_MANAGER update -y
            ;;
        "redhat")
            $PACKAGE_MANAGER update -y
            ;;
        "arch")
            $PACKAGE_MANAGER -Sy
            ;;
    esac
}

# Installer les dépendances système
install_system_dependencies() {
    print_status "Installation des dépendances système..."
    
    case $OS in
        "debian")
            $PACKAGE_MANAGER install -y \
                apache2 \
                mysql-server \
                php \
                php-mysql \
                php-gd \
                php-xml \
                php-zip \
                php-curl \
                php-mbstring \
                php-json \
                composer \
                tesseract-ocr \
                tesseract-ocr-fra \
                poppler-utils \
                imagemagick \
                git \
                curl \
                wget \
                unzip
            ;;
        "redhat")
            $PACKAGE_MANAGER install -y \
                httpd \
                mysql-server \
                php \
                php-mysql \
                php-gd \
                php-xml \
                php-zip \
                php-curl \
                php-mbstring \
                php-json \
                composer \
                tesseract \
                tesseract-langpack-fra \
                poppler-utils \
                ImageMagick \
                git \
                curl \
                wget \
                unzip
            ;;
    esac
}

# Configurer Apache
configure_apache() {
    print_status "Configuration d'Apache..."
    
    # Activer les modules nécessaires
    if [[ $OS == "debian" ]]; then
        a2enmod rewrite
        a2enmod headers
        
        # Créer le virtual host
        cat > /etc/apache2/sites-available/bulletins.conf << EOF
<VirtualHost *:80>
    ServerName bulletins.local
    DocumentRoot $WEB_DIR
    
    <Directory $WEB_DIR>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    <Directory $WEB_DIR/api>
        AllowOverride All
        Require all granted
    </Directory>
    
    # Sécurité - Cacher les fichiers sensibles
    <FilesMatch "\.(htaccess|htpasswd|ini|log|sh)$">
        Require all denied
    </FilesMatch>
    
    # Logs
    ErrorLog \${APACHE_LOG_DIR}/bulletins_error.log
    CustomLog \${APACHE_LOG_DIR}/bulletins_access.log combined
</VirtualHost>
EOF
        
        a2ensite bulletins.conf
        a2dissite 000-default.conf
        
    elif [[ $OS == "redhat" ]]; then
        # Configuration CentOS/RHEL
        cat > /etc/httpd/conf.d/bulletins.conf << EOF
<VirtualHost *:80>
    ServerName bulletins.local
    DocumentRoot $WEB_DIR
    
    <Directory $WEB_DIR>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog /var/log/httpd/bulletins_error.log
    CustomLog /var/log/httpd/bulletins_access.log combined
</VirtualHost>
EOF
    fi
    
    # Redémarrer Apache
    systemctl restart apache2 || systemctl restart httpd
    systemctl enable apache2 || systemctl enable httpd
}

# Configurer MySQL
configure_mysql() {
    print_status "Configuration de MySQL..."
    
    # Démarrer MySQL
    systemctl start mysql || systemctl start mysqld
    systemctl enable mysql || systemctl enable mysqld
    
    # Sécuriser MySQL (version simplifiée)
    mysql -e "DELETE FROM mysql.user WHERE User='';"
    mysql -e "DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');"
    mysql -e "DROP DATABASE IF EXISTS test;"
    mysql -e "DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';"
    mysql -e "FLUSH PRIVILEGES;"
    
    # Créer la base de données
    mysql -u$DB_USER -p$DB_PASS -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    mysql -u$DB_USER -p$DB_PASS -e "GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';"
    mysql -u$DB_USER -p$DB_PASS -e "FLUSH PRIVILEGES;"
    
    print_status "Base de données '$DB_NAME' créée avec succès"
}

# Installer l'application
install_application() {
    print_status "Installation de l'application..."
    
    # Créer le répertoire web
    mkdir -p $WEB_DIR
    cd $WEB_DIR
    
    # Si c'est un nouveau répertoire, copier les fichiers
    if [[ ! -f "index.html" ]]; then
        print_status "Copie des fichiers de l'application..."
        # Ici vous copieriez vos fichiers depuis le dépôt ou le répertoire source
        # cp -r /path/to/source/* $WEB_DIR/
    fi
    
    # Installer les dépendances PHP
    if [[ -f "composer.json" ]]; then
        print_status "Installation des dépendances PHP..."
        composer install --no-dev --optimize-autoloader
    fi
    
    # Créer les répertoires nécessaires
    mkdir -p uploads bulletins logs backups temp
    
    # Configurer les permissions
    chown -R $APACHE_USER:$APACHE_USER $WEB_DIR
    chmod -R 755 $WEB_DIR
    chmod -R 777 $WEB_DIR/uploads $WEB_DIR/bulletins $WEB_DIR/logs $WEB_DIR/backups $WEB_DIR/temp
    
    # Créer le fichier .htaccess pour la sécurité
    cat > $WEB_DIR/.htaccess << 'EOF'
# Sécurité générale
ServerTokens Prod
Options -Indexes -Includes -ExecCGI

# Protection des fichiers sensibles
<FilesMatch "\.(htaccess|htpasswd|ini|log|sh|sql)$">
    Require all denied
</FilesMatch>

# Réécriture d'URL
RewriteEngine On

# API Routes
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^api/(.*)$ api/index.php [QSA,L]

# Frontend SPA
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_URI} !^/api/
RewriteRule ^(.*)$ index.html [QSA,L]

# Cache pour les assets
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType image/png "access plus 1 month"
    ExpiresByType image/jpg "access plus 1 month"
    ExpiresByType image/jpeg "access plus 1 month"
    ExpiresByType image/gif "access plus 1 month"
    ExpiresByType image/svg+xml "access plus 1 month"
</IfModule>

# Compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/xml
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE application/xhtml+xml
    AddOutputFilterByType DEFLATE application/rss+xml
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/x-javascript
</IfModule>
EOF
}

# Importer le schéma de base de données
import_database_schema() {
    print_status "Import du schéma de base de données..."
    
    if [[ -f "database/schema.sql" ]]; then
        mysql -u$DB_USER -p$DB_PASS $DB_NAME < database/schema.sql
        print_status "Schéma de base de données importé"
    else
        print_warning "Fichier schema.sql non trouvé, création manuelle nécessaire"
    fi
}

# Configurer les tâches CRON
setup_cron_jobs() {
    print_status "Configuration des tâches CRON..."
    
    # Créer le script de maintenance
    cat > $WEB_DIR/scripts/maintenance.php << 'EOF'
<?php
/**
 * Script de maintenance automatique
 * Usage: php maintenance.php [task]
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/Logger.php';
require_once __DIR__ . '/../services/MonitoringService.php';
require_once __DIR__ . '/../services/BackupService.php';

$logger = Logger::getInstance();
$database = new Database();
$db = $database->connect();

$task = $argv[1] ?? 'health';

switch($task) {
    case 'health':
        $monitoring = new MonitoringService($db);
        $health = $monitoring->checkSystemHealth();
        
        if ($health['status'] !== 'healthy') {
            $logger->error('System health check failed', $health, 'maintenance');
            // Envoyer alerte email si configuré
        } else {
            $logger->info('System health check passed', [], 'maintenance');
        }
        break;
        
    case 'backup':
        $backup = new BackupService($db);
        $result = $backup->createFullBackup();
        
        if ($result['success']) {
            $logger->info('Scheduled backup completed', $result, 'maintenance');
        } else {
            $logger->error('Scheduled backup failed', $result, 'maintenance');
        }
        break;
        
    case 'cleanup':
        // Nettoyer les fichiers temporaires
        $tempDir = __DIR__ . '/../temp/';
        $files = glob($tempDir . '*');
        $cleaned = 0;
        
        foreach ($files as $file) {
            if (filemtime($file) < time() - (24 * 60 * 60)) { // Plus de 24h
                if (unlink($file)) $cleaned++;
            }
        }
        
        // Nettoyer les logs anciens
        $logDir = __DIR__ . '/../logs/';
        $logFiles = glob($logDir . '*.log.*');
        
        foreach ($logFiles as $file) {
            if (filemtime($file) < time() - (30 * 24 * 60 * 60)) { // Plus de 30 jours
                unlink($file);
            }
        }
        
        $logger->info("Cleanup completed: {$cleaned} temp files removed", [], 'maintenance');
        break;
        
    case 'stats':
        // Calculer et sauvegarder les statistiques quotidiennes
        $monitoring = new MonitoringService($db);
        $stats = $monitoring->getSystemMetrics('24h');
        
        $query = "INSERT INTO daily_stats (date, total_requests, error_rate, uploads, bulletins_generated) 
                  VALUES (CURDATE(), :requests, :errors, :uploads, :bulletins)
                  ON DUPLICATE KEY UPDATE 
                  total_requests = :requests, error_rate = :errors, 
                  uploads = :uploads, bulletins_generated = :bulletins";
        
        try {
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':requests' => $stats['requests']['total_requests'],
                ':errors' => $stats['requests']['error_requests'],
                ':uploads' => $stats['uploads']['total_uploads'],
                ':bulletins' => $stats['bulletins']['bulletins_generated']
            ]);
            
            $logger->info('Daily statistics saved', $stats, 'maintenance');
        } catch (Exception $e) {
            $logger->error('Failed to save daily statistics: ' . $e->getMessage(), [], 'maintenance');
        }
        break;
        
    default:
        echo "Usage: php maintenance.php [health|backup|cleanup|stats]\n";
        exit(1);
}

echo "Maintenance task '$task' completed.\n";
EOF

    chmod +x $WEB_DIR/scripts/maintenance.php
    
    # Ajouter les tâches CRON
    crontab -l > /tmp/current_cron 2>/dev/null || touch /tmp/current_cron
    
    # Health check toutes les 15 minutes
    echo "*/15 * * * * /usr/bin/php $WEB_DIR/scripts/maintenance.php health" >> /tmp/current_cron
    
    # Backup quotidien à 2h du matin
    echo "0 2 * * * /usr/bin/php $WEB_DIR/scripts/maintenance.php backup" >> /tmp/current_cron
    
    # Nettoyage hebdomadaire le dimanche à 3h
    echo "0 3 * * 0 /usr/bin/php $WEB_DIR/scripts/maintenance.php cleanup" >> /tmp/current_cron
    
    # Statistiques quotidiennes à minuit
    echo "0 0 * * * /usr/bin/php $WEB_DIR/scripts/maintenance.php stats" >> /tmp/current_cron
    
    crontab /tmp/current_cron
    rm /tmp/current_cron
    
    print_status "Tâches CRON configurées"
}

# Configurer la sécurité PHP
configure_php_security() {
    print_status "Configuration de la sécurité PHP..."
    
    PHP_INI="/etc/php/$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')/apache2/php.ini"
    
    if [[ -f $PHP_INI ]]; then
        # Backup du fichier original
        cp $PHP_INI $PHP_INI.backup
        
        # Configurations de sécurité
        sed -i 's/expose_php = On/expose_php = Off/' $PHP_INI
        sed -i 's/display_errors = On/display_errors = Off/' $PHP_INI
        sed -i 's/;log_errors = On/log_errors = On/' $PHP_INI
        sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 10M/' $PHP_INI
        sed -i 's/post_max_size = 8M/post_max_size = 12M/' $PHP_INI
        sed -i 's/max_execution_time = 30/max_execution_time = 300/' $PHP_INI
        sed -i 's/memory_limit = 128M/memory_limit = 256M/' $PHP_INI
        
        # Sessions sécurisées
        sed -i 's/session.cookie_httponly =/session.cookie_httponly = 1/' $PHP_INI
        sed -i 's/session.use_only_cookies = 1/session.use_strict_mode = 1/' $PHP_INI
        
        print_status "Configuration PHP sécurisée"
    else
        print_warning "Fichier php.ini non trouvé à $PHP_INI"
    fi
}

# Créer l'utilisateur administrateur
create_admin_user() {
    print_status "Création de l'utilisateur administrateur..."
    
    read -p "Email administrateur: " ADMIN_EMAIL
    read -s -p "Mot de passe administrateur: " ADMIN_PASSWORD
    echo
    
    # Hash du mot de passe
    ADMIN_HASH=$(php -r "echo password_hash('$ADMIN_PASSWORD', PASSWORD_DEFAULT);")
    
    mysql -u$DB_USER -p$DB_PASS $DB_NAME << EOF
INSERT INTO etablissements (nom, adresse, directeur) 
VALUES ('Mon Établissement', 'Adresse à modifier', 'Directeur à modifier');

INSERT INTO annees_scolaires (libelle, date_debut, date_fin, active, etablissement_id)
VALUES ('$(date +%Y)-$(date -d '+1 year' +%Y)', '$(date +%Y)-09-01', '$(date -d '+1 year' +%Y)-07-31', 1, 1);

INSERT INTO users (nom, prenoms, email, type_user, matricule, mot_de_passe, etablissement_id, actif)
VALUES ('Admin', 'Système', '$ADMIN_EMAIL', 'admin', 'ADM$(date +%Y)001', '$ADMIN_HASH', 1, 1);
EOF
    
    print_status "Utilisateur administrateur créé: $ADMIN_EMAIL"
}

# Test final de l'installation
test_installation() {
    print_status "Test de l'installation..."
    
    # Test Apache
    if systemctl is-active --quiet apache2 || systemctl is-active --quiet httpd; then
        print_status "✓ Apache fonctionne"
    else
        print_error "✗ Apache ne fonctionne pas"
    fi
    
    # Test MySQL
    if systemctl is-active --quiet mysql || systemctl is-active --quiet mysqld; then
        print_status "✓ MySQL fonctionne"
    else
        print_error "✗ MySQL ne fonctionne pas"
    fi
    
    # Test PHP
    if php -v >/dev/null 2>&1; then
        print_status "✓ PHP installé: $(php -r 'echo PHP_VERSION;')"
    else
        print_error "✗ PHP non fonctionnel"
    fi
    
    # Test base de données
    if mysql -u$DB_USER -p$DB_PASS -e "USE $DB_NAME; SELECT COUNT(*) FROM users;" >/dev/null 2>&1; then
        print_status "✓ Base de données accessible"
    else
        print_error "✗ Base de données non accessible"
    fi
    
    # Test permissions
    if [[ -w "$WEB_DIR/uploads" ]] && [[ -w "$WEB_DIR/bulletins" ]]; then
        print_status "✓ Permissions des répertoires correctes"
    else
        print_error "✗ Problème de permissions"
    fi
    
    # Test Tesseract
    if tesseract --version >/dev/null 2>&1; then
        print_status "✓ Tesseract OCR installé"
    else
        print_warning "⚠ Tesseract OCR non installé (fonctionnalité OCR indisponible)"
    fi
}

# Afficher les informations finales
show_final_info() {
    echo
    echo -e "${GREEN}=================================="
    echo -e "     INSTALLATION TERMINÉE"
    echo -e "==================================${NC}"
    echo
    echo -e "${BLUE}Informations d'accès:${NC}"
    echo -e "URL: ${YELLOW}http://$(hostname -I | awk '{print $1}')/bulletins${NC}"
    echo -e "URL locale: ${YELLOW}http://localhost/bulletins${NC}"
    echo
    echo -e "${BLUE}Base de données:${NC}"
    echo -e "Nom: ${YELLOW}$DB_NAME${NC}"
    echo -e "Utilisateur: ${YELLOW}$DB_USER${NC}"
    echo
    echo -e "${BLUE}Répertoires importants:${NC}"
    echo -e "Application: ${YELLOW}$WEB_DIR${NC}"
    echo -e "Logs: ${YELLOW}$WEB_DIR/logs/${NC}"
    echo -e "Uploads: ${YELLOW}$WEB_DIR/uploads/${NC}"
    echo -e "Bulletins: ${YELLOW}$WEB_DIR/bulletins/${NC}"
    echo -e "Backups: ${YELLOW}$WEB_DIR/backups/${NC}"
    echo
    echo -e "${BLUE}Maintenance:${NC}"
    echo -e "Health check: ${YELLOW}php $WEB_DIR/scripts/maintenance.php health${NC}"
    echo -e "Backup manuel: ${YELLOW}php $WEB_DIR/scripts/maintenance.php backup${NC}"
    echo -e "Nettoyage: ${YELLOW}php $WEB_DIR/scripts/maintenance.php cleanup${NC}"
    echo
    echo -e "${GREEN}Pour finaliser l'installation:${NC}"
    echo -e "1. Configurer /etc/hosts: ${YELLOW}echo '127.0.0.1 bulletins.local' >> /etc/hosts${NC}"
    echo -e "2. Modifier la configuration dans: ${YELLOW}$WEB_DIR/config/database.php${NC}"
    echo -e "3. Accéder à l'interface web et configurer l'établissement"
    echo
}

# Menu principal d'installation
main_installation() {
    clear
    echo -e "${BLUE}Que souhaitez-vous installer ?${NC}"
    echo "1) Installation complète (recommandé)"
    echo "2) Installation personnalisée"
    echo "3) Mise à jour uniquement"
    echo "4) Test de l'installation existante"
    echo "0) Quitter"
    
    read -p "Votre choix [1]: " CHOICE
    CHOICE=${CHOICE:-1}
    
    case $CHOICE in
        1)
            print_status "Installation complète démarrée..."
            check_root
            detect_os
            update_packages
            install_system_dependencies
            configure_apache
            configure_mysql
            install_application
            import_database_schema
            setup_cron_jobs
            configure_php_security
            create_admin_user
            test_installation
            show_final_info
            ;;
        2)
            print_status "Installation personnalisée..."
            # Menu personnalisé ici
            ;;
        3)
            print_status "Mise à jour uniquement..."
            install_application
            test_installation
            ;;
        4)
            print_status "Test de l'installation..."
            test_installation
            ;;
        0)
            print_status "Installation annulée"
            exit 0
            ;;
        *)
            print_error "Choix invalide"
            exit 1
            ;;
    esac
}

# Point d'entrée principal
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main_installation
fi