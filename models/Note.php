<?php
class Note extends BaseModel {
    protected $table = 'notes';
    
    public function create($data) {
        $query = "INSERT INTO " . $this->table . " 
                  (note, statut, commentaire, eleve_id, evaluation_id, saisie_par) 
                  VALUES (:note, :statut, :commentaire, :eleve_id, :evaluation_id, :saisie_par)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':note', $data['note']);
        $stmt->bindParam(':statut', $data['statut'] ?? 'present');
        $stmt->bindParam(':commentaire', $data['commentaire']);
        $stmt->bindParam(':eleve_id', $data['eleve_id'], PDO::PARAM_INT);
        $stmt->bindParam(':evaluation_id', $data['evaluation_id'], PDO::PARAM_INT);
        $stmt->bindParam(':saisie_par', $data['saisie_par'], PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }
    
    public function update($id, $data) {
        $query = "UPDATE " . $this->table . " 
                  SET note = :note, statut = :statut, commentaire = :commentaire 
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':note', $data['note']);
        $stmt->bindParam(':statut', $data['statut']);
        $stmt->bindParam(':commentaire', $data['commentaire']);
        
        return $stmt->execute();
    }
    
    public function batchUpdate($notes) {
        $this->conn->beginTransaction();
        
        try {
            foreach ($notes as $noteData) {
                if (isset($noteData['id']) && $noteData['id']) {
                    // Mise à jour
                    $this->update($noteData['id'], $noteData);
                } else {
                    // Création
                    $this->create($noteData);
                }
            }
            
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
    
    public function getByEleveAndPeriode($eleveId, $periodeId) {
        $query = "SELECT n.*, e.titre as evaluation_titre, m.nom as matiere_nom, 
                         te.nom as type_evaluation, e.note_sur, e.date_evaluation
                  FROM " . $this->table . " n
                  JOIN evaluations e ON n.evaluation_id = e.id
                  JOIN classe_matiere_professeur cmp ON e.classe_matiere_professeur_id = cmp.id
                  JOIN matieres m ON cmp.matiere_id = m.id
                  JOIN types_evaluations te ON e.type_evaluation_id = te.id
                  WHERE n.eleve_id = :eleve_id AND e.periode_id = :periode_id
                  ORDER BY m.nom, e.date_evaluation";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':eleve_id', $eleveId, PDO::PARAM_INT);
        $stmt->bindParam(':periode_id', $periodeId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    public function calculateMoyenneMatiere($eleveId, $matiereId, $periodeId) {
        $query = "SELECT n.note, te.coefficient as coeff_type, e.note_sur
                  FROM " . $this->table . " n
                  JOIN evaluations e ON n.evaluation_id = e.id
                  JOIN classe_matiere_professeur cmp ON e.classe_matiere_professeur_id = cmp.id
                  JOIN types_evaluations te ON e.type_evaluation_id = te.id
                  WHERE n.eleve_id = :eleve_id 
                    AND cmp.matiere_id = :matiere_id 
                    AND e.periode_id = :periode_id 
                    AND n.statut = 'present' 
                    AND n.note IS NOT NULL";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':eleve_id', $eleveId, PDO::PARAM_INT);
        $stmt->bindParam(':matiere_id', $matiereId, PDO::PARAM_INT);
        $stmt->bindParam(':periode_id', $periodeId, PDO::PARAM_INT);
        $stmt->execute();
        
        $notes = $stmt->fetchAll();
        
        if (empty($notes)) {
            return null;
        }
        
        $totalPoints = 0;
        $totalCoefficients = 0;
        
        foreach ($notes as $note) {
            // Convertir la note sur 20
            $noteSur20 = ($note['note'] * 20) / $note['note_sur'];
            $coefficient = $note['coeff_type'];
            
            $totalPoints += $noteSur20 * $coefficient;
            $totalCoefficients += $coefficient;
        }
        
        return $totalCoefficients > 0 ? round($totalPoints / $totalCoefficients, 2) : null;
    }
}
?>