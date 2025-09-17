<?php
class StatisticsService {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function getDashboardStats($etablissementId) {
        $stats = [];
        
        // Nombre total d'élèves actifs
        $query = "SELECT COUNT(*) as total FROM eleves e 
                  JOIN users u ON e.user_id = u.id 
                  WHERE u.etablissement_id = :etablissement_id AND e.statut = 'inscrit'";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':etablissement_id', $etablissementId, PDO::PARAM_INT);
        $stmt->execute();
        $stats['total_eleves'] = $stmt->fetch()['total'];
        
        // Nombre de classes
        $query = "SELECT COUNT(*) as total FROM classes WHERE etablissement_id = :etablissement_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':etablissement_id', $etablissementId, PDO::PARAM_INT);
        $stmt->execute();
        $stats['total_classes'] = $stmt->fetch()['total'];
        
        // Nombre de professeurs
        $query = "SELECT COUNT(*) as total FROM users 
                  WHERE etablissement_id = :etablissement_id AND type_user = 'professeur' AND actif = 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':etablissement_id', $etablissementId, PDO::PARAM_INT);
        $stmt->execute();
        $stats['total_professeurs'] = $stmt->fetch()['total'];
        
        // Bulletins générés ce mois
        $query = "SELECT COUNT(*) as total FROM bulletins b
                  JOIN eleves e ON b.eleve_id = e.id
                  JOIN users u ON e.user_id = u.id
                  WHERE u.etablissement_id = :etablissement_id 
                  AND MONTH(b.genere_le) = MONTH(CURRENT_DATE)
                  AND YEAR(b.genere_le) = YEAR(CURRENT_DATE)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':etablissement_id', $etablissementId, PDO::PARAM_INT);
        $stmt->execute();
        $stats['bulletins_mois'] = $stmt->fetch()['total'];
        
        // Imports récents
        $query = "SELECT COUNT(*) as total FROM imports_fichiers 
                  WHERE importe_par IN (SELECT id FROM users WHERE etablissement_id = :etablissement_id)
                  AND DATE(created_at) >= DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':etablissement_id', $etablissementId, PDO::PARAM_INT);
        $stmt->execute();
        $stats['imports_semaine'] = $stmt->fetch()['total'];
        
        return $stats;
    }
    
    public function getClasseStats($classeId, $periodeId) {
        $stats = [];
        
        // Moyenne générale de la classe
        $query = "SELECT AVG(b.moyenne_generale) as moyenne_classe, COUNT(*) as effectif
                  FROM bulletins b
                  JOIN eleves e ON b.eleve_id = e.id
                  WHERE e.classe_id = :classe_id AND b.periode_id = :periode_id
                  AND b.moyenne_generale IS NOT NULL";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':classe_id', $classeId, PDO::PARAM_INT);
        $stmt->bindParam(':periode_id', $periodeId, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch();
        $stats['moyenne_classe'] = round($result['moyenne_classe'], 2);
        $stats['effectif'] = $result['effectif'];
        
        // Répartition des notes
        $query = "SELECT 
                    SUM(CASE WHEN b.moyenne_generale >= 16 THEN 1 ELSE 0 END) as excellent,
                    SUM(CASE WHEN b.moyenne_generale >= 14 AND b.moyenne_generale < 16 THEN 1 ELSE 0 END) as bien,
                    SUM(CASE WHEN b.moyenne_generale >= 12 AND b.moyenne_generale < 14 THEN 1 ELSE 0 END) as assez_bien,
                    SUM(CASE WHEN b.moyenne_generale >= 10 AND b.moyenne_generale < 12 THEN 1 ELSE 0 END) as passable,
                    SUM(CASE WHEN b.moyenne_generale < 10 THEN 1 ELSE 0 END) as insuffisant
                  FROM bulletins b
                  JOIN eleves e ON b.eleve_id = e.id
                  WHERE e.classe_id = :classe_id AND b.periode_id = :periode_id
                  AND b.moyenne_generale IS NOT NULL";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':classe_id', $classeId, PDO::PARAM_INT);
        $stmt->bindParam(':periode_id', $periodeId, PDO::PARAM_INT);
        $stmt->execute();
        
        $stats['repartition'] = $stmt->fetch();
        
        return $stats;
    }
    
    public function getMatiereStats($matiereId, $classeId, $periodeId) {
        $query = "SELECT 
                    AVG(mm.moyenne) as moyenne_matiere,
                    MAX(mm.moyenne) as note_max,
                    MIN(mm.moyenne) as note_min,
                    COUNT(*) as nb_notes
                  FROM moyennes_matieres mm
                  JOIN eleves e ON mm.eleve_id = e.id
                  WHERE mm.matiere_id = :matiere_id 
                  AND e.classe_id = :classe_id 
                  AND mm.periode_id = :periode_id
                  AND mm.moyenne IS NOT NULL";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':matiere_id', $matiereId, PDO::PARAM_INT);
        $stmt->bindParam(':classe_id', $classeId, PDO::PARAM_INT);
        $stmt->bindParam(':periode_id', $periodeId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch();
    }
}

// ===========================
// controllers/StatsController.php
class StatsController extends ApiController {
    private $statsService;
    private $authMiddleware;
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
        $this->statsService = new StatisticsService($this->db);
        $this->authMiddleware = new AuthMiddleware($this->db);
    }
    
    public function getDashboardStats() {
        $user = $this->authMiddleware->requireAuth();
        $stats = $this->statsService->getDashboardStats($user['etablissement_id']);
        
        $this->sendResponse($stats);
    }
    
    public function getClasseStats($classeId, $periodeId) {
        $this->authMiddleware->requireRole(['admin', 'professeur']);
        
        if (!$this->authMiddleware->canAccessClass($classeId)) {
            $this->sendResponse(null, 403, 'Accès non autorisé à cette classe');
        }
        
        $stats = $this->statsService->getClasseStats($classeId, $periodeId);
        $this->sendResponse($stats);
    }
    
    public function getMatiereStats($matiereId, $classeId, $periodeId) {
        $this->authMiddleware->requireRole(['admin', 'professeur']);
        
        $stats = $this->statsService->getMatiereStats($matiereId, $classeId, $periodeId);
        $this->sendResponse($stats);
    }
}

// ===========================
// Enhanced BulletinController with notifications
class EnhancedBulletinController extends BulletinController {
    private $notificationService;
    
    public function __construct() {
        parent::__construct();
        $this->notificationService = new NotificationService($this->db);
    }
    
    public function generate($eleveId, $periodeId) {
        try {
            $result = $this->bulletinService->generateBulletin($eleveId, $periodeId);
            
            // Envoyer notification
            $this->notificationService->sendBulletinNotification(
                $eleveId, 
                $periodeId, 
                $result['pdf_path']
            );
            
            $this->sendResponse($result, 200, 'Bulletin généré et notification envoyée');
        } catch (Exception $e) {
            $this->sendResponse(null, 500, 'Erreur: ' . $e->getMessage());
        }
    }
}
?>