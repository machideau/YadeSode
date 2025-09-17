<?php
class EleveController extends ApiController {
    private $eleveModel;
    private $userModel;
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
        $this->eleveModel = new Eleve($this->db);
        $this->userModel = new User($this->db);
    }
    
    public function getAll() {
        $eleves = $this->eleveModel->findAll();
        $this->sendResponse($eleves);
    }
    
    public function getById($id) {
        $eleve = $this->eleveModel->findById($id);
        if (!$eleve) {
            $this->sendResponse(null, 404, 'Élève non trouvé');
        }
        $this->sendResponse($eleve);
    }
    
    public function create() {
        $data = $this->getRequestData();
        $this->validateRequired($data, ['nom', 'prenoms', 'classe_id', 'etablissement_id']);
        
        // Démarrer une transaction
        $this->db->beginTransaction();
        
        try {
            // Créer l'utilisateur d'abord
            $userData = [
                'nom' => $data['nom'],
                'prenoms' => $data['prenoms'],
                'email' => $data['email'] ?? null,
                'telephone' => $data['telephone'] ?? null,
                'date_naissance' => $data['date_naissance'] ?? null,
                'sexe' => $data['sexe'],
                'adresse' => $data['adresse'] ?? null,
                'type_user' => 'eleve',
                'matricule' => $this->userModel->generateMatricule('eleve', $data['etablissement_id']),
                'mot_de_passe' => 'eleve123', // Mot de passe par défaut
                'etablissement_id' => $data['etablissement_id']
            ];
            
            $userId = $this->userModel->create($userData);
            
            if (!$userId) {
                throw new Exception('Erreur lors de la création de l\'utilisateur');
            }
            
            // Créer l'élève
            $eleveData = [
                'user_id' => $userId,
                'classe_id' => $data['classe_id'],
                'date_inscription' => $data['date_inscription'] ?? date('Y-m-d'),
                'nom_pere' => $data['nom_pere'] ?? null,
                'nom_mere' => $data['nom_mere'] ?? null,
                'telephone_tuteur' => $data['telephone_tuteur'] ?? null
            ];
            
            $eleveId = $this->eleveModel->create($eleveData);
            
            if (!$eleveId) {
                throw new Exception('Erreur lors de la création de l\'élève');
            }
            
            $this->db->commit();
            
            $eleve = $this->eleveModel->findById($eleveId);
            $this->sendResponse($eleve, 201, 'Élève créé avec succès');
            
        } catch (Exception $e) {
            $this->db->rollback();
            $this->sendResponse(null, 500, 'Erreur: ' . $e->getMessage());
        }
    }
    
    public function update($id) {
        $data = $this->getRequestData();
        
        if ($this->eleveModel->update($id, $data)) {
            $eleve = $this->eleveModel->findById($id);
            $this->sendResponse($eleve, 200, 'Élève mis à jour avec succès');
        } else {
            $this->sendResponse(null, 500, 'Erreur lors de la mise à jour');
        }
    }
    
    public function delete($id) {
        if ($this->eleveModel->delete($id)) {
            $this->sendResponse(null, 200, 'Élève supprimé avec succès');
        } else {
            $this->sendResponse(null, 500, 'Erreur lors de la suppression');
        }
    }
    
    public function getByClasse($classeId) {
        $eleves = $this->eleveModel->getByClasse($classeId);
        $this->sendResponse($eleves);
    }
}

?>