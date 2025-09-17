<?php
class BackupService {
    private $db;
    private $logger;
    private $backupPath;
    
    public function __construct($db) {
        $this->db = $db;
        $this->logger = Logger::getInstance();
        $this->backupPath = __DIR__ . '/../backups/';
        
        if (!is_dir($this->backupPath)) {
            mkdir($this->backupPath, 0755, true);
        }
    }
    
    public function createDatabaseBackup($compress = true) {
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "backup_db_{$timestamp}.sql";
        $filepath = $this->backupPath . $filename;
        
        try {
            // Configuration de la base de données
            $host = 'localhost';
            $dbname = 'bulletins_system';
            $username = 'root';
            $password = '';
            
            // Commande mysqldump
            $command = "mysqldump --host={$host} --user={$username} --password={$password} " .
                      "--single-transaction --routines --triggers {$dbname} > {$filepath}";
            
            $output = [];
            $returnVar = 0;
            exec($command, $output, $returnVar);
            
            if ($returnVar === 0 && file_exists($filepath)) {
                // Compression si demandée
                if ($compress) {
                    $compressedFile = $filepath . '.gz';
                    $command = "gzip {$filepath}";
                    exec($command);
                    $filepath = $compressedFile;
                    $filename .= '.gz';
                }
                
                $fileSize = filesize($filepath);
                
                $this->logger->info("Database backup created: {$filename}", [
                    'file_size' => $fileSize,
                    'path' => $filepath
                ], 'backup');
                
                // Nettoyer les anciens backups
                $this->cleanOldBackups();
                
                return [
                    'success' => true,
                    'filename' => $filename,
                    'filepath' => $filepath,
                    'size' => $fileSize
                ];
                
            } else {
                throw new Exception('mysqldump command failed');
            }
            
        } catch (Exception $e) {
            $this->logger->error("Database backup failed: " . $e->getMessage(), [], 'backup');
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    public function createFilesBackup($directories = null) {
        if ($directories === null) {
            $directories = [
                'uploads' => __DIR__ . '/../uploads/',
                'bulletins' => __DIR__ . '/../bulletins/',
                'config' => __DIR__ . '/../config/'
            ];
        }
        
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "backup_files_{$timestamp}.tar.gz";
        $filepath = $this->backupPath . $filename;
        
        try {
            // Créer l'archive tar
            $command = "tar -czf {$filepath}";
            
            foreach ($directories as $name => $path) {
                if (is_dir($path)) {
                    $command .= " -C " . dirname($path) . " " . basename($path);
                }
            }
            
            $output = [];
            $returnVar = 0;
            exec($command, $output, $returnVar);
            
            if ($returnVar === 0 && file_exists($filepath)) {
                $fileSize = filesize($filepath);
                
                $this->logger->info("Files backup created: {$filename}", [
                    'file_size' => $fileSize,
                    'directories' => array_keys($directories)
                ], 'backup');
                
                return [
                    'success' => true,
                    'filename' => $filename,
                    'filepath' => $filepath,
                    'size' => $fileSize
                ];
                
            } else {
                throw new Exception('tar command failed');
            }
            
        } catch (Exception $e) {
            $this->logger->error("Files backup failed: " . $e->getMessage(), [], 'backup');
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    public function createFullBackup() {
        $results = [];
        
        // Backup base de données
        $results['database'] = $this->createDatabaseBackup();
        
        // Backup fichiers
        $results['files'] = $this->createFilesBackup();
        
        $success = $results['database']['success'] && $results['files']['success'];
        
        if ($success) {
            $this->logger->info("Full backup completed successfully", $results, 'backup');
        } else {
            $this->logger->error("Full backup failed", $results, 'backup');
        }
        
        return [
            'success' => $success,
            'results' => $results
        ];
    }
    
    private function cleanOldBackups($keepDays = 30) {
        $cutoffTime = time() - ($keepDays * 24 * 60 * 60);
        
        $files = glob($this->backupPath . 'backup_*');
        $deleted = 0;
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                if (unlink($file)) {
                    $deleted++;
                }
            }
        }
        
        if ($deleted > 0) {
            $this->logger->info("Cleaned up {$deleted} old backup files", [], 'backup');
        }
    }
    
    public function listBackups() {
        $backups = [];
        $files = glob($this->backupPath . 'backup_*');
        
        foreach ($files as $file) {
            $backups[] = [
                'filename' => basename($file),
                'size' => filesize($file),
                'created_at' => date('Y-m-d H:i:s', filemtime($file)),
                'type' => strpos($file, '_db_') !== false ? 'database' : 'files'
            ];
        }
        
        // Trier par date de création (plus récent d'abord)
        usort($backups, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        return $backups;
    }
}
?>