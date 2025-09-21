<?php
class AnneeController extends ApiController {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
    }

    public function getAll() {
        $stmt = $this->db->query("SELECT id, libelle, date_debut, date_fin, active FROM annees_scolaires ORDER BY id DESC");
        $annees = $stmt->fetchAll();
        $this->sendResponse($annees);
    }
}
?>


