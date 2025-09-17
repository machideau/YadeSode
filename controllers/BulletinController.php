<?php
class BulletinController extends ApiController {
    private $bulletinService;
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
        $this->bulletinService = new BulletinService($this->db);
    }
    
    public function generate($eleveId, $periodeId) {
        try {
            $result = $this->bulletinService->generateBulletin($eleveId, $periodeId);
            $this->sendResponse($result, 200, 'Bulletin généré avec succès');
        } catch (Exception $e) {
            $this->sendResponse(null, 500, 'Erreur: ' . $e->getMessage());
        }
    }
    
    public function generateForClasse($classeId, $periodeId) {
        try {
            // Récupérer tous les élèves de la classe
            $query = "SELECT id FROM eleves WHERE classe_id = :classe_id AND statut = 'inscrit'";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':classe_id', $classeId, PDO::PARAM_INT);
            $stmt->execute();
            
            $eleves = $stmt->fetchAll();
            $results = [];
            
            foreach ($eleves as $eleve) {
                $results[] = $this->bulletinService->generateBulletin($eleve['id'], $periodeId);
            }
            
            $this->sendResponse($results, 200, count($results) . ' bulletins générés avec succès');
            
        } catch (Exception $e) {
            $this->sendResponse(null, 500, 'Erreur: ' . $e->getMessage());
        }
    }
    
    public function download($bulletinId) {
        $query = "SELECT fichier_pdf, eleve_id FROM bulletins WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $bulletinId, PDO::PARAM_INT);
        $stmt->execute();
        
        $bulletin = $stmt->fetch();
        
        if (!$bulletin || !file_exists($bulletin['fichier_pdf'])) {
            $this->sendResponse(null, 404, 'Bulletin non trouvé');
        }
        
        // Télécharger le fichier PDF
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($bulletin['fichier_pdf']) . '"');
        header('Content-Length: ' . filesize($bulletin['fichier_pdf']));
        
        readfile($bulletin['fichier_pdf']);
        exit;
    }
}
?>