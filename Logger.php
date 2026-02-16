<?php
/**
 * Simple logger class for tracking sync operations
 */

class Logger {
    private $logFile;
    
    public function __construct($logFile) {
        $this->logFile = $logFile;
        
        // Ensure log directory exists
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    public function info($message, $context = []) {
        $this->log('INFO', $message, $context);
    }
    
    public function error($message, $context = []) {
        $this->log('ERROR', $message, $context);
    }
    
    public function success($message, $context = []) {
        $this->log('SUCCESS', $message, $context);
    }
    
    private function log($level, $message, $context = []) {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | ' . json_encode($context) : '';
        $logMessage = "[{$timestamp}] [{$level}] {$message}{$contextStr}\n";
        
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
        
        // Also echo to console
        echo $logMessage;
    }
}
