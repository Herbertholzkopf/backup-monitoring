<?php
// Installation prüfen
function checkInstallation() {
    if (!file_exists(__DIR__ . '/../config/config.php')) {
        header('Location: /install/');
        exit;
    }
}

// Datums- und Zeitformatierung
function formatDate($date) {
    return date('d.m.Y', strtotime($date));
}

function formatTime($time) {
    return date('H:i:s', strtotime($time));
}

// Systemkonfiguration
function getSystemConfig($db, $key = null) {
    if ($key) {
        $stmt = $db->prepare("SELECT config_value FROM system_config WHERE config_key = ?");
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row ? $row['config_value'] : null;
    }
    
    $result = $db->query("SELECT config_key, config_value FROM system_config");
    $config = [];
    while ($row = $result->fetch_assoc()) {
        $config[$row['config_key']] = $row['config_value'];
    }
    return $config;
}

function saveSystemConfig($db, $key, $value) {
    try {
        $stmt = $db->prepare("
            INSERT INTO system_config (config_key, config_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE config_value = ?
        ");
        $stmt->bind_param('sss', $key, $value, $value);
        return $stmt->execute();
    } catch (Exception $e) {
        logError("Fehler beim Speichern der Konfiguration: " . $e->getMessage());
        return false;
    }
}

// Backup-Status
function determineBackupStatus($db, $content) {
    try {
        $content = strtolower($content);
        $result = $db->query("SELECT * FROM backup_status ORDER BY priority DESC");
        
        while ($status = $result->fetch_assoc()) {
            $searchStrings = explode(',', $status['search_strings']);
            foreach ($searchStrings as $string) {
                $string = trim(strtolower($string));
                if ($string && strpos($content, $string) !== false) {
                    return $status['name'];
                }
            }
        }
        
        $result = $db->query("SELECT name FROM backup_status ORDER BY priority ASC LIMIT 1");
        $defaultStatus = $result->fetch_assoc();
        return $defaultStatus['name'];
    } catch (Exception $e) {
        logError("Fehler bei der Status-Bestimmung: " . $e->getMessage());
        return 'unknown';
    }
}

// Validierung
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function isValidEmailFile($content) {
    return strpos($content, 'From:') !== false && 
           strpos($content, 'Date:') !== false;
}

// Kundennummer-Generator
function generateCustomerNumber($db) {
    do {
        $number = 'KD-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $result = $db->query("SELECT id FROM customers WHERE customer_number = '" . 
                            mysqli_real_escape_string($db, $number) . "'");
    } while ($result->num_rows > 0);
    
    return $number;
}

// Datenpflege
function cleanupOldResults($db, $daysToKeep = 30) {
    try {
        $db->query("
            DELETE FROM backup_results 
            WHERE date < DATE_SUB(CURRENT_DATE, INTERVAL $daysToKeep DAY)
        ");
        logError("Alte Ergebnisse bereinigt (älter als $daysToKeep Tage)");
        return true;
    } catch (Exception $e) {
        logError("Fehler bei der Bereinigung: " . $e->getMessage());
        return false;
    }
}

// Logging
function logError($message, $type = 'error') {
    $logFile = __DIR__ . "/../logs/{$type}.log";
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    
    try {
        if (!is_dir(dirname($logFile))) {
            mkdir(dirname($logFile), 0755, true);
        }
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    } catch (Exception $e) {
        error_log("Logging fehlgeschlagen: " . $e->getMessage());
    }
}

// Sicherheit
function secureString($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function getClientIP() {
    return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
}

// Debug-Hilfe
function debug($var, $die = false) {
    if (getSystemConfig(Database::getInstance()->getConnection(), 'debug_mode')) {
        echo '<pre>';
        var_dump($var);
        echo '</pre>';
        if ($die) die();
    }
}
