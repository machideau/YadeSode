<?php
class DevController extends ApiController {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function seed() {
        if (($_GET['confirm'] ?? '') !== '1') {
            $this->sendResponse(null, 400, "Confirmez avec ?confirm=1");
        }

        try {
            $this->db->beginTransaction();

            // Etablissement
            $etabId = $this->ensureEtablissement();

            // Année scolaire active
            $anneeId = $this->ensureAnneeScolaire($etabId);

            // Admin user
            $adminId = $this->ensureUser([
                'nom' => 'Admin',
                'prenoms' => 'Système',
                'email' => 'admin@etablissement.com',
                'sexe' => 'M',
                'type_user' => 'admin',
                'matricule' => 'ADM2025001',
                'mot_de_passe' => password_hash('admin123', PASSWORD_BCRYPT),
                'etablissement_id' => $etabId,
                'actif' => 1
            ]);

            // Professeur
            $profId = $this->ensureUser([
                'nom' => 'Dupont',
                'prenoms' => 'Paul',
                'email' => 'prof.maths@etab.com',
                'sexe' => 'M',
                'type_user' => 'professeur',
                'matricule' => 'PRF2025001',
                'mot_de_passe' => password_hash('prof123', PASSWORD_BCRYPT),
                'etablissement_id' => $etabId,
                'actif' => 1
            ]);

            // Classe
            $classeId = $this->ensureClasse([
                'nom' => '6ème A',
                'niveau' => '6ème',
                'section' => 'A',
                'effectif_max' => 35,
                'etablissement_id' => $etabId,
                'annee_scolaire_id' => $anneeId,
                'professeur_principal_id' => $profId
            ]);

            // Élèves (users + eleves)
            $eleve1UserId = $this->ensureUser([
                'nom' => 'Kone',
                'prenoms' => 'Awa',
                'email' => 'awa.kone@etab.com',
                'sexe' => 'F',
                'type_user' => 'eleve',
                'matricule' => 'EL2025001',
                'mot_de_passe' => password_hash('eleve123', PASSWORD_BCRYPT),
                'etablissement_id' => $etabId,
                'actif' => 1
            ]);
            $this->ensureEleve($eleve1UserId, $classeId, 1);

            $eleve2UserId = $this->ensureUser([
                'nom' => 'Traore',
                'prenoms' => 'Ibrahim',
                'email' => 'ibrahim.traore@etab.com',
                'sexe' => 'M',
                'type_user' => 'eleve',
                'matricule' => 'EL2025002',
                'mot_de_passe' => password_hash('eleve123', PASSWORD_BCRYPT),
                'etablissement_id' => $etabId,
                'actif' => 1
            ]);
            $this->ensureEleve($eleve2UserId, $classeId, 2);

            $this->db->commit();

            $this->sendResponse([
                'etablissement_id' => $etabId,
                'annee_scolaire_id' => $anneeId,
                'admin_user_id' => $adminId,
                'professeur_id' => $profId,
                'classe_id' => $classeId
            ], 201, 'Données de démo créées');
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->sendResponse(null, 500, 'Erreur seed: ' . $e->getMessage());
        }
    }

    private function ensureEtablissement() {
        $stmt = $this->db->query("SELECT id FROM etablissements LIMIT 1");
        $row = $stmt->fetch();
        if ($row) return (int)$row['id'];

        $stmt = $this->db->prepare("INSERT INTO etablissements (nom, adresse, telephone, email, directeur) VALUES ('Etablissement Démo', 'Abidjan', '0102030405', 'contact@demo.com', 'Directeur Démo')");
        $stmt->execute();
        return (int)$this->db->lastInsertId();
    }

    private function ensureAnneeScolaire($etablissementId) {
        $stmt = $this->db->prepare("SELECT id FROM annees_scolaires WHERE active = 1 AND etablissement_id = :eid LIMIT 1");
        $stmt->bindParam(':eid', $etablissementId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        if ($row) return (int)$row['id'];

        $libelle = date('Y') . '-' . (date('Y') + 1);
        $stmt = $this->db->prepare("INSERT INTO annees_scolaires (libelle, date_debut, date_fin, active, etablissement_id) VALUES (:libelle, :debut, :fin, 1, :eid)");
        $debut = date('Y') . "-09-01";
        $fin = (date('Y') + 1) . "-06-30";
        $stmt->bindParam(':libelle', $libelle);
        $stmt->bindParam(':debut', $debut);
        $stmt->bindParam(':fin', $fin);
        $stmt->bindParam(':eid', $etablissementId, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$this->db->lastInsertId();
    }

    private function ensureUser($data) {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE matricule = :matricule LIMIT 1");
        $stmt->bindParam(':matricule', $data['matricule']);
        $stmt->execute();
        $row = $stmt->fetch();
        if ($row) return (int)$row['id'];

        $stmt = $this->db->prepare("INSERT INTO users (nom, prenoms, email, sexe, type_user, matricule, mot_de_passe, actif, etablissement_id) VALUES (:nom, :prenoms, :email, :sexe, :type_user, :matricule, :mot_de_passe, :actif, :eid)");
        $stmt->bindParam(':nom', $data['nom']);
        $stmt->bindParam(':prenoms', $data['prenoms']);
        $stmt->bindParam(':email', $data['email']);
        $stmt->bindParam(':sexe', $data['sexe']);
        $stmt->bindParam(':type_user', $data['type_user']);
        $stmt->bindParam(':matricule', $data['matricule']);
        $stmt->bindParam(':mot_de_passe', $data['mot_de_passe']);
        $stmt->bindParam(':actif', $data['actif'], PDO::PARAM_INT);
        $stmt->bindParam(':eid', $data['etablissement_id'], PDO::PARAM_INT);
        $stmt->execute();
        return (int)$this->db->lastInsertId();
    }

    private function ensureClasse($data) {
        $stmt = $this->db->prepare("SELECT id FROM classes WHERE nom = :nom AND etablissement_id = :eid AND annee_scolaire_id = :aid LIMIT 1");
        $stmt->bindParam(':nom', $data['nom']);
        $stmt->bindParam(':eid', $data['etablissement_id'], PDO::PARAM_INT);
        $stmt->bindParam(':aid', $data['annee_scolaire_id'], PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        if ($row) return (int)$row['id'];

        $stmt = $this->db->prepare("INSERT INTO classes (nom, niveau, section, effectif_max, etablissement_id, annee_scolaire_id, professeur_principal_id) VALUES (:nom, :niveau, :section, :effectif_max, :eid, :aid, :ppid)");
        $stmt->bindParam(':nom', $data['nom']);
        $stmt->bindParam(':niveau', $data['niveau']);
        $stmt->bindParam(':section', $data['section']);
        $stmt->bindParam(':effectif_max', $data['effectif_max'], PDO::PARAM_INT);
        $stmt->bindParam(':eid', $data['etablissement_id'], PDO::PARAM_INT);
        $stmt->bindParam(':aid', $data['annee_scolaire_id'], PDO::PARAM_INT);
        $stmt->bindParam(':ppid', $data['professeur_principal_id'], PDO::PARAM_INT);
        $stmt->execute();
        return (int)$this->db->lastInsertId();
    }

    private function ensureEleve($userId, $classeId, $numeroOrdre) {
        $stmt = $this->db->prepare("SELECT id FROM eleves WHERE user_id = :uid LIMIT 1");
        $stmt->bindParam(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        if ($row) return (int)$row['id'];

        $today = date('Y-m-d');
        $stmt = $this->db->prepare("INSERT INTO eleves (user_id, classe_id, numero_ordre, date_inscription, statut) VALUES (:uid, :cid, :num, :date_inscription, 'inscrit')");
        $stmt->bindParam(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':cid', $classeId, PDO::PARAM_INT);
        $stmt->bindParam(':num', $numeroOrdre, PDO::PARAM_INT);
        $stmt->bindParam(':date_inscription', $today);
        $stmt->execute();
        return (int)$this->db->lastInsertId();
    }
}
?>


