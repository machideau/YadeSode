<?php
class User extends BaseModel {
    protected $table = 'users';
    
    public function create($data) {
        $query = "INSERT INTO " . $this->table . " 
                  (nom, prenoms, email, telephone, date_naissance, sexe, adresse, 
                   type_user, matricule, mot_de_passe, etablissement_id) 
                  VALUES (:nom, :prenoms, :email, :telephone, :date_naissance, :sexe, 
                          :adresse, :type_user, :matricule, :mot_de_passe, :etablissement_id)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':nom', $data['nom']);
        $stmt->bindParam(':prenoms', $data['prenoms']);
        $stmt->bindParam(':email', $data['email']);
        $stmt->bindParam(':telephone', $data['telephone']);
        $stmt->bindParam(':date_naissance', $data['date_naissance']);
        $stmt->bindParam(':sexe', $data['sexe']);
        $stmt->bindParam(':adresse', $data['adresse']);
        $stmt->bindParam(':type_user', $data['type_user']);
        $stmt->bindParam(':matricule', $data['matricule']);
        $stmt->bindParam(':mot_de_passe', password_hash($data['mot_de_passe'], PASSWORD_DEFAULT));
        $stmt->bindParam(':etablissement_id', $data['etablissement_id'], PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }
    
    public function update($id, $data) {
        $query = "UPDATE " . $this->table . " 
                  SET nom = :nom, prenoms = :prenoms, email = :email, telephone = :telephone, 
                      date_naissance = :date_naissance, sexe = :sexe, adresse = :adresse 
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':nom', $data['nom']);
        $stmt->bindParam(':prenoms', $data['prenoms']);
        $stmt->bindParam(':email', $data['email']);
        $stmt->bindParam(':telephone', $data['telephone']);
        $stmt->bindParam(':date_naissance', $data['date_naissance']);
        $stmt->bindParam(':sexe', $data['sexe']);
        $stmt->bindParam(':adresse', $data['adresse']);
        
        return $stmt->execute();
    }
    
    public function login($email, $password) {
        $query = "SELECT * FROM " . $this->table . " WHERE email = :email AND actif = 1 LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['mot_de_passe'])) {
            unset($user['mot_de_passe']); // Ne pas retourner le mot de passe
            return $user;
        }
        return false;
    }
    
    public function getProfesseurs() {
        $query = "SELECT * FROM " . $this->table . " WHERE type_user = 'professeur' AND actif = 1 ORDER BY nom, prenoms";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function generateMatricule($type_user, $etablissement_id) {
        $prefix = ($type_user == 'eleve') ? 'EL' : 'PR';
        $year = date('Y');
        
        $query = "SELECT COUNT(*) as count FROM " . $this->table . " 
                  WHERE type_user = :type_user AND etablissement_id = :etablissement_id 
                  AND YEAR(created_at) = :year";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':type_user', $type_user);
        $stmt->bindParam(':etablissement_id', $etablissement_id, PDO::PARAM_INT);
        $stmt->bindParam(':year', $year, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch();
        $number = str_pad($result['count'] + 1, 4, '0', STR_PAD_LEFT);
        
        return $prefix . $year . $etablissement_id . $number;
    }
}
?>