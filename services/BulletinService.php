<?php
class BulletinService {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function generateBulletin($eleveId, $periodeId) {
        // Récupérer les informations de l'élève
        $eleve = $this->getEleveInfo($eleveId);
        
        // Récupérer toutes les matières et notes
        $matieres = $this->getMoyennesMatieresEleve($eleveId, $periodeId);
        
        // Calculer la moyenne générale
        $moyenneGenerale = $this->calculateMoyenneGenerale($matieres);
        
        // Calculer le rang
        $rang = $this->calculateRang($eleveId, $periodeId, $moyenneGenerale);
        
        // Générer le PDF
        $pdfPath = $this->generatePDF($eleve, $matieres, $moyenneGenerale, $rang, $periodeId);
        
        // Sauvegarder en base
        $bulletinId = $this->saveBulletin($eleveId, $periodeId, $moyenneGenerale, $rang, $pdfPath);
        
        return [
            'bulletin_id' => $bulletinId,
            'pdf_path' => $pdfPath,
            'moyenne_generale' => $moyenneGenerale,
            'rang' => $rang
        ];
    }
    
    private function getEleveInfo($eleveId) {
        $query = "SELECT e.*, u.nom, u.prenoms, u.matricule, u.date_naissance,
                         c.nom as classe_nom, et.nom as etablissement_nom
                  FROM eleves e
                  JOIN users u ON e.user_id = u.id
                  JOIN classes c ON e.classe_id = c.id
                  JOIN etablissements et ON c.etablissement_id = et.id
                  WHERE e.id = :eleve_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':eleve_id', $eleveId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch();
    }
    
    private function getMoyennesMatieresEleve($eleveId, $periodeId) {
        $query = "SELECT DISTINCT m.id, m.nom as matiere_nom, m.coefficient
                  FROM notes n
                  JOIN evaluations e ON n.evaluation_id = e.id
                  JOIN classe_matiere_professeur cmp ON e.classe_matiere_professeur_id = cmp.id
                  JOIN matieres m ON cmp.matiere_id = m.id
                  WHERE n.eleve_id = :eleve_id AND e.periode_id = :periode_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':eleve_id', $eleveId, PDO::PARAM_INT);
        $stmt->bindParam(':periode_id', $periodeId, PDO::PARAM_INT);
        $stmt->execute();
        
        $matieres = $stmt->fetchAll();
        $noteModel = new Note($this->db);
        
        foreach ($matieres as &$matiere) {
            $matiere['moyenne'] = $noteModel->calculateMoyenneMatiere($eleveId, $matiere['id'], $periodeId);
        }
        
        return $matieres;
    }
    
    private function calculateMoyenneGenerale($matieres) {
        $totalPoints = 0;
        $totalCoefficients = 0;
        
        foreach ($matieres as $matiere) {
            if ($matiere['moyenne'] !== null) {
                $totalPoints += $matiere['moyenne'] * $matiere['coefficient'];
                $totalCoefficients += $matiere['coefficient'];
            }
        }
        
        return $totalCoefficients > 0 ? round($totalPoints / $totalCoefficients, 2) : null;
    }
    
    private function calculateRang($eleveId, $periodeId, $moyenneEleve) {
        if ($moyenneEleve === null) return null;
        
        // Récupérer la classe de l'élève
        $query = "SELECT classe_id FROM eleves WHERE id = :eleve_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':eleve_id', $eleveId, PDO::PARAM_INT);
        $stmt->execute();
        $classe = $stmt->fetch();
        
        if (!$classe) return null;
        
        // Calculer les moyennes de tous les élèves de la classe
        $query = "SELECT e.id
                  FROM eleves e
                  WHERE e.classe_id = :classe_id AND e.statut = 'inscrit'";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':classe_id', $classe['classe_id'], PDO::PARAM_INT);
        $stmt->execute();
        
        $elevesClasse = $stmt->fetchAll();
        $moyennes = [];
        
        foreach ($elevesClasse as $eleve) {
            $matieres = $this->getMoyennesMatieresEleve($eleve['id'], $periodeId);
            $moyenne = $this->calculateMoyenneGenerale($matieres);
            if ($moyenne !== null) {
                $moyennes[] = $moyenne;
            }
        }
        
        // Trier par ordre décroissant et trouver le rang
        rsort($moyennes);
        $rang = array_search($moyenneEleve, $moyennes);
        
        return $rang !== false ? $rang + 1 : null;
    }
    
    private function generatePDF($eleve, $matieres, $moyenneGenerale, $rang, $periodeId) {
        require_once 'vendor/autoload.php'; // TCPDF
        
        $pdf = new TCPDF();
        $pdf->AddPage();
        $pdf->SetFont('helvetica', 'B', 16);
        
        // En-tête
        $pdf->Cell(0, 15, $eleve['etablissement_nom'], 0, 1, 'C');
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, 'BULLETIN DE NOTES', 0, 1, 'C');
        
        // Informations élève
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Ln(10);
        $pdf->Cell(50, 8, 'Nom : ' . $eleve['nom'], 0, 0);
        $pdf->Cell(50, 8, 'Prénoms : ' . $eleve['prenoms'], 0, 1);
        $pdf->Cell(50, 8, 'Matricule : ' . $eleve['matricule'], 0, 0);
        $pdf->Cell(50, 8, 'Classe : ' . $eleve['classe_nom'], 0, 1);
        
        // Tableau des notes
        $pdf->Ln(10);
        $pdf->SetFont('helvetica', 'B', 10);
        
        // En-têtes du tableau
        $pdf->Cell(60, 8, 'MATIERES', 1, 0, 'C');
        $pdf->Cell(30, 8, 'MOYENNE', 1, 0, 'C');
        $pdf->Cell(20, 8, 'COEFF', 1, 0, 'C');
        $pdf->Cell(30, 8, 'TOTAL', 1, 1, 'C');
        
        $pdf->SetFont('helvetica', '', 10);
        
        foreach ($matieres as $matiere) {
            $moyenne = $matiere['moyenne'] ?? '-';
            $total = ($matiere['moyenne'] ?? 0) * $matiere['coefficient'];
            
            $pdf->Cell(60, 6, $matiere['matiere_nom'], 1, 0);
            $pdf->Cell(30, 6, $moyenne, 1, 0, 'C');
            $pdf->Cell(20, 6, $matiere['coefficient'], 1, 0, 'C');
            $pdf->Cell(30, 6, $moyenne !== '-' ? number_format($total, 2) : '-', 1, 1, 'C');
        }
        
        // Moyenne générale et rang
        $pdf->Ln(5);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 10, 'Moyenne Générale : ' . ($moyenneGenerale ?? 'N/A'), 0, 1);
        if ($rang) {
            $pdf->Cell(0, 10, 'Rang : ' . $rang, 0, 1);
        }
        
        // Sauvegarder le PDF
        $filename = 'bulletin_' . $eleve['matricule'] . '_' . $periodeId . '_' . date('Y-m-d') . '.pdf';
        $filepath = __DIR__ . '/../bulletins/' . $filename;
        
        // Créer le dossier s'il n'existe pas
        $dir = dirname($filepath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        
        $pdf->Output($filepath, 'F');
        
        return $filepath;
    }
    
    private function saveBulletin($eleveId, $periodeId, $moyenneGenerale, $rang, $pdfPath) {
        $query = "INSERT INTO bulletins 
                  (eleve_id, periode_id, moyenne_generale, rang_classe, fichier_pdf, genere_par) 
                  VALUES (:eleve_id, :periode_id, :moyenne_generale, :rang_classe, :fichier_pdf, :genere_par)
                  ON DUPLICATE KEY UPDATE 
                  moyenne_generale = :moyenne_generale2, rang_classe = :rang_classe2, 
                  fichier_pdf = :fichier_pdf2, genere_le = CURRENT_TIMESTAMP";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':eleve_id', $eleveId, PDO::PARAM_INT);
        $stmt->bindParam(':periode_id', $periodeId, PDO::PARAM_INT);
        $stmt->bindParam(':moyenne_generale', $moyenneGenerale);
        $stmt->bindParam(':moyenne_generale2', $moyenneGenerale);
        $stmt->bindParam(':rang_classe', $rang, PDO::PARAM_INT);
        $stmt->bindParam(':rang_classe2', $rang, PDO::PARAM_INT);
        $stmt->bindParam(':fichier_pdf', $pdfPath);
        $stmt->bindParam(':fichier_pdf2', $pdfPath);
        $stmt->bindParam(':genere_par', $_SESSION['user_id'] ?? 1, PDO::PARAM_INT);
        
        $stmt->execute();
        
        return $this->db->lastInsertId() ?: $this->getBulletinId($eleveId, $periodeId);
    }
    
    private function getBulletinId($eleveId, $periodeId) {
        $query = "SELECT id FROM bulletins WHERE eleve_id = :eleve_id AND periode_id = :periode_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':eleve_id', $eleveId, PDO::PARAM_INT);
        $stmt->bindParam(':periode_id', $periodeId, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch();
        return $result ? $result['id'] : null;
    }
}
?>