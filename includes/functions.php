<?php
// includes/functions.php

// Prüft ob die Installation durchgeführt wurde
function checkInstallation() {
    if (!file_exists(__DIR__ . '/../config/config.php')) {
        header('Location: /install/');
        exit;
    }
}

// Formatiert ein Datum
function formatDate($date) {
    return date('d.m.Y', strtotime($date));
}

// Formatiert eine Uhrzeit
function formatTime($time) {
    return date('H:i:s', strtotime($time));
}

// Holt die System-Konfiguration
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

// Speichert einen Konfigurationswert
function saveSystemConfig($db, $key, $value) {
    $stmt = $db->prepare("
        INSERT INTO system_config (config_key, config_value) 
        VALUES (?, ?) 
        ON DUPLICATE KEY UPDATE config_value = ?
    ");
    $stmt->bind_param('sss', $key, $value, $value);
    return $stmt->execute();
}

// Überprüft den Backup-Status basierend auf Suchbegriffen
function determineBackupStatus($db, $content) {
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
    
    // Standard-Status zurückgeben (niedrigste Priorität)
    $result = $db->query("SELECT name FROM backup_status ORDER BY priority ASC LIMIT 1");
    $defaultStatus = $result->fetch_assoc();
    return $defaultStatus['name'];
}

// Validiert eine E-Mail-Adresse
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Generiert eine eindeutige Kundennummer
function generateCustomerNumber($db) {
    do {
        $number = 'KD-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $result = $db->query("SELECT id FROM customers WHERE customer_number = '$number'");
    } while ($result->num_rows > 0);
    
    return $number;
}

// Bereinigt alte Backup-Ergebnisse
function cleanupOldResults($db, $daysToKeep = 30) {
    $db->query("
        DELETE FROM backup_results 
        WHERE date < DATE_SUB(CURRENT_DATE, INTERVAL $daysToKeep DAY)
    ");
}

// Prüft ob eine Datei eine gültige E-Mail ist
function isValidEmailFile($content) {
    return strpos($content, 'From:') !== false && strpos($content, 'Date:') !== false;
}

// Loggt Fehler
function logError($message) {
    $logFile = __DIR__ . '/../logs/error.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    
    if (!is_dir(dirname($logFile))) {
        mkdir(dirname($logFile), 0755, true);
    }
    
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}