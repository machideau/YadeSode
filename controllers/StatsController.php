<?php
class StatsController extends ApiController {
    private $statsService;
    private $authMiddleware;
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
        $this->statsService = new StatisticsService($this->db);
        $this->authMiddleware = new AuthMiddleware($this->db);
    }

    public function getDashboardStats() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Mode dev: si non authentifié, utiliser l'établissement 1 par défaut
        $etablissementId = $_SESSION['etablissement_id'] ?? null;
        if (!$etablissementId) {
            $etablissementId = 1; // TODO: rendre configurable si besoin
        }

        $stats = $this->statsService->getDashboardStats($etablissementId);
        $this->sendResponse($stats);
    }

    public function getClasseStats($classeId, $periodeId) {
        $this->authMiddleware->requireRole(['admin', 'professeur']);

        if (!$this->authMiddleware->canAccessClass($classeId)) {
            $this->sendResponse(null, 403, 'Accès non autorisé à cette classe');
        }

        $stats = $this->statsService->getClasseStats($classeId, $periodeId);
        $this->sendResponse($stats);
    }

    public function getMatiereStats($matiereId, $classeId, $periodeId) {
        $this->authMiddleware->requireRole(['admin', 'professeur']);

        $stats = $this->statsService->getMatiereStats($matiereId, $classeId, $periodeId);
        $this->sendResponse($stats);
    }
}
?>