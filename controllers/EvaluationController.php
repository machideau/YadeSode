<?php
class EvaluationController extends ApiController {
    private $evaluationModel;
    private $noteModel;
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
        $this->evaluationModel = new Evaluation($this->db);
        $this->noteModel = new Note($this->db);
    }
    
    public function getAll() {
        $evaluations = $this->evaluationModel->findAll();
        $this->sendResponse($evaluations);
    }
    
    public function getById($id) {
        $evaluation = $this->evaluationModel->findById($id);
        if (!$evaluation) {
            $this->sendResponse(null, 404, 'Évaluation non trouvée');
        }
        $this->sendResponse($evaluation);
    }
    
    public function create() {
        $data = $this->getRequestData();
        $this->validateRequired($data, [
            'titre', 'date_evaluation', 'classe_matiere_professeur_id', 
            'periode_id', 'type_evaluation_id'
        ]);
        
        $id = $this->evaluationModel->create($data);
        if ($id) {
            $evaluation = $this->evaluationModel->findById($id);
            $this->sendResponse($evaluation, 201, 'Évaluation créée avec succès');
        } else {
            $this->sendResponse(null, 500, 'Erreur lors de la création');
        }
    }
    
    public function update($id) {
        $data = $this->getRequestData();
        $this->validateRequired($data, ['titre', 'date_evaluation']);
        
        if ($this->evaluationModel->update($id, $data)) {
            $evaluation = $this->evaluationModel->findById($id);
            $this->sendResponse($evaluation, 200, 'Évaluation mise à jour avec succès');
        } else {
            $this->sendResponse(null, 500, 'Erreur lors de la mise à jour');
        }
    }
    
    public function delete($id) {
        if ($this->evaluationModel->delete($id)) {
            $this->sendResponse(null, 200, 'Évaluation supprimée avec succès');
        } else {
            $this->sendResponse(null, 500, 'Erreur lors de la suppression');
        }
    }
    
    public function getByProfesseur($professeurId, $periodeId = null) {
        if (!$periodeId) {
            $this->sendResponse(null, 400, 'Période requise');
        }
        
        $evaluations = $this->evaluationModel->getByProfesseurAndPeriode($professeurId, $periodeId);
        $this->sendResponse($evaluations);
    }
    
    public function getWithNotes($id) {
        $data = $this->evaluationModel->getWithNotes($id);
        
        // Restructurer les données pour une meilleure lisibilité
        $evaluation = null;
        $notes = [];
        
        foreach ($data as $row) {
            if (!$evaluation) {
                $evaluation = [
                    'id' => $row['id'],
                    'titre' => $row['titre'],
                    'date_evaluation' => $row['date_evaluation'],
                    'note_sur' => $row['note_sur'],
                    'matiere_nom' => $row['matiere_nom'],
                    'classe_nom' => $row['classe_nom'],
                    'type_nom' => $row['type_nom']
                ];
            }
            
            if ($row['note_id']) {
                $notes[] = [
                    'id' => $row['note_id'],
                    'note' => $row['note'],
                    'statut' => $row['statut'],
                    'commentaire' => $row['commentaire'],
                    'eleve' => [
                        'nom' => $row['eleve_nom'],
                        'prenoms' => $row['eleve_prenoms'],
                        'matricule' => $row['matricule']
                    ]
                ];
            }
        }
        
        $this->sendResponse([
            'evaluation' => $evaluation,
            'notes' => $notes
        ]);
    }
}
?>