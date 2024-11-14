<?php
class BackupProcessor {
    private $db;
    private $config;
    private $backup_types;
    private $customer_emails;
    private $backup_jobs;
    private $mail_server;

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
        $result = $this->db->query("SELECT config_key, config_value FROM system_config");
        $this->config = [];
        while ($row = $result->fetch_assoc()) {
            $this->config[$row['config_key']] = $row['config_value'];
        }
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
            if ($this->config['mail_ssl']) {
                $this->mail_server = new POP3;
                $this->mail_server->connect(
                    'ssl://' . $this->config['mail_server'], 
                    $this->config['mail_port']
                );
            } else {
                $this->mail_server = new POP3;
                $this->mail_server->connect(
                    $this->config['mail_server'], 
                    $this->config['mail_port']
                );
            }
            
            $this->mail_server->login(
                $this->config['mail_user'], 
                $this->config['mail_password']
            );
        } catch (Exception $e) {
            $this->logError("Mail-Server Verbindungsfehler: " . $e->getMessage());
            throw $e;
        }
    }

    public function processEmails() {
        try {
            $num_messages = $this->mail_server->numMessages();
            
            for ($i = 1; $i <= $num_messages; $i++) {
                try {
                    $email_content = $this->mail_server->retrieveMessage($i);
                    $email_data = $this->parseEmail($email_content);
                    
                    if ($email_data) {
                        $customer_id = $this->findCustomerId($email_data);
                        if ($customer_id) {
                            $job_id = $this->findBackupJob($customer_id, $email_data);
                            if ($job_id) {
                                $status = $this->determineBackupStatus($email_data['body']);
                                $this->saveBackupResult($job_id, $status, $email_data);
                                
                                // Mail löschen wenn konfiguriert
                                if ($this->config['mail_delete_after_processing']) {
                                    $this->mail_server->deleteMessage($i);
                                }
                            }
                        }
                    }
                } catch (Exception $e) {
                    $this->logError("Fehler bei Mail $i: " . $e->getMessage());
                    continue;
                }
            }
        } finally {
            $this->mail_server->disconnect();
        }
    }

    private function parseEmail($content) {
        try {
            $headers = imap_rfc822_parse_headers($content);
            $body = preg_replace('/^.*?\r\n\r\n/s', '', $content);
            
            // Zeitzone setzen
            date_default_timezone_set('Europe/Berlin');
            
            return [
                'from' => $headers->from[0]->mailbox . '@' . $headers->from[0]->host,
                'subject' => $this->decodeSubject($headers->subject),
                'date' => date('Y-m-d', strtotime($headers->date)),
                'timestamp' => date('Y-m-d H:i:s', strtotime($headers->date)),
                'body' => $body
            ];
        } catch (Exception $e) {
            $this->logError("E-Mail Parse-Fehler: " . $e->getMessage());
            return null;
        }
    }

    private function decodeSubject($subject) {
        $elements = imap_mime_header_decode($subject);
        $decoded = '';
        foreach ($elements as $element) {
            if ($element->charset === 'default') {
                $decoded .= $element->text;
            } else {
                $decoded .= iconv($element->charset, 'UTF-8', $element->text);
            }
        }
        return $decoded;
    }

    private function findCustomerId($email_data) {
        $from_address = $email_data['from'];
        return isset($this->customer_emails[$from_address]) ? 
               $this->customer_emails[$from_address] : null;
    }

    private function findBackupJob($customer_id, $email_data) {
        foreach ($this->backup_jobs as $job) {
            if ($job['customer_id'] != $customer_id) {
                continue;
            }

            // Je nach Backup-Typ verschiedene Suchstrategien
            if ($job['identifier_type'] === 'email') {
                // Suche nach Hostname im Subject oder Body
                if (stripos($email_data['subject'], $job['hostname']) !== false ||
                    stripos($email_data['body'], $job['hostname']) !== false) {
                    return $job['id'];
                }
            } else if ($job['identifier_type'] === 'hostname') {
                // Explizite Suche nach Hostnamen im Body
                $pattern = '/\b' . preg_quote($job['hostname'], '/') . '\b/i';
                if (preg_match($pattern, $email_data['body'])) {
                    return $job['id'];
                }
            }
        }
        return null;
    }

    private function determineBackupStatus($content) {
        $content = strtolower($content);
        
        // Status nach Priorität prüfen
        $result = $this->db->query(
            "SELECT name, search_strings FROM backup_status ORDER BY priority DESC"
        );
        
        while ($status = $result->fetch_assoc()) {
            $search_strings = explode(',', $status['search_strings']);
            foreach ($search_strings as $string) {
                $string = trim(strtolower($string));
                if ($string && strpos($content, $string) !== false) {
                    return $status['name'];
                }
            }
        }
        
        // Standard-Status (niedrigste Priorität) zurückgeben
        $result = $this->db->query(
            "SELECT name FROM backup_status ORDER BY priority ASC LIMIT 1"
        );
        $default_status = $result->fetch_assoc();
        return $default_status['name'];
    }

    private function saveBackupResult($job_id, $status, $email_data) {
        $stmt = $this->db->prepare(
            "INSERT INTO backup_results 
            (backup_job_id, status, date, created_at, email_content) 
            VALUES (?, ?, ?, ?, ?)"
        );
        
        $stmt->bind_param(
            'issss',
            $job_id,
            $status,
            $email_data['date'],
            $email_data['timestamp'],
            $email_data['body']
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Fehler beim Speichern des Backup-Results: " . $stmt->error);
        }
    }

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