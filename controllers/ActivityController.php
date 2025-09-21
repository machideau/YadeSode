<?php
class ActivityController extends ApiController {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
    }

    public function recent() {
        $etablissementId = $this->getUserOrDefaultEtablissementId();

        // Agréger quelques activités récentes (dernières 10)
        $activities = [];

        // Nouveaux élèves
        $q = "SELECT u.nom, u.prenoms, e.created_at as created_at
              FROM eleves e JOIN users u ON e.user_id = u.id
              WHERE u.etablissement_id = :eid
              ORDER BY e.created_at DESC LIMIT 3";
        $stmt = $this->db->prepare($q);
        $stmt->bindParam(':eid', $etablissementId, PDO::PARAM_INT);
        $stmt->execute();
        foreach ($stmt->fetchAll() as $row) {
            $activities[] = [
                'icon' => 'fas fa-user-plus',
                'color' => 'text-blue-600',
                'bg' => 'bg-blue-100',
                'message' => 'Nouvel élève ajouté: ' . $row['nom'] . ' ' . $row['prenoms'],
                'created_at' => $row['created_at']
            ];
        }

        // Imports fichiers récents
        $q = "SELECT nom_fichier, statut, created_at FROM imports_fichiers
              WHERE importe_par IN (SELECT id FROM users WHERE etablissement_id = :eid)
              ORDER BY created_at DESC LIMIT 3";
        $stmt = $this->db->prepare($q);
        $stmt->bindParam(':eid', $etablissementId, PDO::PARAM_INT);
        $stmt->execute();
        foreach ($stmt->fetchAll() as $row) {
            $activities[] = [
                'icon' => 'fas fa-upload',
                'color' => 'text-green-600',
                'bg' => 'bg-green-100',
                'message' => 'Import ' . $row['statut'] . ': ' . $row['nom_fichier'],
                'created_at' => $row['created_at']
            ];
        }

        // Bulletins générés récents
        $q = "SELECT b.genere_le as created_at, u.nom, u.prenoms, p.nom as periode_nom
              FROM bulletins b
              JOIN eleves e ON b.eleve_id = e.id
              JOIN users u ON e.user_id = u.id
              JOIN periodes p ON b.periode_id = p.id
              WHERE u.etablissement_id = :eid
              ORDER BY b.genere_le DESC LIMIT 3";
        $stmt = $this->db->prepare($q);
        $stmt->bindParam(':eid', $etablissementId, PDO::PARAM_INT);
        $stmt->execute();
        foreach ($stmt->fetchAll() as $row) {
            $activities[] = [
                'icon' => 'fas fa-file-pdf',
                'color' => 'text-purple-600',
                'bg' => 'bg-purple-100',
                'message' => 'Bulletin généré: ' . $row['nom'] . ' ' . $row['prenoms'] . ' (' . $row['periode_nom'] . ')',
                'created_at' => $row['created_at']
            ];
        }

        // Trier par date desc et limiter à 10
        usort($activities, function($a, $b) {
            return strtotime($b['created_at']) <=> strtotime($a['created_at']);
        });
        $activities = array_slice($activities, 0, 10);

        $this->sendResponse($activities);
    }
}
?>


