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
        try {
            $data = $this->getRequestData();
            // Etablissement depuis session par défaut
            if (empty($data['etablissement_id'])) {
                if (session_status() === PHP_SESSION_NONE) { session_start(); }
                $data['etablissement_id'] = $_SESSION['etablissement_id'] ?? null;
            }

            // Générer un code si absent
            if (empty($data['code'])) {
                $data['code'] = $this->generateMatiereCode($data['nom']);
            }

            // Champs par défaut
            $data['coefficient'] = $data['coefficient'] ?? 1.0;
            $data['couleur'] = $data['couleur'] ?? '#000000';
            $data['description'] = $data['description'] ?? '';

            $this->validateRequired($data, ['nom', 'code', 'etablissement_id']);

            // Vérifier l'unicité du code
            if ($this->codeExists($data['code'], $data['etablissement_id'])) {
                $this->sendResponse(null, 400, 'Ce code de matière existe déjà');
            }

            $id = $this->matiereModel->create($data);
            if ($id) {
                // Optionnel: liaison à une classe si fournie
                if (!empty($data['classe_id'])) {
                    $this->linkMatiereToClasse($id, (int)$data['classe_id']);
                }

                $matiere = $this->matiereModel->findById($id);
                $this->sendResponse($matiere, 201, 'Matière créée avec succès');
            } else {
                $this->sendResponse(null, 500, 'Erreur lors de la création');
            }
        } catch (Exception $e) {
            $this->sendResponse(null, 500, 'Erreur serveur: ' . $e->getMessage());
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

    private function generateMatiereCode($nom) {
        $nom = trim($nom);
        if ($nom === '') { return 'MAT'; }
        // Acronyme des premières lettres des mots, sinon 4 premières lettres
        $words = preg_split('/\s+/', $nom);
        $acronym = '';
        foreach ($words as $w) { $acronym .= mb_substr($w, 0, 1); }
        $code = strtoupper($acronym);
        if (mb_strlen($code) < 2) {
            $code = strtoupper(mb_substr(preg_replace('/[^a-zA-Z]/', '', $nom), 0, 4));
        }
        return $code;
    }

    private function linkMatiereToClasse($matiereId, $classeId) {
        // Récupérer la classe et son professeur principal
        $stmt = $this->db->prepare("SELECT professeur_principal_id FROM classes WHERE id = :id");
        $stmt->bindParam(':id', $classeId, PDO::PARAM_INT);
        $stmt->execute();
        $classe = $stmt->fetch();
        if (!$classe) {
            $this->sendResponse(null, 400, 'Classe introuvable');
        }
        $profId = $classe['professeur_principal_id'];
        if (!$profId) {
            // Si pas de professeur principal, on ne bloque pas la création de matière, mais on n'établit pas la liaison
            return;
        }

        // Vérifier si liaison existe déjà
        $check = $this->db->prepare("SELECT id FROM classe_matiere_professeur WHERE classe_id = :cid AND matiere_id = :mid AND professeur_id = :pid");
        $check->bindParam(':cid', $classeId, PDO::PARAM_INT);
        $check->bindParam(':mid', $matiereId, PDO::PARAM_INT);
        $check->bindParam(':pid', $profId, PDO::PARAM_INT);
        $check->execute();
        if ($check->fetch()) { return; }

        // Créer la liaison
        $ins = $this->db->prepare("INSERT INTO classe_matiere_professeur (classe_id, matiere_id, professeur_id, coefficient_classe) VALUES (:cid, :mid, :pid, 1.0)");
        $ins->bindParam(':cid', $classeId, PDO::PARAM_INT);
        $ins->bindParam(':mid', $matiereId, PDO::PARAM_INT);
        $ins->bindParam(':pid', $profId, PDO::PARAM_INT);
        $ins->execute();
    }
}

?>