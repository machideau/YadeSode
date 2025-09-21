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

    public function downloadClasseZip($classeId, $anneeId) {
        // Récupérer tous les bulletins de la classe pour une année scolaire donnée
        $query = "SELECT b.id, b.fichier_pdf, e.id as eleve_id, u.nom, u.prenoms, p.nom as periode_nom
                  FROM bulletins b
                  JOIN eleves e ON b.eleve_id = e.id
                  JOIN users u ON e.user_id = u.id
                  JOIN periodes p ON b.periode_id = p.id
                  WHERE e.classe_id = :classe_id AND p.annee_scolaire_id = :annee_id AND b.fichier_pdf IS NOT NULL";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':classe_id', $classeId, PDO::PARAM_INT);
        $stmt->bindParam(':annee_id', $anneeId, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        if (!$rows || count($rows) === 0) {
            $this->sendResponse(null, 404, 'Aucun bulletin trouvé pour cette classe et année');
        }

        $zip = new ZipArchive();
        $zipFileName = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "bulletins_classe_{$classeId}_annee_{$anneeId}_" . time() . ".zip";
        if ($zip->open($zipFileName, ZipArchive::CREATE) !== TRUE) {
            $this->sendResponse(null, 500, 'Impossible de créer l\'archive ZIP');
        }

        foreach ($rows as $row) {
            $pdfPath = $row['fichier_pdf'];
            if ($pdfPath && file_exists($pdfPath)) {
                $safeNom = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $row['nom'] . '_' . $row['prenoms']);
                $safePeriode = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $row['periode_nom']);
                $entryName = $safeNom . '_' . $safePeriode . '_' . basename($pdfPath);
                $zip->addFile($pdfPath, $entryName);
            }
        }

        $zip->close();

        if (!file_exists($zipFileName)) {
            $this->sendResponse(null, 500, 'Archive ZIP non trouvée');
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="bulletins_classe_' . $classeId . '_annee_' . $anneeId . '.zip"');
        header('Content-Length: ' . filesize($zipFileName));
        readfile($zipFileName);
        @unlink($zipFileName);
        exit;
    }
}
?>