<?php
class Classe extends BaseModel {
    protected $table = 'classes';
    
    public function create($data) {
        $query = "INSERT INTO " . $this->table . " 
                (nom, niveau, section, effectif_max, etablissement_id, annee_scolaire_id) 
                VALUES (:nom, :niveau, :section, :effectif_max, :etablissement_id, :annee_scolaire_id)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':nom', $data['nom']);
        $stmt->bindParam(':niveau', $data['niveau']);
        $stmt->bindParam(':section', $data['section']);
        $stmt->bindParam(':effectif_max', $data['effectif_max'], PDO::PARAM_INT);
        $stmt->bindParam(':etablissement_id', $data['etablissement_id'], PDO::PARAM_INT);
        $stmt->bindParam(':annee_scolaire_id', $data['annee_scolaire_id'], PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }
    
    public function update($id, $data) {
        $query = "UPDATE " . $this->table . " 
                SET nom = :nom, niveau = :niveau, section = :section, 
                effectif_max = :effectif_max, professeur_principal_id = :professeur_principal_id 
                WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':nom', $data['nom']);
        $stmt->bindParam(':niveau', $data['niveau']);
        $stmt->bindParam(':section', $data['section']);
        $stmt->bindParam(':effectif_max', $data['effectif_max'], PDO::PARAM_INT);
        $stmt->bindParam(':professeur_principal_id', $data['professeur_principal_id'] ?? null, PDO::PARAM_INT);
        
        return $stmt->execute();
    }
    
    public function getWithEtablissement() {
        $query = "SELECT c.*, e.nom as etablissement_nom, u.nom as prof_principal_nom 
                FROM " . $this->table . " c
                LEFT JOIN etablissements e ON c.etablissement_id = e.id
                LEFT JOIN users u ON c.professeur_principal_id = u.id
                ORDER BY c.nom";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function getEleves($classe_id) {
        $query = "SELECT e.*, u.nom, u.prenoms, u.email 
                FROM eleves e
                JOIN users u ON e.user_id = u.id
                WHERE e.classe_id = :classe_id
                ORDER BY u.nom, u.prenoms";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':classe_id', $classe_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
?>