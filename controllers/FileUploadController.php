<?php
require_once 'vendor/autoload.php'; // Pour PHPSpreadsheet

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception;

class FileUploadController extends ApiController {
    private $db;
    private $uploadDir;
    private $allowedTypes = ['excel', 'csv', 'pdf', 'image'];
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
        $this->uploadDir = __DIR__ . '/../uploads/';
        
        // Créer le dossier uploads s'il n'existe pas
        if (!file_exists($this->uploadDir)) {
            mkdir($this->uploadDir, 0777, true);
        }
    }
    
    public function upload() {
        if (!isset($_FILES['file'])) {
            $this->sendResponse(null, 400, 'Aucun fichier fourni');
        }
        
        $file = $_FILES['file'];
        $originalName = $file['name'];
        $tmpName = $file['tmp_name'];
        $fileSize = $file['size'];
        $error = $file['error'];
        
        // Vérifications de base
        if ($error !== UPLOAD_ERR_OK) {
            $this->sendResponse(null, 400, 'Erreur lors de l\'upload: ' . $this->getUploadErrorMessage($error));
        }
        
        if ($fileSize > 10 * 1024 * 1024) { // 10MB max
            $this->sendResponse(null, 400, 'Fichier trop volumineux (max 10MB)');
        }
        
        // Déterminer le type de fichier
        $fileType = $this->detectFileType($originalName, $tmpName);
        if (!in_array($fileType, $this->allowedTypes)) {
            $this->sendResponse(null, 400, 'Type de fichier non supporté');
        }
        
        // Générer un nom unique pour le fichier
        $uniqueName = uniqid() . '_' . $originalName;
        $uploadPath = $this->uploadDir . $uniqueName;
        
        // Déplacer le fichier
        if (!move_uploaded_file($tmpName, $uploadPath)) {
            $this->sendResponse(null, 500, 'Erreur lors de la sauvegarde du fichier');
        }
        
        // Enregistrer dans la base de données
        $importId = $this->saveImportRecord($originalName, $fileType, $uploadPath);
        
        // Lancer la conversion vers CSV
        $this->processFileToCSV($importId, $uploadPath, $fileType);
        
        $this->sendResponse([
            'import_id' => $importId,
            'filename' => $originalName,
            'type' => $fileType,
            'status' => 'en_cours'
        ], 201, 'Fichier uploadé avec succès, conversion en cours...');
    }
    
    private function detectFileType($filename, $filepath) {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        switch ($extension) {
            case 'xlsx':
            case 'xls':
                return 'excel';
            case 'csv':
                return 'csv';
            case 'pdf':
                return 'pdf';
            case 'jpg':
            case 'jpeg':
            case 'png':
            case 'gif':
                return 'image';
            default:
                // Vérification MIME type en fallback
                $mimeType = mime_content_type($filepath);
                if (strpos($mimeType, 'image/') === 0) return 'image';
                if ($mimeType === 'application/pdf') return 'pdf';
                return 'unknown';
        }
    }
    
    private function saveImportRecord($filename, $type, $path) {
        $query = "INSERT INTO imports_fichiers (nom_fichier, type_fichier, chemin_original, importe_par) 
                  VALUES (:filename, :type, :path, :user_id)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':filename', $filename);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':path', $path);
        $stmt->bindParam(':user_id', $_SESSION['user_id'] ?? 1); // A adapter selon votre système d'auth
        
        $stmt->execute();
        return $this->db->lastInsertId();
    }
    
    private function processFileToCSV($importId, $filepath, $type) {
        try {
            $csvPath = '';
            
            switch ($type) {
                case 'excel':
                    $csvPath = $this->convertExcelToCSV($filepath);
                    break;
                case 'csv':
                    $csvPath = $filepath; // Déjà en CSV
                    break;
                case 'pdf':
                    $csvPath = $this->convertPDFToCSV($filepath);
                    break;
                case 'image':
                    $csvPath = $this->convertImageToCSV($filepath);
                    break;
            }
            
            if ($csvPath) {
                // Valider le contenu CSV
                $validation = $this->validateCSVContent($csvPath);
                
                // Mettre à jour le statut
                $this->updateImportStatus($importId, 'converti', $csvPath, $validation);
            } else {
                $this->updateImportStatus($importId, 'erreur', '', ['erreur' => 'Conversion impossible']);
            }
            
        } catch (Exception $e) {
            $this->updateImportStatus($importId, 'erreur', '', ['erreur' => $e->getMessage()]);
        }
    }
    
    private function convertExcelToCSV($excelPath) {
        try {
            $spreadsheet = IOFactory::load($excelPath);
            $worksheet = $spreadsheet->getActiveSheet();
            
            $csvPath = str_replace(['.xlsx', '.xls'], '.csv', $excelPath);
            
            $csvData = [];
            foreach ($worksheet->getRowIterator() as $row) {
                $rowData = [];
                $cellIterator = $row->getCellIterator();
                
                foreach ($cellIterator as $cell) {
                    $rowData[] = $cell->getCalculatedValue();
                }
                $csvData[] = $rowData;
            }
            
            // Écrire le fichier CSV
            $handle = fopen($csvPath, 'w');
            foreach ($csvData as $row) {
                fputcsv($handle, $row, ';'); // Utilisation du point-virgule comme séparateur
            }
            fclose($handle);
            
            return $csvPath;
            
        } catch (Exception $e) {
            error_log("Erreur conversion Excel: " . $e->getMessage());
            return false;
        }
    }
    
    private function convertPDFToCSV($pdfPath) {
        // Utilisation de pdftotext (nécessite l'installation sur le serveur)
        $txtPath = str_replace('.pdf', '.txt', $pdfPath);
        $command = "pdftotext -layout " . escapeshellarg($pdfPath) . " " . escapeshellarg($txtPath);
        
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0 && file_exists($txtPath)) {
            $csvPath = str_replace('.txt', '.csv', $txtPath);
            return $this->parseTextToCSV($txtPath, $csvPath);
        }
        
        return false;
    }
    
    private function convertImageToCSV($imagePath) {
        // Utilisation de Tesseract OCR via API ou commande système
        $txtPath = str_replace(['.jpg', '.jpeg', '.png', '.gif'], '.txt', $imagePath);
        
        // Option 1: Via commande système (nécessite tesseract installé)
        $command = "tesseract " . escapeshellarg($imagePath) . " " . escapeshellarg(str_replace('.txt', '', $txtPath)) . " -l fra";
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0 && file_exists($txtPath)) {
            $csvPath = str_replace('.txt', '.csv', $txtPath);
            return $this->parseTextToCSV($txtPath, $csvPath);
        }
        
        // Option 2: Via API Google Vision (alternative)
        return $this->convertImageWithGoogleVision($imagePath);
    }
    
    private function convertImageWithGoogleVision($imagePath) {
        // Configuration Google Cloud Vision API
        $apiKey = 'YOUR_GOOGLE_VISION_API_KEY'; // À configurer
        $url = 'https://vision.googleapis.com/v1/images:annotate?key=' . $apiKey;
        
        $imageData = base64_encode(file_get_contents($imagePath));
        
        $requestData = [
            'requests' => [
                [
                    'image' => ['content' => $imageData],
                    'features' => [['type' => 'TEXT_DETECTION', 'maxResults' => 1]]
                ]
            ]
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if (isset($result['responses'][0]['textAnnotations'][0]['description'])) {
            $extractedText = $result['responses'][0]['textAnnotations'][0]['description'];
            
            $txtPath = str_replace(['.jpg', '.jpeg', '.png', '.gif'], '.txt', $imagePath);
            file_put_contents($txtPath, $extractedText);
            
            $csvPath = str_replace('.txt', '.csv', $txtPath);
            return $this->parseTextToCSV($txtPath, $csvPath);
        }
        
        return false;
    }
    
    private function parseTextToCSV($txtPath, $csvPath) {
        $content = file_get_contents($txtPath);
        $lines = explode("\n", $content);
        
        $csvData = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line)) {
                // Essayer de détecter les colonnes (espaces multiples, tabulations, etc.)
                $columns = preg_split('/\s{2,}|\t/', $line);
                $csvData[] = array_map('trim', $columns);
            }
        }
        
        // Écrire le CSV
        if (!empty($csvData)) {
            $handle = fopen($csvPath, 'w');
            foreach ($csvData as $row) {
                fputcsv($handle, $row, ';');
            }
            fclose($handle);
            return $csvPath;
        }
        
        return false;
    }
    
    private function validateCSVContent($csvPath) {
        $errors = [];
        $lineCount = 0;
        $expectedHeaders = ['nom', 'prenoms', 'matricule', 'notes']; // Headers attendus
        
        if (($handle = fopen($csvPath, 'r')) !== false) {
            $headers = fgetcsv($handle, 1000, ';');
            $lineCount++;
            
            // Vérifier les en-têtes
            $missingHeaders = array_diff($expectedHeaders, array_map('strtolower', $headers));
            if (!empty($missingHeaders)) {
                $errors[] = "En-têtes manquants: " . implode(', ', $missingHeaders);
            }
            
            // Vérifier chaque ligne
            while (($data = fgetcsv($handle, 1000, ';')) !== false) {
                $lineCount++;
                
                // Vérifications basiques
                if (count($data) !== count($headers)) {
                    $errors[] = "Ligne $lineCount: nombre de colonnes incorrect";
                }
                
                // Vérifier les notes (si présentes)
                if (isset($data[3]) && !empty($data[3])) {
                    $note = floatval($data[3]);
                    if ($note < 0 || $note > 20) {
                        $errors[] = "Ligne $lineCount: note invalide ($note)";
                    }
                }
            }
            fclose($handle);
        }
        
        return [
            'line_count' => $lineCount,
            'errors' => $errors,
            'valid' => empty($errors)
        ];
    }
    
    private function updateImportStatus($importId, $status, $csvPath = '', $validation = []) {
        $query = "UPDATE imports_fichiers 
                  SET statut = :status, chemin_csv = :csv_path, 
                      nombre_lignes = :lines, nombre_erreurs = :errors, 
                      details_erreurs = :error_details 
                  WHERE id = :id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $importId, PDO::PARAM_INT);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':csv_path', $csvPath);
        $stmt->bindParam(':lines', $validation['line_count'] ?? 0, PDO::PARAM_INT);
        $stmt->bindParam(':errors', count($validation['errors'] ?? []), PDO::PARAM_INT);
        $stmt->bindParam(':error_details', json_encode($validation['errors'] ?? []));
        
        $stmt->execute();
    }
    
    private function getUploadErrorMessage($error) {
        switch ($error) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'Fichier trop volumineux';
            case UPLOAD_ERR_PARTIAL:
                return 'Upload interrompu';
            case UPLOAD_ERR_NO_FILE:
                return 'Aucun fichier sélectionné';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Dossier temporaire manquant';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Erreur d\'écriture';
            default:
                return 'Erreur inconnue';
        }
    }
    
    public function getImportStatus($importId) {
        $query = "SELECT * FROM imports_fichiers WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $importId, PDO::PARAM_INT);
        $stmt->execute();
        
        $import = $stmt->fetch();
        if (!$import) {
            $this->sendResponse(null, 404, 'Import non trouvé');
        }
        
        $this->sendResponse($import);
    }
    
    public function importToDatabase($importId) {
        // Récupérer les infos de l'import
        $query = "SELECT * FROM imports_fichiers WHERE id = :id AND statut = 'converti'";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $importId, PDO::PARAM_INT);
        $stmt->execute();
        
        $import = $stmt->fetch();
        if (!$import) {
            $this->sendResponse(null, 404, 'Import non trouvé ou non prêt');
        }
        
        // Lire le fichier CSV et importer les données
        $csvPath = $import['chemin_csv'];
        $imported = 0;
        $errors = [];
        
        if (($handle = fopen($csvPath, 'r')) !== false) {
            $headers = fgetcsv($handle, 1000, ';');
            
            while (($data = fgetcsv($handle, 1000, ';')) !== false) {
                try {
                    $this->importNote($data, $headers);
                    $imported++;
                } catch (Exception $e) {
                    $errors[] = "Erreur ligne " . ($imported + 2) . ": " . $e->getMessage();
                }
            }
            fclose($handle);
        }
        
        // Mettre à jour le statut
        $finalStatus = empty($errors) ? 'importe' : 'erreur';
        $this->updateImportStatus($importId, $finalStatus, $csvPath, [
            'line_count' => $imported,
            'errors' => $errors
        ]);
        
        $this->sendResponse([
            'imported' => $imported,
            'errors' => count($errors),
            'error_details' => $errors
        ], 200, "Import terminé: $imported lignes importées");
    }
    
    private function importNote($data, $headers) {
        // Mapper les données selon les headers
        $noteData = array_combine($headers, $data);
        
        // Logique d'import spécifique aux notes
        // À adapter selon votre structure de données
        $query = "INSERT INTO notes (eleve_id, evaluation_id, note, saisie_par) 
                  VALUES (:eleve_id, :evaluation_id, :note, :saisie_par)";
        
        // Ici vous devriez avoir la logique pour:
        // 1. Trouver l'élève par son matricule
        // 2. Associer à la bonne évaluation
        // 3. Insérer la note
    }
}
?>