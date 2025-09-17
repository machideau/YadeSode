<?php
class MonitoringService {
    private $db;
    private $logger;
    
    public function __construct($db) {
        $this->db = $db;
        $this->logger = Logger::getInstance();
    }
    
    public function checkSystemHealth() {
        $health = [
            'status' => 'healthy',
            'checks' => [],
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // Vérification base de données
        $health['checks']['database'] = $this->checkDatabase();
        
        // Vérification espace disque
        $health['checks']['disk_space'] = $this->checkDiskSpace();
        
        // Vérification dossiers critiques
        $health['checks']['directories'] = $this->checkDirectories();
        
        // Vérification services externes
        $health['checks']['external_services'] = $this->checkExternalServices();
        
        // Vérification performance
        $health['checks']['performance'] = $this->checkPerformance();
        
        // Déterminer le statut global
        $hasError = false;
        foreach ($health['checks'] as $check) {
            if ($check['status'] === 'error') {
                $hasError = true;
                break;
            }
        }
        
        $health['status'] = $hasError ? 'unhealthy' : 'healthy';
        
        // Log si problème
        if ($hasError) {
            $this->logger->error('System health check failed', $health, 'monitoring');
        }
        
        return $health;
    }
    
    private function checkDatabase() {
        try {
            $start = microtime(true);
            $this->db->query('SELECT 1')->fetch();
            $responseTime = round((microtime(true) - $start) * 1000, 2);
            
            // Vérifier les tables critiques
            $tables = ['users', 'classes', 'eleves', 'notes', 'bulletins'];
            foreach ($tables as $table) {
                $stmt = $this->db->query("SELECT COUNT(*) FROM {$table}");
                $stmt->fetch();
            }
            
            return [
                'status' => 'ok',
                'response_time_ms' => $responseTime,
                'message' => 'Database connection healthy'
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Database connection failed: ' . $e->getMessage()
            ];
        }
    }
    
    private function checkDiskSpace() {
        $uploadDir = __DIR__ . '/../uploads/';
        $bulletinDir = __DIR__ . '/../bulletins/';
        $logDir = __DIR__ . '/../logs/';
        
        $checks = [];
        
        foreach ([$uploadDir, $bulletinDir, $logDir] as $dir) {
            if (is_dir($dir)) {
                $freeSpace = disk_free_space($dir);
                $totalSpace = disk_total_space($dir);
                $usedPercent = round((1 - ($freeSpace / $totalSpace)) * 100, 2);
                
                $status = $usedPercent > 90 ? 'error' : ($usedPercent > 80 ? 'warning' : 'ok');
                
                $checks[basename($dir)] = [
                    'status' => $status,
                    'used_percent' => $usedPercent,
                    'free_space_mb' => round($freeSpace / 1024 / 1024, 2)
                ];
            }
        }
        
        return $checks;
    }
    
    private function checkDirectories() {
        $criticalDirs = [
            'uploads' => __DIR__ . '/../uploads/',
            'bulletins' => __DIR__ . '/../bulletins/',
            'logs' => __DIR__ . '/../logs/',
            'temp' => sys_get_temp_dir()
        ];
        
        $checks = [];
        
        foreach ($criticalDirs as $name => $path) {
            $status = 'ok';
            $message = 'Directory accessible';
            
            if (!is_dir($path)) {
                $status = 'error';
                $message = 'Directory does not exist';
            } elseif (!is_readable($path)) {
                $status = 'error';
                $message = 'Directory not readable';
            } elseif (!is_writable($path)) {
                $status = 'error';
                $message = 'Directory not writable';
            }
            
            $checks[$name] = [
                'status' => $status,
                'message' => $message,
                'path' => $path
            ];
        }
        
        return $checks;
    }
    
    private function checkExternalServices() {
        $services = [];
        
        // Test Tesseract OCR
        $tesseractCheck = shell_exec('which tesseract 2>/dev/null');
        $services['tesseract'] = [
            'status' => $tesseractCheck ? 'ok' : 'warning',
            'message' => $tesseractCheck ? 'Tesseract available' : 'Tesseract not found',
            'required' => false
        ];
        
        // Test pdftotext
        $pdftotextCheck = shell_exec('which pdftotext 2>/dev/null');
        $services['pdftotext'] = [
            'status' => $pdftotextCheck ? 'ok' : 'warning',
            'message' => $pdftotextCheck ? 'pdftotext available' : 'pdftotext not found',
            'required' => false
        ];
        
        // Test SMTP (si configuré)
        if (defined('SMTP_HOST')) {
            $services['smtp'] = $this->checkSMTP();
        }
        
        return $services;
    }
    
    private function checkSMTP() {
        try {
            $connection = fsockopen(SMTP_HOST, SMTP_PORT ?? 587, $errno, $errstr, 10);
            if ($connection) {
                fclose($connection);
                return [
                    'status' => 'ok',
                    'message' => 'SMTP server reachable'
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => "SMTP connection failed: $errstr ($errno)"
                ];
            }
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'SMTP check failed: ' . $e->getMessage()
            ];
        }
    }
    
    private function checkPerformance() {
        $metrics = [];
        
        // Memory usage
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = ini_get('memory_limit');
        $memoryLimitBytes = $this->convertToBytes($memoryLimit);
        
        $memoryPercent = round(($memoryUsage / $memoryLimitBytes) * 100, 2);
        
        $metrics['memory'] = [
            'status' => $memoryPercent > 80 ? 'warning' : 'ok',
            'usage_mb' => round($memoryUsage / 1024 / 1024, 2),
            'limit_mb' => round($memoryLimitBytes / 1024 / 1024, 2),
            'usage_percent' => $memoryPercent
        ];
        
        // Database performance
        $start = microtime(true);
        $stmt = $this->db->query('SELECT COUNT(*) as total FROM users');
        $stmt->fetch();
        $dbResponseTime = round((microtime(true) - $start) * 1000, 2);
        
        $metrics['database_response'] = [
            'status' => $dbResponseTime > 1000 ? 'warning' : 'ok',
            'response_time_ms' => $dbResponseTime
        ];
        
        return $metrics;
    }
    
    private function convertToBytes($value) {
        $unit = strtolower(substr($value, -1));
        $value = (int) $value;
        
        switch ($unit) {
            case 'g': $value *= 1024 * 1024 * 1024; break;
            case 'm': $value *= 1024 * 1024; break;
            case 'k': $value *= 1024; break;
        }
        
        return $value;
    }
    
    public function getSystemMetrics($period = '24h') {
        $metrics = [];
        
        // Statistiques d'utilisation
        $metrics['requests'] = $this->getRequestStats($period);
        $metrics['errors'] = $this->getErrorStats($period);
        $metrics['uploads'] = $this->getUploadStats($period);
        $metrics['bulletins'] = $this->getBulletinStats($period);
        
        return $metrics;
    }
    
    private function getRequestStats($period) {
        // Analyser les logs pour compter les requêtes
        $logFile = $this->logger->getRecentLogs('api', 1000);
        $requests = 0;
        $errors = 0;
        
        $cutoff = strtotime("-$period");
        
        foreach ($logFile as $line) {
            if (preg_match('/\[([\d\-\s:]+)\]/', $line, $matches)) {
                $timestamp = strtotime($matches[1]);
                if ($timestamp > $cutoff) {
                    $requests++;
                    if (strpos($line, '[ERROR]') !== false) {
                        $errors++;
                    }
                }
            }
        }
        
        return [
            'total_requests' => $requests,
            'error_requests' => $errors,
            'success_rate' => $requests > 0 ? round((($requests - $errors) / $requests) * 100, 2) : 100
        ];
    }
    
    private function getErrorStats($period) {
        $hours = $this->periodToHours($period);
        
        $query = "SELECT 
                    COUNT(*) as total_errors,
                    COUNT(CASE WHEN niveau = 'ERROR' THEN 1 END) as critical_errors
                  FROM system_logs 
                  WHERE created_at >= DATE_SUB(NOW(), INTERVAL :hours HOUR)";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':hours', $hours, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch();
        } catch (Exception $e) {
            return ['total_errors' => 0, 'critical_errors' => 0];
        }
    }
    
    private function getUploadStats($period) {
        $hours = $this->periodToHours($period);
        
        $query = "SELECT 
                    COUNT(*) as total_uploads,
                    COUNT(CASE WHEN statut = 'importe' THEN 1 END) as successful_imports,
                    COUNT(CASE WHEN statut = 'erreur' THEN 1 END) as failed_imports
                  FROM imports_fichiers 
                  WHERE created_at >= DATE_SUB(NOW(), INTERVAL :hours HOUR)";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':hours', $hours, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch();
        } catch (Exception $e) {
            return ['total_uploads' => 0, 'successful_imports' => 0, 'failed_imports' => 0];
        }
    }
    
    private function getBulletinStats($period) {
        $hours = $this->periodToHours($period);
        
        $query = "SELECT 
                    COUNT(*) as bulletins_generated,
                    COUNT(CASE WHEN statut = 'valide' THEN 1 END) as bulletins_validated
                  FROM bulletins 
                  WHERE genere_le >= DATE_SUB(NOW(), INTERVAL :hours HOUR)";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':hours', $hours, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch();
        } catch (Exception $e) {
            return ['bulletins_generated' => 0, 'bulletins_validated' => 0];
        }
    }
    
    private function periodToHours($period) {
        $periods = [
            '1h' => 1,
            '24h' => 24,
            '7d' => 168,
            '30d' => 720
        ];
        
        return $periods[$period] ?? 24;
    }
}
?>