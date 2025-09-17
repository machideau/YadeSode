<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Inclure tous les fichiers nécessaires
require_once 'config/database.php';
require_once 'models/BaseModel.php';
require_once 'models/Etablissement.php';
require_once 'models/Classe.php';
require_once 'models/User.php';
require_once 'models/Eleve.php';
require_once 'models/Matiere.php';
require_once 'controllers/ApiController.php';
require_once 'controllers/ClasseController.php';

// Router simple
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$path_parts = explode('/', trim($path, '/'));

// Supprimer 'api' du chemin si présent
if ($path_parts[0] === 'api') {
    array_shift($path_parts);
}

$controller = $path_parts[0] ?? '';
$id = $path_parts[1] ?? null;
$action = $path_parts[2] ?? null;

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