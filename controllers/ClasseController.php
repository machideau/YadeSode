<?php
class ClasseController extends ApiController {
    private $classeModel;
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
        $this->classeModel = new Classe($this->db);
    }
    
    public function getAll() {
        $classes = $this->classeModel->getWithEtablissement();
        $this->sendResponse($classes);
    }
    
    public function getById($id) {
        $classe = $this->classeModel->findById($id);
        if (!$classe) {
            $this->sendResponse(null, 404, 'Classe non trouvée');
        }
        $this->sendResponse($classe);
    }
    
    public function create() {
        $data = $this->getRequestData();
        $this->validateRequired($data, ['nom', 'etablissement_id', 'annee_scolaire_id']);
        
        $id = $this->classeModel->create($data);
        if ($id) {
            $classe = $this->classeModel->findById($id);
            $this->sendResponse($classe, 201, 'Classe créée avec succès');
        } else {
            $this->sendResponse(null, 500, 'Erreur lors de la création');
        }
    }
    
    public function update($id) {
        $data = $this->getRequestData();
        $this->validateRequired($data, ['nom']);
        
        if ($this->classeModel->update($id, $data)) {
            $classe = $this->classeModel->findById($id);
            $this->sendResponse($classe, 200, 'Classe mise à jour avec succès');
        } else {
            $this->sendResponse(null, 500, 'Erreur lors de la mise à jour');
        }
    }
    
    public function delete($id) {
        if ($this->classeModel->delete($id)) {
            $this->sendResponse(null, 200, 'Classe supprimée avec succès');
        } else {
            $this->sendResponse(null, 500, 'Erreur lors de la suppression');
        }
    }
    
    public function getEleves($id) {
        $eleves = $this->classeModel->getEleves($id);
        $this->sendResponse($eleves);
    }
}
?>