<?php
// process_mails.php
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/BackupProcessor.php';
require_once __DIR__ . '/includes/functions.php';

try {
    // Prüfe Installation
    checkInstallation();
    
    // Initialisiere Processor
    $processor = new BackupProcessor();
    
    // Verarbeite E-Mails
    $processor->processEmails();
    
    // Optional: Alte Ergebnisse bereinigen
    cleanupOldResults(Database::getInstance()->getConnection());
    
} catch (Exception $e) {
    // Fehler loggen
    $logfile = __DIR__ . '/logs/error.log';
    $timestamp = date('Y-m-d H:i:s');
    $message = "[$timestamp] Fehler bei der Mail-Verarbeitung: " . $e->getMessage() . "\n";
    file_put_contents($logfile, $message, FILE_APPEND);
    
    // Bei kritischen Fehlern E-Mail an Admin
    $admin_email = getSystemConfig(Database::getInstance()->getConnection(), 'admin_email');
    if ($admin_email) {
        mail(
            $admin_email,
            'Backup-Monitor: Fehler bei der Mail-Verarbeitung',
            $message,
            'From: backup-monitor@' . php_uname('n')
        );
    }
    
    exit(1);
}

exit(0);