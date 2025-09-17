<?php
class NoteController extends ApiController {
    private $noteModel;
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
        $this->noteModel = new Note($this->db);
    }
    
    public function create() {
        $data = $this->getRequestData();
        $this->validateRequired($data, ['eleve_id', 'evaluation_id', 'saisie_par']);
        
        $id = $this->noteModel->create($data);
        if ($id) {
            $note = $this->noteModel->findById($id);
            $this->sendResponse($note, 201, 'Note créée avec succès');
        } else {
            $this->sendResponse(null, 500, 'Erreur lors de la création');
        }
    }
    
    public function update($id) {
        $data = $this->getRequestData();
        
        if ($this->noteModel->update($id, $data)) {
            $note = $this->noteModel->findById($id);
            $this->sendResponse($note, 200, 'Note mise à jour avec succès');
        } else {
            $this->sendResponse(null, 500, 'Erreur lors de la mise à jour');
        }
    }
    
    public function batchUpdate() {
        $data = $this->getRequestData();
        
        if (!isset($data['notes']) || !is_array($data['notes'])) {
            $this->sendResponse(null, 400, 'Format de données incorrect');
        }
        
        try {
            $this->noteModel->batchUpdate($data['notes']);
            $this->sendResponse(null, 200, 'Notes mises à jour avec succès');
        } catch (Exception $e) {
            $this->sendResponse(null, 500, 'Erreur: ' . $e->getMessage());
        }
    }
    
    public function getByEleve($eleveId, $periodeId = null) {
        if (!$periodeId) {
            $this->sendResponse(null, 400, 'Période requise');
        }
        
        $notes = $this->noteModel->getByEleveAndPeriode($eleveId, $periodeId);
        $this->sendResponse($notes);
    }
    
    public function getMoyenneMatiere($eleveId, $matiereId, $periodeId) {
        $moyenne = $this->noteModel->calculateMoyenneMatiere($eleveId, $matiereId, $periodeId);
        $this->sendResponse(['moyenne' => $moyenne]);
    }
}
?>