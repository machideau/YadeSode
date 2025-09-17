<?php
class Matiere extends BaseModel {
    protected $table = 'matieres';
    
    public function create($data) {
        $query = "INSERT INTO " . $this->table . " 
                (nom, code, coefficient, couleur, description, etablissement_id) 
                VALUES (:nom, :code, :coefficient, :couleur, :description, :etablissement_id)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':nom', $data['nom']);
        $stmt->bindParam(':code', strtoupper($data['code']));
        $stmt->bindParam(':coefficient', $data['coefficient']);
        $stmt->bindParam(':couleur', $data['couleur']);
        $stmt->bindParam(':description', $data['description']);
        $stmt->bindParam(':etablissement_id', $data['etablissement_id'], PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }
    
    public function update($id, $data) {
        $query = "UPDATE " . $this->table . " 
                SET nom = :nom, code = :code, coefficient = :coefficient, 
                couleur = :couleur, description = :description 
                WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':nom', $data['nom']);
        $stmt->bindParam(':code', strtoupper($data['code']));
        $stmt->bindParam(':coefficient', $data['coefficient']);
        $stmt->bindParam(':couleur', $data['couleur']);
        $stmt->bindParam(':description', $data['description']);
        
        return $stmt->execute();
    }
}
?>