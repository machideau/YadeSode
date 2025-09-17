#!/bin/bash

# deploy.sh - Script de déploiement et mise à jour
# Usage: ./deploy.sh [environment] [version]

set -e

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_NAME="bulletin-system"
ENVIRONMENTS=("development" "staging" "production")

# Couleurs
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Fonctions utilitaires
print_status() { echo -e "${GREEN}[INFO]${NC} $1"; }
print_warning() { echo -e "${YELLOW}[WARNING]${NC} $1"; }
print_error() { echo -e "${RED}[ERROR]${NC} $1"; }

# Configuration par environnement
load_environment_config() {
    local env=$1
    
    case $env in
        "development")
            WEB_DIR="/var/www/html/bulletins-dev"
            DB_NAME="bulletins_dev"
            BACKUP_RETENTION="7"
            DEBUG_MODE="true"
            ;;
        "staging")
            WEB_DIR="/var/www/html/bulletins-staging"
            DB_NAME="bulletins_staging"
            BACKUP_RETENTION="14"
            DEBUG_MODE="true"
            ;;
        "production")
            WEB_DIR="/var/www/html/bulletins"
            DB_NAME="bulletins_system"
            BACKUP_RETENTION="30"
            DEBUG_MODE="false"
            ;;
        *)
            print_error "Environnement non supporté: $env"
            print_error "Environnements disponibles: ${ENVIRONMENTS[*]}"
            exit 1
            ;;
    esac
    
    print_status "Environnement: $env"
    print_status "Répertoire: $WEB_DIR"
    print_status "Base de données: $DB_NAME"
}

# Vérifications préalables
pre_deployment_checks() {
    print_status "Vérifications préalables..."
    
    # Vérifier les permissions
    if [[ ! -w "$WEB_DIR" ]]; then
        print_error "Pas de permissions d'écriture sur $WEB_DIR"
        exit 1
    fi
    
    # Vérifier l'espace disque (minimum 1GB libre)
    local available_space=$(df "$WEB_DIR" | tail -1 | awk '{print $4}')
    if [[ $available_space -lt 1048576 ]]; then
        print_warning "Espace disque faible: $(($available_space / 1024))MB disponibles"
    fi
    
    # Vérifier que les services sont actifs
    systemctl is-active --quiet apache2 || systemctl is-active --quiet httpd || {
        print_error "Apache n'est pas actif"
        exit 1
    }
    
    systemctl is-active --quiet mysql || systemctl is-active --quiet mysqld || {
        print_error "MySQL n'est pas actif"
        exit 1
    }
    
    print_status "✓ Vérifications préalables réussies"
}

# Créer une sauvegarde avant déploiement
create_backup() {
    print_status "Création de la sauvegarde..."
    
    local backup_dir="$WEB_DIR/backups/pre-deploy"
    local timestamp=$(date +%Y%m%d_%H%M%S)
    local backup_name="backup_${timestamp}"
    
    mkdir -p "$backup_dir"
    
    # Backup de l'application
    if [[ -d "$WEB_DIR" ]]; then
        print_status "Sauvegarde des fichiers..."
        tar -czf "$backup_dir/${backup_name}_files.tar.gz" \
            -C "$(dirname "$WEB_DIR")" \
            --exclude='backups' \
            --exclude='logs' \
            --exclude='temp' \
            "$(basename "$WEB_DIR")" 2>/dev/null || true
    fi
    
    # Backup de la base de données
    print_status "Sauvegarde de la base de données..."
    mysqldump --single-transaction --routines --triggers \
        "$DB_NAME" > "$backup_dir/${backup_name}_database.sql" 2>/dev/null || {
        print_warning "Échec de la sauvegarde de la base de données"
    }
    
    # Créer un fichier de métadonnées
    cat > "$backup_dir/${backup_name}_metadata.json" << EOF
{
    "timestamp": "$timestamp",
    "environment": "$ENVIRONMENT",
    "version_before": "$(git describe --tags --always 2>/dev/null || echo 'unknown')",
    "created_by": "$(whoami)",
    "hostname": "$(hostname)"
}
EOF
    
    print_status "✓ Sauvegarde créée: $backup_name"
    echo "$backup_dir/$backup_name" > /tmp/last_backup_path
}

# Télécharger et préparer la nouvelle version
download_and_prepare() {
    local version=$1
    print_status "Téléchargement de la version $version..."
    
    local temp_dir="/tmp/${PROJECT_NAME}_${version}"
    rm -rf "$temp_dir"
    mkdir -p "$temp_dir"
    
    # Si c'est un déploiement depuis Git
    if [[ -n "$GIT_REPOSITORY" ]]; then
        git clone --depth 1 --branch "$version" "$GIT_REPOSITORY" "$temp_dir"
        cd "$temp_dir"
        
        # Installer les dépendances
        if [[ -f "composer.json" ]]; then
            print_status "Installation des dépendances PHP..."
            composer install --no-dev --optimize-autoloader --no-interaction
        fi
        
        # Build des assets si nécessaire
        if [[ -f "package.json" ]] && command -v npm >/dev/null; then
            print_status "Build des assets frontend..."
            npm ci --production
            npm run build 2>/dev/null || true
        fi
        
    else
        # Téléchargement depuis une archive
        local archive_url="https://releases.example.com/${PROJECT_NAME}/${version}.tar.gz"
        wget -q "$archive_url" -O "${temp_dir}.tar.gz"
        tar -xzf "${temp_dir}.tar.gz" -C "$temp_dir" --strip-components=1
    fi
    
    print_status "✓ Version $version préparée dans $temp_dir"
    echo "$temp_dir" > /tmp/deploy_temp_dir
}

# Effectuer les migrations de base de données
run_database_migrations() {
    print_status "Exécution des migrations de base de données..."
    
    local migrations_dir="$WEB_DIR/database/migrations"
    
    if [[ -d "$migrations_dir" ]]; then
        # Créer la table de migrations si elle n'existe pas
        mysql "$DB_NAME" << 'EOF'
CREATE TABLE IF NOT EXISTS migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration VARCHAR(255) NOT NULL,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_migration (migration)
);
EOF
        
        # Exécuter les migrations non appliquées
        for migration_file in "$migrations_dir"/*.sql; do
            if [[ -f "$migration_file" ]]; then
                local migration_name=$(basename "$migration_file" .sql)
                
                # Vérifier si la migration a déjà été exécutée
                local count=$(mysql -sN "$DB_NAME" << EOF
SELECT COUNT(*) FROM migrations WHERE migration = '$migration_name';
EOF
)
                
                if [[ "$count" -eq "0" ]]; then
                    print_status "Application de la migration: $migration_name"
                    
                    if mysql "$DB_NAME" < "$migration_file"; then
                        mysql "$DB_NAME" << EOF
INSERT INTO migrations (migration) VALUES ('$migration_name');
EOF
                        print_status "✓ Migration $migration_name appliquée"
                    else
                        print_error "✗ Échec de la migration $migration_name"
                        return 1
                    fi
                fi
            fi
        done
    else
        print_status "Aucun répertoire de migrations trouvé"
    fi
    
    print_status "✓ Migrations terminées"
}

# Déployer la nouvelle version
deploy_application() {
    local temp_dir=$(cat /tmp/deploy_temp_dir)
    print_status "Déploiement de l'application..."
    
    # Mode maintenance
    create_maintenance_page
    
    # Synchroniser les fichiers (en préservant certains répertoires)
    print_status "Synchronisation des fichiers..."
    
    rsync -av --delete \
        --exclude 'config/database.php' \
        --exclude 'uploads/' \
        --exclude 'bulletins/' \
        --exclude 'logs/' \
        --exclude 'backups/' \
        --exclude 'temp/' \
        --exclude '.git/' \
        "$temp_dir/" "$WEB_DIR/"
    
    # Restaurer les permissions
    chown -R www-data:www-data "$WEB_DIR"
    chmod -R 755 "$WEB_DIR"
    chmod -R 777 "$WEB_DIR/uploads" "$WEB_DIR/bulletins" "$WEB_DIR/logs" "$WEB_DIR/backups" "$WEB_DIR/temp"
    
    # Vider les caches si nécessaire
    if [[ -d "$WEB_DIR/cache" ]]; then
        rm -rf "$WEB_DIR/cache/*"
    fi
    
    # Exécuter les migrations
    run_database_migrations
    
    # Désactiver le mode maintenance
    remove_maintenance_page
    
    print_status "✓ Application déployée"
}

# Page de maintenance
create_maintenance_page() {
    print_status "Activation du mode maintenance..."
    
    cat > "$WEB_DIR/maintenance.html" << 'EOF'
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance en cours</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .container {
            text-align: center;
            background: rgba(255,255,255,0.1);
            padding: 40px;
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }
        .spinner {
            border: 3px solid rgba(255,255,255,0.3);
            border-top: 3px solid white;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Maintenance en cours</h1>
        <div class="spinner"></div>
        <p>Le système de gestion des bulletins est temporairement indisponible pour maintenance.</p>
        <p>Nous serons de retour dans quelques minutes.</p>
        <p><small>Merci de votre patience.</small></p>
    </div>
    
    <script>
        // Auto-refresh toutes les 30 secondes
        setTimeout(() => location.reload(), 30000);
    </script>
</body>
</html>
EOF
    
    # Rediriger tout le trafic vers la page de maintenance
    cat > "$WEB_DIR/.htaccess.maintenance" << 'EOF'
RewriteEngine On
RewriteCond %{REQUEST_URI} !^/maintenance\.html$
RewriteCond %{REMOTE_ADDR} !^127\.0\.0\.1$
RewriteRule ^(.*)$ /maintenance.html [R=503,L]

ErrorDocument 503 /maintenance.html
Header always set Retry-After "120"
EOF
    
    mv "$WEB_DIR/.htaccess" "$WEB_DIR/.htaccess.backup" 2>/dev/null || true
    mv "$WEB_DIR/.htaccess.maintenance" "$WEB_DIR/.htaccess"
}

remove_maintenance_page() {
    print_status "Désactivation du mode maintenance..."
    
    mv "$WEB_DIR/.htaccess.backup" "$WEB_DIR/.htaccess" 2>/dev/null || true
    rm -f "$WEB_DIR/maintenance.html"
}

# Tests post-déploiement
run_post_deploy_tests() {
    print_status "Exécution des tests post-déploiement..."
    
    # Test de connectivité base de données
    if php -r "
        require_once '$WEB_DIR/config/database.php';
        try {
            \$db = new Database();
            \$conn = \$db->connect();
            \$stmt = \$conn->query('SELECT 1');
            echo 'DB_OK';
        } catch (Exception \$e) {
            echo 'DB_ERROR: ' . \$e->getMessage();
            exit(1);
        }
    " | grep -q "DB_OK"; then
        print_status "✓ Test base de données: OK"
    else
        print_error "✗ Test base de données: ÉCHEC"
        return 1
    fi
    
    # Test API basique
    local api_url="http://localhost$(basename "$WEB_DIR")/api/health"
    if curl -s -f "$api_url" >/dev/null; then
        print_status "✓ Test API: OK"
    else
        print_warning "⚠ Test API: Non accessible (normal si pas encore configuré)"
    fi
    
    # Test permissions des répertoires
    local dirs=("uploads" "bulletins" "logs" "backups")
    for dir in "${dirs[@]}"; do
        if [[ -w "$WEB_DIR/$dir" ]]; then
            print_status "✓ Permissions $dir: OK"
        else
            print_error "✗ Permissions $dir: ÉCHEC"
            return 1
        fi
    done
    
    print_status "✓ Tests post-déploiement terminés"
}

# Rollback en cas d'échec
rollback() {
    print_warning "Rollback en cours..."
    
    local backup_path=$(cat /tmp/last_backup_path 2>/dev/null)
    
    if [[ -n "$backup_path" ]] && [[ -f "${backup_path}_files.tar.gz" ]]; then
        # Restaurer les fichiers
        print_status "Restauration des fichiers..."
        tar -xzf "${backup_path}_files.tar.gz" -C "$(dirname "$WEB_DIR")"
        
        # Restaurer la base de données
        if [[ -f "${backup_path}_database.sql" ]]; then
            print_status "Restauration de la base de données..."
            mysql "$DB_NAME" < "${backup_path}_database.sql"
        fi
        
        # Restaurer les permissions
        chown -R www-data:www-data "$WEB_DIR"
        
        print_status "✓ Rollback terminé"
    else
        print_error "Impossible de faire le rollback: sauvegarde non trouvée"
        return 1
    fi
}

# Nettoyage post-déploiement
cleanup() {
    print_status "Nettoyage..."
    
    # Supprimer les fichiers temporaires
    local temp_dir=$(cat /tmp/deploy_temp_dir 2>/dev/null)
    if [[ -n "$temp_dir" ]] && [[ -d "$temp_dir" ]]; then
        rm -rf "$temp_dir"
        print_status "✓ Répertoire temporaire supprimé"
    fi
    
    # Nettoyer les anciens backups
    find "$WEB_DIR/backups/pre-deploy" -type f -mtime +$BACKUP_RETENTION -delete 2>/dev/null || true
    
    # Nettoyer les fichiers temporaires système
    rm -f /tmp/last_backup_path /tmp/deploy_temp_dir
    
    # Redémarrer les services si nécessaire
    systemctl reload apache2 || systemctl reload httpd
    
    print_status "✓ Nettoyage terminé"
}

# Afficher le statut du déploiement
show_deployment_status() {
    local version=$1
    local environment=$2
    
    echo
    echo -e "${GREEN}=================================="
    echo -e "    DÉPLOIEMENT TERMINÉ"
    echo -e "==================================${NC}"
    echo
    echo -e "${BLUE}Informations:${NC}"
    echo -e "Version: ${YELLOW}$version${NC}"
    echo -e "Environnement: ${YELLOW}$environment${NC}"
    echo -e "Répertoire: ${YELLOW}$WEB_DIR${NC}"
    echo -e "Timestamp: ${YELLOW}$(date)${NC}"
    echo
    echo -e "${BLUE}URLs:${NC}"
    echo -e "Application: ${YELLOW}http://$(hostname -I | awk '{print $1}')$(basename "$WEB_DIR")${NC}"
    echo -e "API: ${YELLOW}http://$(hostname -I | awk '{print $1}')$(basename "$WEB_DIR")/api${NC}"
    echo
    echo -e "${BLUE}Monitoring:${NC}"
    echo -e "Logs: ${YELLOW}tail -f $WEB_DIR/logs/*.log${NC}"
    echo -e "Health: ${YELLOW}curl http://localhost$(basename "$WEB_DIR")/api/health${NC}"
    echo
}

# Fonction principale de déploiement
main_deploy() {
    local environment=${1:-"development"}
    local version=${2:-"main"}
    
    print_status "Démarrage du déploiement..."
    print_status "Environnement: $environment"
    print_status "Version: $version"
    
    # Charger la configuration de l'environnement
    load_environment_config "$environment"
    
    # Piège pour gérer les erreurs
    trap 'print_error "Déploiement échoué!"; rollback; cleanup; exit 1' ERR
    
    # Étapes du déploiement
    pre_deployment_checks
    create_backup
    download_and_prepare "$version"
    deploy_application
    run_post_deploy_tests
    cleanup
    
    # Enregistrer les informations du déploiement
    cat > "$WEB_DIR/.deployment_info" << EOF
{
    "version": "$version",
    "environment": "$environment",
    "deployed_at": "$(date -Iseconds)",
    "deployed_by": "$(whoami)",
    "hostname": "$(hostname)",
    "git_commit": "$(cd $(cat /tmp/deploy_temp_dir 2>/dev/null || echo .) && git rev-parse HEAD 2>/dev/null || echo 'unknown')"
}
EOF
    
    show_deployment_status "$version" "$environment"
    
    print_status "✅ Déploiement réussi!"
}

# Fonction de rollback manuel
manual_rollback() {
    local environment=${1:-"production"}
    
    load_environment_config "$environment"
    
    print_warning "Rollback manuel vers la dernière sauvegarde..."
    
    # Chercher la dernière sauvegarde
    local latest_backup=$(ls -t "$WEB_DIR/backups/pre-deploy" | head -1)
    
    if [[ -n "$latest_backup" ]]; then
        echo "$WEB_DIR/backups/pre-deploy/$latest_backup" > /tmp/last_backup_path
        rollback
        print_status "Rollback terminé vers: $latest_backup"
    else
        print_error "Aucune sauvegarde trouvée pour le rollback"
        exit 1
    fi
}

# Fonction de mise à jour simple
quick_update() {
    local environment=${1:-"development"}
    
    load_environment_config "$environment"
    
    print_status "Mise à jour rapide (git pull)..."
    
    cd "$WEB_DIR"
    
    # Vérifier si c'est un dépôt git
    if [[ -d ".git" ]]; then
        # Sauvegarder les modifications locales
        git stash push -m "Auto-stash before update $(date)"
        
        # Mettre à jour depuis le dépôt
        git pull origin main
        
        # Mettre à jour les dépendances si nécessaire
        if [[ -f "composer.json" ]]; then
            composer install --no-dev --optimize-autoloader
        fi
        
        # Redémarrer les services
        systemctl reload apache2 || systemctl reload httpd
        
        print_status "✅ Mise à jour rapide terminée"
    else
        print_error "Le répertoire n'est pas un dépôt Git"
        exit 1
    fi
}

# Afficher les informations de déploiement actuelles
show_current_deployment() {
    local environment=${1:-"production"}
    
    load_environment_config "$environment"
    
    if [[ -f "$WEB_DIR/.deployment_info" ]]; then
        echo -e "${BLUE}Déploiement actuel:${NC}"
        cat "$WEB_DIR/.deployment_info" | python3 -m json.tool 2>/dev/null || cat "$WEB_DIR/.deployment_info"
    else
        print_warning "Aucune information de déploiement trouvée"
    fi
    
    # Afficher le statut des services
    echo -e "\n${BLUE}Statut des services:${NC}"
    systemctl is-active apache2 httpd 2>/dev/null || echo "Apache: Inconnu"
    systemctl is-active mysql mysqld 2>/dev/null || echo "MySQL: Inconnu"
    
    # Afficher l'espace disque
    echo -e "\n${BLUE}Espace disque:${NC}"
    df -h "$WEB_DIR" | tail -1
}

# Menu principal
show_help() {
    echo "Usage: $0 [COMMAND] [ENVIRONMENT] [VERSION]"
    echo
    echo "COMMANDS:"
    echo "  deploy      Déploiement complet (défaut)"
    echo "  rollback    Rollback vers la dernière sauvegarde"
    echo "  update      Mise à jour rapide (git pull)"
    echo "  status      Afficher le statut actuel"
    echo "  help        Afficher cette aide"
    echo
    echo "ENVIRONMENTS:"
    echo "  development (défaut)"
    echo "  staging"
    echo "  production"
    echo
    echo "EXAMPLES:"
    echo "  $0 deploy production v1.2.0"
    echo "  $0 rollback production"
    echo "  $0 update development"
    echo "  $0 status production"
}

# Point d'entrée principal
main() {
    local command=${1:-"deploy"}
    local environment=${2:-"development"}
    local version=${3:-"main"}
    
    # Vérifier si on est dans le bon répertoire ou si les fichiers existent
    if [[ ! -f "$(dirname "$0")/install.sh" ]] && [[ "$command" == "deploy" ]]; then
        print_warning "Scripts d'installation non trouvés dans le répertoire courant"
    fi
    
    case $command in
        "deploy")
            main_deploy "$environment" "$version"
            ;;
        "rollback")
            manual_rollback "$environment"
            ;;
        "update")
            quick_update "$environment"
            ;;
        "status")
            show_current_deployment "$environment"
            ;;
        "help"|"-h"|"--help")
            show_help
            ;;
        *)
            print_error "Commande inconnue: $command"
            show_help
            exit 1
            ;;
    esac
}

# Configuration des variables d'environnement
export ENVIRONMENT=""
export WEB_DIR=""
export DB_NAME=""
export BACKUP_RETENTION=""
export DEBUG_MODE=""

# Configuration Git (optionnel)
export GIT_REPOSITORY=""

# Exécution si le script est appelé directement
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi