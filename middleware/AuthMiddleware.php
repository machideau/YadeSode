<?php
class AuthMiddleware {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    public function requireAuth() {
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Authentification requise']);
            exit;
        }
        
        return $this->getCurrentUser();
    }
    
    public function requireRole($allowedRoles) {
        $user = $this->requireAuth();
        
        if (!in_array($user['type_user'], $allowedRoles)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Accès non autorisé']);
            exit;
        }
        
        return $user;
    }
    
    public function canAccessClass($classeId) {
        $user = $this->requireAuth();
        
        // Admin peut tout voir
        if ($user['type_user'] === 'admin') {
            return true;
        }
        
        // Professeur peut voir ses classes
        if ($user['type_user'] === 'professeur') {
            $query = "SELECT COUNT(*) as count FROM classe_matiere_professeur cmp 
                     WHERE cmp.professeur_id = :user_id AND cmp.classe_id = :classe_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user['id'], PDO::PARAM_INT);
            $stmt->bindParam(':classe_id', $classeId, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetch();
            return $result['count'] > 0;
        }
        
        // Élève peut voir sa classe
        if ($user['type_user'] === 'eleve') {
            $query = "SELECT COUNT(*) as count FROM eleves e 
                     WHERE e.user_id = :user_id AND e.classe_id = :classe_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user['id'], PDO::PARAM_INT);
            $stmt->bindParam(':classe_id', $classeId, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetch();
            return $result['count'] > 0;
        }
        
        return false;
    }
    
    private function getCurrentUser() {
        $query = "SELECT * FROM users WHERE id = :id AND actif = 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->execute();
        
        $user = $stmt->fetch();
        if (!$user) {
            session_destroy();
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Session invalide']);
            exit;
        }
        
        unset($user['mot_de_passe']);
        return $user;
    }
}

// ===========================
// validators/DataValidator.php
class DataValidator {
    
    public static function validateEleve($data) {
        $errors = [];
        
        // Nom obligatoire
        if (empty(trim($data['nom']))) {
            $errors['nom'] = 'Le nom est obligatoire';
        } elseif (strlen(trim($data['nom'])) < 2) {
            $errors['nom'] = 'Le nom doit contenir au moins 2 caractères';
        }
        
        // Prénoms obligatoires
        if (empty(trim($data['prenoms']))) {
            $errors['prenoms'] = 'Les prénoms sont obligatoires';
        }
        
        // Email valide si fourni
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Adresse email invalide';
        }
        
        // Date de naissance
        if (!empty($data['date_naissance'])) {
            $date = DateTime::createFromFormat('Y-m-d', $data['date_naissance']);
            if (!$date || $date->format('Y-m-d') !== $data['date_naissance']) {
                $errors['date_naissance'] = 'Date de naissance invalide';
            } elseif ($date > new DateTime()) {
                $errors['date_naissance'] = 'La date de naissance ne peut pas être future';
            }
        }
        
        // Sexe obligatoire
        if (!in_array($data['sexe'], ['M', 'F'])) {
            $errors['sexe'] = 'Le sexe doit être M ou F';
        }
        
        // Classe obligatoire
        if (empty($data['classe_id']) || !is_numeric($data['classe_id'])) {
            $errors['classe_id'] = 'Classe obligatoire';
        }
        
        return $errors;
    }
    
    public static function validateNote($data) {
        $errors = [];
        
        // Note valide si fournie
        if ($data['statut'] === 'present' && isset($data['note'])) {
            $note = floatval($data['note']);
            if ($note < 0 || $note > 20) {
                $errors['note'] = 'La note doit être entre 0 et 20';
            }
        }
        
        // Statut valide
        if (!in_array($data['statut'], ['present', 'absent', 'dispense'])) {
            $errors['statut'] = 'Statut invalide';
        }
        
        // Élève obligatoire
        if (empty($data['eleve_id']) || !is_numeric($data['eleve_id'])) {
            $errors['eleve_id'] = 'Élève obligatoire';
        }
        
        // Évaluation obligatoire
        if (empty($data['evaluation_id']) || !is_numeric($data['evaluation_id'])) {
            $errors['evaluation_id'] = 'Évaluation obligatoire';
        }
        
        return $errors;
    }
    
    public static function validateClasse($data) {
        $errors = [];
        
        // Nom obligatoire et unique
        if (empty(trim($data['nom']))) {
            $errors['nom'] = 'Le nom de la classe est obligatoire';
        }
        
        // Effectif maximum valide
        if (isset($data['effectif_max'])) {
            $effectif = intval($data['effectif_max']);
            if ($effectif < 1 || $effectif > 50) {
                $errors['effectif_max'] = 'L\'effectif doit être entre 1 et 50';
            }
        }
        
        // Établissement obligatoire
        if (empty($data['etablissement_id']) || !is_numeric($data['etablissement_id'])) {
            $errors['etablissement_id'] = 'Établissement obligatoire';
        }
        
        return $errors;
    }
}

?>