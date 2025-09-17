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
?>