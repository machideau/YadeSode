<?php
class Evaluation extends BaseModel {
    protected $table = 'evaluations';
    
    public function create($data) {
        $query = "INSERT INTO " . $this->table . " 
                  (titre, date_evaluation, note_sur, classe_matiere_professeur_id, 
                   periode_id, type_evaluation_id, description) 
                  VALUES (:titre, :date_evaluation, :note_sur, :classe_matiere_professeur_id, 
                          :periode_id, :type_evaluation_id, :description)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':titre', $data['titre']);
        $stmt->bindParam(':date_evaluation', $data['date_evaluation']);
        $stmt->bindParam(':note_sur', $data['note_sur'] ?? 20);
        $stmt->bindParam(':classe_matiere_professeur_id', $data['classe_matiere_professeur_id'], PDO::PARAM_INT);
        $stmt->bindParam(':periode_id', $data['periode_id'], PDO::PARAM_INT);
        $stmt->bindParam(':type_evaluation_id', $data['type_evaluation_id'], PDO::PARAM_INT);
        $stmt->bindParam(':description', $data['description']);
        
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }
    
    public function update($id, $data) {
        $query = "UPDATE " . $this->table . " 
                  SET titre = :titre, date_evaluation = :date_evaluation, note_sur = :note_sur, 
                      type_evaluation_id = :type_evaluation_id, description = :description 
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':titre', $data['titre']);
        $stmt->bindParam(':date_evaluation', $data['date_evaluation']);
        $stmt->bindParam(':note_sur', $data['note_sur']);
        $stmt->bindParam(':type_evaluation_id', $data['type_evaluation_id'], PDO::PARAM_INT);
        $stmt->bindParam(':description', $data['description']);
        
        return $stmt->execute();
    }
    
    public function getByProfesseurAndPeriode($professeurId, $periodeId) {
        $query = "SELECT e.*, m.nom as matiere_nom, c.nom as classe_nom, te.nom as type_nom
                FROM " . $this->table . " e
                JOIN classe_matiere_professeur cmp ON e.classe_matiere_professeur_id = cmp.id
                JOIN matieres m ON cmp.matiere_id = m.id
                JOIN classes c ON cmp.classe_id = c.id
                JOIN types_evaluations te ON e.type_evaluation_id = te.id
                WHERE cmp.professeur_id = :professeur_id AND e.periode_id = :periode_id
                ORDER BY e.date_evaluation DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':professeur_id', $professeurId, PDO::PARAM_INT);
        $stmt->bindParam(':periode_id', $periodeId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    public function getWithNotes($evaluationId) {
        $query = "SELECT e.*, m.nom as matiere_nom, c.nom as classe_nom, te.nom as type_nom,
                        n.id as note_id, n.note, n.statut, n.commentaire,
                        u.nom as eleve_nom, u.prenoms as eleve_prenoms, u.matricule
                FROM " . $this->table . " e
                JOIN classe_matiere_professeur cmp ON e.classe_matiere_professeur_id = cmp.id
                JOIN matieres m ON cmp.matiere_id = m.id
                JOIN classes c ON cmp.classe_id = c.id
                JOIN types_evaluations te ON e.type_evaluation_id = te.id
                LEFT JOIN notes n ON e.id = n.evaluation_id
                LEFT JOIN eleves el ON n.eleve_id = el.id
                LEFT JOIN users u ON el.user_id = u.id
                WHERE e.id = :evaluation_id
                ORDER BY u.nom, u.prenoms";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':evaluation_id', $evaluationId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
}
?>