<?php
class AuthController extends ApiController {
    private $userModel;
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
        $this->userModel = new User($this->db);
        
        // Démarrer la session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    public function login() {
        $data = $this->getRequestData();
        $this->validateRequired($data, ['email', 'password']);
        
        $user = $this->userModel->login($data['email'], $data['password']);
        
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_type'] = $user['type_user'];
            $_SESSION['etablissement_id'] = $user['etablissement_id'];
            
            $this->sendResponse([
                'user' => $user,
                'session_id' => session_id()
            ], 200, 'Connexion réussie');
        } else {
            $this->sendResponse(null, 401, 'Email ou mot de passe incorrect');
        }
    }
    
    public function logout() {
        session_destroy();
        $this->sendResponse(null, 200, 'Déconnexion réussie');
    }
    
    public function me() {
        if (!isset($_SESSION['user_id'])) {
            $this->sendResponse(null, 401, 'Non authentifié');
        }
        
        $user = $this->userModel->findById($_SESSION['user_id']);
        if ($user) {
            unset($user['mot_de_passe']);
            $this->sendResponse($user);
        } else {
            $this->sendResponse(null, 404, 'Utilisateur non trouvé');
        }
    }
}
?>