<?php
class MatiereController extends ApiController {
    private $matiereModel;
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
        $this->matiereModel = new Matiere($this->db);
    }
    
    public function getAll() {
        $matieres = $this->matiereModel->findAll();
        $this->sendResponse($matieres);
    }
    
    public function getById($id) {
        $matiere = $this->matiereModel->findById($id);
        if (!$matiere) {
            $this->sendResponse(null, 404, 'Matière non trouvée');
        }
        $this->sendResponse($matiere);
    }
    
    public function create() {
        $data = $this->getRequestData();
        $this->validateRequired($data, ['nom', 'code', 'etablissement_id']);
        
        // Vérifier l'unicité du code
        if ($this->codeExists($data['code'], $data['etablissement_id'])) {
            $this->sendResponse(null, 400, 'Ce code de matière existe déjà');
        }
        
        $id = $this->matiereModel->create($data);
        if ($id) {
            $matiere = $this->matiereModel->findById($id);
            $this->sendResponse($matiere, 201, 'Matière créée avec succès');
        } else {
            $this->sendResponse(null, 500, 'Erreur lors de la création');
        }
    }
    
    public function update($id) {
        $data = $this->getRequestData();
        $this->validateRequired($data, ['nom', 'code']);
        
        if ($this->matiereModel->update($id, $data)) {
            $matiere = $this->matiereModel->findById($id);
            $this->sendResponse($matiere, 200, 'Matière mise à jour avec succès');
        } else {
            $this->sendResponse(null, 500, 'Erreur lors de la mise à jour');
        }
    }
    
    public function delete($id) {
        if ($this->matiereModel->delete($id)) {
            $this->sendResponse(null, 200, 'Matière supprimée avec succès');
        } else {
            $this->sendResponse(null, 500, 'Erreur lors de la suppression');
        }
    }
    
    private function codeExists($code, $etablissementId, $excludeId = null) {
        $query = "SELECT id FROM matieres WHERE code = :code AND etablissement_id = :etablissement_id";
        if ($excludeId) {
            $query .= " AND id != :exclude_id";
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':code', strtoupper($code));
        $stmt->bindParam(':etablissement_id', $etablissementId, PDO::PARAM_INT);
        if ($excludeId) {
            $stmt->bindParam(':exclude_id', $excludeId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetch() !== false;
    }
}

?>