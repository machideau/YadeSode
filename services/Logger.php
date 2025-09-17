<?php
class Logger {
    private static $instance = null;
    private $logPath;
    private $maxFileSize = 10 * 1024 * 1024; // 10MB
    
    private function __construct() {
        $this->logPath = __DIR__ . '/../logs/';
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0755, true);
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Logger();
        }
        return self::$instance;
    }
    
    public function info($message, $context = [], $category = 'general') {
        $this->log('INFO', $message, $context, $category);
    }
    
    public function warning($message, $context = [], $category = 'general') {
        $this->log('WARNING', $message, $context, $category);
    }
    
    public function error($message, $context = [], $category = 'general') {
        $this->log('ERROR', $message, $context, $category);
    }
    
    public function debug($message, $context = [], $category = 'general') {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            $this->log('DEBUG', $message, $context, $category);
        }
    }
    
    private function log($level, $message, $context, $category) {
        $filename = $this->logPath . $category . '_' . date('Y-m-d') . '.log';
        
        // Rotation si fichier trop volumineux
        if (file_exists($filename) && filesize($filename) > $this->maxFileSize) {
            $this->rotateLog($filename);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
        $userInfo = $this->getUserContext();
        
        $logEntry = "[{$timestamp}] [{$level}]{$userInfo} {$message}{$contextStr}" . PHP_EOL;
        
        file_put_contents($filename, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    private function getUserContext() {
        if (isset($_SESSION['user_id'])) {
            return " [User: {$_SESSION['user_id']}]";
        }
        return " [IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "]";
    }
    
    private function rotateLog($filename) {
        $backup = $filename . '.' . time();
        rename($filename, $backup);
        
        // Garder seulement les 5 derniers fichiers
        $pattern = dirname($filename) . '/' . basename($filename) . '.*';
        $files = glob($pattern);
        
        if (count($files) > 5) {
            usort($files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            // Supprimer les plus anciens
            for ($i = 0; $i < count($files) - 5; $i++) {
                unlink($files[$i]);
            }
        }
    }
    
    public function getRecentLogs($category = 'general', $lines = 100) {
        $filename = $this->logPath . $category . '_' . date('Y-m-d') . '.log';
        
        if (!file_exists($filename)) {
            return [];
        }
        
        $logs = [];
        $handle = fopen($filename, 'r');
        
        if ($handle) {
            // Lire depuis la fin
            fseek($handle, -1, SEEK_END);
            $pos = ftell($handle);
            $line = '';
            $lineCount = 0;
            
            while ($pos >= 0 && $lineCount < $lines) {
                fseek($handle, $pos);
                $char = fgetc($handle);
                
                if ($char === "\n" || $pos === 0) {
                    if (!empty(trim($line))) {
                        $logs[] = trim($line);
                        $lineCount++;
                    }
                    $line = '';
                } else {
                    $line = $char . $line;
                }
                
                $pos--;
            }
            
            fclose($handle);
        }
        
        return array_reverse($logs);
    }
}
?>