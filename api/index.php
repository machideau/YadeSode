<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Inclure tous les fichiers nécessaires
require_once '../config/database.php';
require_once '../models/BaseModel.php';
require_once '../models/Etablissement.php';
require_once '../models/Classe.php';
require_once '../models/User.php';
require_once '../models/Eleve.php';
require_once '../models/Matiere.php';
require_once '../models/Evaluation.php';
require_once '../models/Note.php';
require_once '../controllers/ApiController.php';
require_once '../middleware/AuthMiddleware.php';
require_once '../controllers/ClasseController.php';
require_once '../controllers/EleveController.php';
require_once '../controllers/MatiereController.php';
require_once '../controllers/EvaluationController.php';
require_once '../controllers/NoteController.php';
require_once '../controllers/FileUploadController.php';
require_once '../services/BulletinService.php';
require_once '../controllers/BulletinController.php';
require_once '../services/StatisticsService.php';
require_once '../controllers/StatsController.php';
require_once '../controllers/DevController.php';
require_once '../controllers/AnneeController.php';
require_once '../controllers/ActivityController.php';

// Router simple
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$all_parts = array_values(array_filter(explode('/', trim($path, '/'))));

// Trouver le segment 'api' même si le projet est servi sous un sous-dossier (ex: /YadeSode/api/...)
$apiIndex = array_search('api', $all_parts, true);
if ($apiIndex !== false) {
    $path_parts = array_slice($all_parts, $apiIndex + 1);
} else {
    $path_parts = $all_parts;
}

$controller = $path_parts[0] ?? '';
$id = $path_parts[1] ?? null;
$action = $path_parts[2] ?? null;
$param = $path_parts[3] ?? null;

try {
    switch ($controller) {
        case 'classes':
            $classeController = new ClasseController();
            
            switch ($_SERVER['REQUEST_METHOD']) {
                case 'GET':
                    if ($id && $action === 'eleves') {
                        $classeController->getEleves($id);
                    } elseif ($id) {
                        $classeController->getById($id);
                    } else {
                        $classeController->getAll();
                    }
                    break;
                    
                case 'POST':
                    $classeController->create();
                    break;
                    
                case 'PUT':
                    if ($id) {
                        $classeController->update($id);
                    }
                    break;
                    
                case 'DELETE':
                    if ($id) {
                        $classeController->delete($id);
                    }
                    break;
            }
            break;
            
        case 'eleves':
            $eleveController = new EleveController();
            
            switch ($_SERVER['REQUEST_METHOD']) {
                case 'GET':
                    if ($id && $action === 'notes' && $param) {
                        $noteController = new NoteController();
                        $noteController->getByEleve($id, $param);
                    } elseif ($id) {
                        $eleveController->getById($id);
                    } elseif ($action === 'classe' && $param) {
                        $eleveController->getByClasse($param);
                    } else {
                        $eleveController->getAll();
                    }
                    break;
                    
                case 'POST':
                    if ($action === 'import') {
                        $eleveController->importCSV();
                    } else {
                        $eleveController->create();
                    }
                    break;
                    
                case 'PUT':
                    if ($id) {
                        $eleveController->update($id);
                    }
                    break;
                    
                case 'DELETE':
                    if ($id) {
                        $eleveController->delete($id);
                    }
                    break;
            }
            break;
            
        case 'matieres':
            $matiereController = new MatiereController();
            
            switch ($_SERVER['REQUEST_METHOD']) {
                case 'GET':
                    if ($id) {
                        $matiereController->getById($id);
                    } else {
                        $matiereController->getAll();
                    }
                    break;
                    
                case 'POST':
                    $matiereController->create();
                    break;
                    
                case 'PUT':
                    if ($id) {
                        $matiereController->update($id);
                    }
                    break;
                    
                case 'DELETE':
                    if ($id) {
                        $matiereController->delete($id);
                    }
                    break;
            }
            break;

        case 'annees':
            $anneeController = new AnneeController();
            switch ($_SERVER['REQUEST_METHOD']) {
                case 'GET':
                    $anneeController->getAll();
                    break;
            }
            break;
            
        case 'evaluations':
            $evaluationController = new EvaluationController();
            
            switch ($_SERVER['REQUEST_METHOD']) {
                case 'GET':
                    if ($id && $action === 'notes') {
                        $evaluationController->getWithNotes($id);
                    } elseif ($id) {
                        $evaluationController->getById($id);
                    } elseif ($action === 'professeur' && $param) {
                        // GET /evaluations/professeur/{prof_id}?periode={periode_id}
                        $evaluationController->getByProfesseur($param, $_GET['periode'] ?? null);
                    } else {
                        $evaluationController->getAll();
                    }
                    break;
                    
                case 'POST':
                    $evaluationController->create();
                    break;
                    
                case 'PUT':
                    if ($id) {
                        $evaluationController->update($id);
                    }
                    break;
                    
                case 'DELETE':
                    if ($id) {
                        $evaluationController->delete($id);
                    }
                    break;
            }
            break;
            
        case 'notes':
            $noteController = new NoteController();
            
            switch ($_SERVER['REQUEST_METHOD']) {
                case 'GET':
                    if ($id && $action === 'moyenne' && $param) {
                        // GET /notes/{eleve_id}/moyenne/{matiere_id}?periode={periode_id}
                        $noteController->getMoyenneMatiere($id, $param, $_GET['periode'] ?? null);
                    }
                    break;
                    
                case 'POST':
                    if ($action === 'batch') {
                        $noteController->batchUpdate();
                    } else {
                        $noteController->create();
                    }
                    break;
                    
                case 'PUT':
                    if ($id) {
                        $noteController->update($id);
                    }
                    break;
            }
            break;
            
        case 'upload':
            $uploadController = new FileUploadController();
            
            switch ($_SERVER['REQUEST_METHOD']) {
                case 'POST':
                    if ($action === 'import' && $id) {
                        // POST /upload/import/{import_id}
                        $uploadController->importToDatabase($id);
                    } else {
                        $uploadController->upload();
                    }
                    break;
                    
                case 'GET':
                    if ($action === 'status' && $id) {
                        // GET /upload/status/{import_id}
                        $uploadController->getImportStatus($id);
                    }
                    break;
            }
            break;
            
        case 'bulletins':
            $bulletinController = new BulletinController();
            
            switch ($_SERVER['REQUEST_METHOD']) {
                case 'POST':
                    if ($action === 'generate') {
                        if ($id === 'classe' && $param) {
                            // POST /bulletins/generate/classe/{classe_id}?periode={periode_id}
                            $bulletinController->generateForClasse($param, $_GET['periode'] ?? null);
                        } elseif ($id && $action === 'generate' && $param) {
                            // POST /bulletins/{eleve_id}/generate/{periode_id}
                            $bulletinController->generate($id, $param);
                        }
                    }
                    break;
                    
                case 'GET':
                    if ($id && $action === 'download') {
                        // GET /bulletins/{bulletin_id}/download
                        $bulletinController->download($id);
                    } elseif ($id === 'classe' && $action && isset($_GET['annee'])) {
                        // GET /bulletins/classe/{classe_id}?annee={annee_id}
                        $bulletinController->downloadClasseZip($action, $_GET['annee']);
                    }
                    break;
            }
            break;

        case 'stats':
            $statsController = new StatsController();

            switch ($_SERVER['REQUEST_METHOD']) {
                case 'GET':
                    if ($id === 'dashboard') {
                        // GET /stats/dashboard
                        $statsController->getDashboardStats();
                    } elseif ($id === 'classe' && $action && isset($_GET['periode'])) {
                        // GET /stats/classe/{classe_id}?periode={periode_id}
                        $statsController->getClasseStats($action, $_GET['periode']);
                    } elseif ($id === 'matiere' && $action && $param && isset($_GET['periode'])) {
                        // GET /stats/matiere/{matiere_id}/{classe_id}?periode={periode_id}
                        $statsController->getMatiereStats($action, $param, $_GET['periode']);
                    } else {
                        http_response_code(400);
                        echo json_encode(['status' => 'error', 'message' => 'Requête stats invalide']);
                    }
                    break;
            }
            break;

        case 'activity':
            $activityController = new ActivityController();
            switch ($_SERVER['REQUEST_METHOD']) {
                case 'GET':
                    if ($id === 'recent') {
                        $activityController->recent();
                    }
                    break;
            }
            break;

        case 'dev':
            $devController = new DevController();
            switch ($_SERVER['REQUEST_METHOD']) {
                case 'GET':
                    if ($id === 'seed') {
                        $devController->seed();
                    } else {
                        http_response_code(404);
                        echo json_encode(['status' => 'error', 'message' => 'Endpoint dev non trouvé']);
                    }
                    break;
            }
            break;
            
        case 'auth':
            $authController = new AuthController();
            
            switch ($_SERVER['REQUEST_METHOD']) {
                case 'POST':
                    if ($action === 'login') {
                        $authController->login();
                    } elseif ($action === 'logout') {
                        $authController->logout();
                    }
                    break;
                    
                case 'GET':
                    if ($action === 'me') {
                        $authController->me();
                    }
                    break;
            }
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Endpoint non trouvé']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}
?>