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
        $etabId = null;
        if (session_status() === PHP_SESSION_NONE) { session_start(); }
        if (isset($_SESSION['etablissement_id'])) { $etabId = $_SESSION['etablissement_id']; }
        $eleves = $this->eleveModel->findAllWithUser($etabId);
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

    public function importCSV() {
        if (!isset($_FILES['file'])) {
            $this->sendResponse(null, 400, 'Aucun fichier fourni');
        }

        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->sendResponse(null, 400, 'Erreur upload');
        }

        $classeId = $_POST['classe_id'] ?? null;
        $etablissementId = $_POST['etablissement_id'] ?? null;
        if (!$classeId || !$etablissementId) {
            $this->sendResponse(null, 400, 'classe_id et etablissement_id requis');
        }

        $tmpPath = $file['tmp_name'];
        $imported = 0;
        $errors = [];

        if (($handle = fopen($tmpPath, 'r')) !== false) {
            $headers = fgetcsv($handle, 1000, ';');
            if (!$headers) {
                fclose($handle);
                $this->sendResponse(null, 400, 'CSV vide');
            }
            $headers = array_map('strtolower', $headers);

            $required = ['nom', 'prenoms', 'sexe'];
            $missing = array_diff($required, $headers);
            if (!empty($missing)) {
                fclose($handle);
                $this->sendResponse(null, 400, 'En-têtes requis manquants: ' . implode(', ', $missing));
            }

            $idx = array_flip($headers);
            $line = 1;
            while (($row = fgetcsv($handle, 1000, ';')) !== false) {
                $line++;
                try {
                    $data = [
                        'nom' => $row[$idx['nom']] ?? '',
                        'prenoms' => $row[$idx['prenoms']] ?? '',
                        'sexe' => $row[$idx['sexe']] ?? 'M',
                        'email' => $idx['email'] !== null && isset($row[$idx['email']]) ? $row[$idx['email']] : null,
                        'telephone' => $idx['telephone'] !== null && isset($row[$idx['telephone']]) ? $row[$idx['telephone']] : null,
                        'classe_id' => (int)$classeId,
                        'etablissement_id' => (int)$etablissementId
                    ];
                    $_POST_BACKUP = $_POST;
                    // Utiliser la méthode create() existante
                    $this->createFromArray($data);
                    $_POST = $_POST_BACKUP;
                    $imported++;
                } catch (Exception $e) {
                    $errors[] = "Ligne $line: " . $e->getMessage();
                }
            }
            fclose($handle);
        }

        $this->sendResponse([
            'imported' => $imported,
            'errors' => $errors
        ], 200, 'Import terminé');
    }

    // Helper to reuse create logic without raw input
    private function createFromArray($data) {
        // Simuler getRequestData
        $input = json_encode($data);
        // Temp override php://input not trivial; call model directly here
        $this->db->beginTransaction();
        try {
            $userData = [
                'nom' => $data['nom'],
                'prenoms' => $data['prenoms'],
                'email' => $data['email'] ?? null,
                'telephone' => $data['telephone'] ?? null,
                'date_naissance' => $data['date_naissance'] ?? null,
                'sexe' => $data['sexe'] ?? 'M',
                'adresse' => $data['adresse'] ?? null,
                'type_user' => 'eleve',
                'matricule' => $this->userModel->generateMatricule('eleve', $data['etablissement_id']),
                'mot_de_passe' => 'eleve123',
                'etablissement_id' => $data['etablissement_id']
            ];
            $userId = $this->userModel->create($userData);
            if (!$userId) { throw new Exception('Création utilisateur échouée'); }

            $eleveData = [
                'user_id' => $userId,
                'classe_id' => $data['classe_id'],
                'date_inscription' => $data['date_inscription'] ?? date('Y-m-d'),
                'nom_pere' => $data['nom_pere'] ?? null,
                'nom_mere' => $data['nom_mere'] ?? null,
                'telephone_tuteur' => $data['telephone_tuteur'] ?? null
            ];
            $eleveId = $this->eleveModel->create($eleveData);
            if (!$eleveId) { throw new Exception("Création élève échouée"); }
            $this->db->commit();
            return $eleveId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}

?>