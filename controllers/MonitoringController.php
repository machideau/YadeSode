<?php
class MonitoringController extends ApiController {
    private $monitoringService;
    private $backupService;
    private $authMiddleware;
    private $logger;
    
    public function __construct() {
        $database = new Database();
        $db = $database->connect();
        
        $this->monitoringService = new MonitoringService($db);
        $this->backupService = new BackupService($db);
        $this->authMiddleware = new AuthMiddleware($db);
        $this->logger = Logger::getInstance();
    }
    
    public function getHealthCheck() {
        // Accessible sans authentification pour les checks automatiques
        $health = $this->monitoringService->checkSystemHealth();
        
        http_response_code($health['status'] === 'healthy' ? 200 : 503);
        $this->sendResponse($health);
    }
    
    public function getSystemMetrics($period = '24h') {
        $this->authMiddleware->requireRole(['admin']);
        
        $metrics = $this->monitoringService->getSystemMetrics($period);
        $this->sendResponse($metrics);
    }
    
    public function getLogs($category = 'general', $lines = 100) {
        $this->authMiddleware->requireRole(['admin']);
        
        $logs = $this->logger->getRecentLogs($category, $lines);
        $this->sendResponse(['logs' => $logs]);
    }
    
    public function createBackup($type = 'full') {
        $this->authMiddleware->requireRole(['admin']);
        
        switch ($type) {
            case 'database':
                $result = $this->backupService->createDatabaseBackup();
                break;
            case 'files':
                $result = $this->backupService->createFilesBackup();
                break;
            case 'full':
            default:
                $result = $this->backupService->createFullBackup();
                break;
        }
        
        if ($result['success']) {
            $this->sendResponse($result, 200, 'Backup créé avec succès');
        } else {
            $this->sendResponse($result, 500, 'Erreur lors du backup');
        }
    }
    
    public function listBackups() {
        $this->authMiddleware->requireRole(['admin']);
        
        $backups = $this->backupService->listBackups();
        $this->sendResponse(['backups' => $backups]);
    }
    
    public function downloadBackup($filename) {
        $this->authMiddleware->requireRole(['admin']);
        
        $backupPath = __DIR__ . '/../backups/' . $filename;
        
        // Validation du nom de fichier pour éviter les path traversal
        if (!preg_match('/^backup_[a-zA-Z0-9_\-\.]+$/', $filename) || !file_exists($backupPath)) {
            $this->sendResponse(null, 404, 'Backup non trouvé');
        }
        
        // Headers pour le téléchargement
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($backupPath));
        
        readfile($backupPath);
        exit;
    }
}
?>