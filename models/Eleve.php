<?php
class Eleve extends BaseModel {
    protected $table = 'eleves';
    
    public function create($data) {
        $query = "INSERT INTO " . $this->table . " 
                  (user_id, classe_id, date_inscription, nom_pere, nom_mere, telephone_tuteur) 
                  VALUES (:user_id, :classe_id, :date_inscription, :nom_pere, :nom_mere, :telephone_tuteur)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $data['user_id'], PDO::PARAM_INT);
        $stmt->bindParam(':classe_id', $data['classe_id'], PDO::PARAM_INT);
        $stmt->bindParam(':date_inscription', $data['date_inscription']);
        $stmt->bindParam(':nom_pere', $data['nom_pere']);
        $stmt->bindParam(':nom_mere', $data['nom_mere']);
        $stmt->bindParam(':telephone_tuteur', $data['telephone_tuteur']);
        
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }
    
    public function update($id, $data) {
        $query = "UPDATE " . $this->table . " 
                  SET classe_id = :classe_id, nom_pere = :nom_pere, nom_mere = :nom_mere, 
                      telephone_tuteur = :telephone_tuteur, statut = :statut 
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':classe_id', $data['classe_id'], PDO::PARAM_INT);
        $stmt->bindParam(':nom_pere', $data['nom_pere']);
        $stmt->bindParam(':nom_mere', $data['nom_mere']);
        $stmt->bindParam(':telephone_tuteur', $data['telephone_tuteur']);
        $stmt->bindParam(':statut', $data['statut']);
        
        return $stmt->execute();
    }
    
    public function getByClasse($classe_id) {
        $query = "SELECT e.*, u.nom, u.prenoms, u.matricule, u.date_naissance 
                  FROM " . $this->table . " e
                  JOIN users u ON e.user_id = u.id
                  WHERE e.classe_id = :classe_id AND e.statut = 'inscrit'
                  ORDER BY u.nom, u.prenoms";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':classe_id', $classe_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
?>