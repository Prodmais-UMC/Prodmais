<?php

class LogService {
    private $db;
    private $config;
    
    public function __construct($config = null) {
        $this->config = $config;
        $logPath = $config['data_paths']['logs'] ?? __DIR__.'/../../../data/logs.sqlite';
        
        // Ensure directory exists
        $dir = dirname($logPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $this->db = new \SQLite3($logPath);

        // Compatibilidade com volumes Docker no Windows — evita lock I/O error
        $this->db->exec("PRAGMA journal_mode=WAL");
        $this->db->exec("PRAGMA synchronous=NORMAL");
        $this->db->exec("PRAGMA busy_timeout=5000");

        // Check if table exists and get its structure
        $result = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='logs'");
        $tableExists = $result->fetchArray();
        
        if (!$tableExists) {
            // Create new table with complete structure
            $this->db->exec("CREATE TABLE logs (
                id INTEGER PRIMARY KEY, 
                level TEXT, 
                message TEXT, 
                context TEXT,
                user TEXT, 
                action TEXT, 
                timestamp TEXT
            )");
        } else {
            // Check if level column exists
            $result = $this->db->query("PRAGMA table_info(logs)");
            $hasLevelColumn = false;
            while ($row = $result->fetchArray()) {
                if ($row['name'] === 'level') {
                    $hasLevelColumn = true;
                    break;
                }
            }
            
            if (!$hasLevelColumn) {
                // Migrate old table to new structure
                $this->db->exec("ALTER TABLE logs ADD COLUMN level TEXT");
                $this->db->exec("ALTER TABLE logs ADD COLUMN message TEXT");
                $this->db->exec("ALTER TABLE logs ADD COLUMN context TEXT");
                // Update existing records
                $this->db->exec("UPDATE logs SET level = 'INFO', message = action WHERE level IS NULL");
            }
        }
    }
    
    /**
     * Log with level, message and context (LGPD compatible)
     */
    public function log($level, $message, $context = []) {
        $stmt = $this->db->prepare("INSERT INTO logs (level, message, context, timestamp) VALUES (?, ?, ?, ?)");
        $stmt->bindValue(1, $level);
        $stmt->bindValue(2, $message);
        $stmt->bindValue(3, json_encode($context));
        $stmt->bindValue(4, date('c'));
        $stmt->execute();
    }
    
    /**
     * Legacy method for backward compatibility
     */
    public function logAction($user, $action) {
        $stmt = $this->db->prepare("INSERT INTO logs (user, action, timestamp) VALUES (?, ?, ?)");
        $stmt->bindValue(1, $user);
        $stmt->bindValue(2, $action);
        $stmt->bindValue(3, date('c'));
        $stmt->execute();
    }
    
    public function expungeOld($days = 365): int {
        $limit = date('c', strtotime("-$days days"));
        $stmt = $this->db->prepare("DELETE FROM logs WHERE timestamp < :limit");
        $stmt->bindValue(':limit', $limit, SQLITE3_TEXT);
        $stmt->execute();
        return $this->db->changes();
    }

    public function getLogs($limit = 100) {
        $stmt = $this->db->prepare("SELECT level, message, context, user, action, timestamp FROM logs ORDER BY timestamp DESC LIMIT :limit");
        $stmt->bindValue(':limit', (int) $limit, SQLITE3_INTEGER);
        $results = $stmt->execute();
        $logs = [];
        while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
            if ($row['context']) {
                $row['context'] = json_decode($row['context'], true);
            }
            $logs[] = $row;
        }
        return $logs;
    }
}
