<?php
class BackupProcessor {
    private $db;
    private $config;
    private $backup_types;
    private $customer_emails;
    private $backup_jobs;
    private $mail_server;
    private $timezone = 'Europe/Berlin';

    public function __construct() {
        try {
            $this->db = Database::getInstance()->getConnection();
            $this->loadConfiguration();
            $this->loadBackupTypes();
            $this->loadCustomerEmails();
            $this->loadBackupJobs();
            $this->setupMailServer();
        } catch (Exception $e) {
            $this->logError("Initialisierungsfehler: " . $e->getMessage());
            throw $e;
        }
    }

    private function loadConfiguration() {
        // Lade Systemkonfiguration aus der Datenbank
        $result = $this->db->query("SELECT config_key, config_value FROM system_config");
        $this->config = [];
        while ($row = $result->fetch_assoc()) {
            $this->config[$row['config_key']] = $row['config_value'];
        }
        
        // Setze Zeitzone
        date_default_timezone_set($this->timezone);
    }

    private function loadBackupTypes() {
        $result = $this->db->query("SELECT * FROM backup_types");
        $this->backup_types = [];
        while ($row = $result->fetch_assoc()) {
            $this->backup_types[$row['id']] = $row;
        }
    }

    private function loadCustomerEmails() {
        $result = $this->db->query("SELECT email, customer_id FROM customer_emails");
        $this->customer_emails = [];
        while ($row = $result->fetch_assoc()) {
            $this->customer_emails[$row['email']] = $row['customer_id'];
        }
    }

    private function loadBackupJobs() {
        $result = $this->db->query(
            "SELECT bj.*, bt.identifier_type 
             FROM backup_jobs bj 
             JOIN backup_types bt ON bj.backup_type_id = bt.id"
        );
        $this->backup_jobs = [];
        while ($row = $result->fetch_assoc()) {
            $this->backup_jobs[$row['id']] = $row;
        }
    }

    private function setupMailServer() {
        try {
            $server = $this->config['mail']['ssl'] ? 
                     'ssl://' . $this->config['mail']['server'] : 
                     $this->config['mail']['server'];
    
            $this->mail_server = new POP3();
            $this->mail_server->connect($server, $this->config['mail']['port']);
            $this->mail_server->login(
                $this->config['mail']['username'],
                $this->config['mail']['password']
            );
        } catch (Exception $e) {
            $this->logError("Mail-Server Verbindungsfehler: " . $e->getMessage());
            throw $e;
        }
    }

    public function processEmails() {
        $processed = 0;
        $errors = 0;

        try {
            $num_messages = $this->mail_server->numMessages();
            
            for ($i = 1; $i <= $num_messages; $i++) {
                try {
                    $email_content = $this->mail_server->retrieveMessage($i);
                    $email_data = $this->parseEmail($email_content);
                    
                    if (!$email_data) {
                        $this->logError("Mail $i konnte nicht geparst werden");
                        $errors++;
                        continue;
                    }

                    $customer_id = $this->findCustomerId($email_data);
                    if (!$customer_id) {
                        $this->logError("Kein Kunde für Mail $i gefunden (From: {$email_data['from']})");
                        continue;
                    }

                    $job_id = $this->findBackupJob($customer_id, $email_data);
                    if (!$job_id) {
                        $this->logError("Kein Backup-Job für Kunde $customer_id in Mail $i gefunden");
                        continue;
                    }

                    $status = $this->determineBackupStatus($email_data['body']);
                    $this->saveBackupResult($job_id, $status, $email_data);
                    $processed++;
                    
                    if ($this->config['mail_delete_after_processing'] === '1') {
                        $this->mail_server->deleteMessage($i);
                    }

                } catch (Exception $e) {
                    $this->logError("Fehler bei Mail $i: " . $e->getMessage());
                    $errors++;
                    continue;
                }
            }
        } finally {
            $this->mail_server->disconnect();
            $this->logError("Verarbeitung beendet. Verarbeitet: $processed, Fehler: $errors");
        }
    }

    private function parseEmail($content) {
        try {
            $headers = imap_rfc822_parse_headers($content);
            if (!$headers) {
                throw new Exception("Ungültige E-Mail-Header");
            }

            $body = preg_replace('/^.*?\r\n\r\n/s', '', $content);
            if (!$body) {
                throw new Exception("Kein E-Mail-Body gefunden");
            }
            
            $from = $headers->from[0]->mailbox . '@' . $headers->from[0]->host;
            $subject = $this->decodeSubject($headers->subject);
            $date = strtotime($headers->date);
            
            if (!$date) {
                throw new Exception("Ungültiges Datum");
            }

            return [
                'from' => $from,
                'subject' => $subject,
                'date' => date('Y-m-d', $date),
                'timestamp' => date('Y-m-d H:i:s', $date),
                'body' => $body
            ];
        } catch (Exception $e) {
            $this->logError("E-Mail Parse-Fehler: " . $e->getMessage());
            return null;
        }
    }

    // [Restliche Methoden bleiben unverändert wie im alten Code]

    private function logError($message) {
        $logfile = __DIR__ . '/../logs/error.log';
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[$timestamp] $message\n";
        
        if (!is_dir(dirname($logfile))) {
            mkdir(dirname($logfile), 0755, true);
        }
        
        file_put_contents($logfile, $log_message, FILE_APPEND);
    }
}