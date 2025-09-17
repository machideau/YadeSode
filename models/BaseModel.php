<?php
abstract class BaseModel {
    protected $conn;
    protected $table;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Méthode générique pour récupérer tous les enregistrements
    public function findAll($limit = null) {
        $query = "SELECT * FROM " . $this->table;
        if ($limit) {
            $query .= " LIMIT " . $limit;
        }
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    // Méthode générique pour récupérer un enregistrement par ID
    public function findById($id) {
        $query = "SELECT * FROM " . $this->table . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch();
    }
    
    // Méthode générique pour supprimer
    public function delete($id) {
        $query = "DELETE FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
?>