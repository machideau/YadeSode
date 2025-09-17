<?php
class Etablissement extends BaseModel {
    protected $table = 'etablissements';
    
    public function create($data) {
        $query = "INSERT INTO " . $this->table . " 
                (nom, adresse, telephone, email, directeur) 
                VALUES (:nom, :adresse, :telephone, :email, :directeur)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':nom', $data['nom']);
        $stmt->bindParam(':adresse', $data['adresse']);
        $stmt->bindParam(':telephone', $data['telephone']);
        $stmt->bindParam(':email', $data['email']);
        $stmt->bindParam(':directeur', $data['directeur']);
        
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }
    
    public function update($id, $data) {
        $query = "UPDATE " . $this->table . " 
                SET nom = :nom, adresse = :adresse, telephone = :telephone, 
                email = :email, directeur = :directeur 
                WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':nom', $data['nom']);
        $stmt->bindParam(':adresse', $data['adresse']);
        $stmt->bindParam(':telephone', $data['telephone']);
        $stmt->bindParam(':email', $data['email']);
        $stmt->bindParam(':directeur', $data['directeur']);
        
        return $stmt->execute();
    }
}
?>