-- install/sql/install.sql

-- Kunden
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_number VARCHAR(50) NOT NULL,
    name VARCHAR(255) NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Kunden-E-Mail-Adressen
CREATE TABLE IF NOT EXISTS customer_emails (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT,
    email VARCHAR(255) NOT NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- Backup-Arten
CREATE TABLE IF NOT EXISTS backup_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    identifier_type ENUM('email', 'hostname') NOT NULL,
    notes TEXT
);

-- Backup-Jobs
CREATE TABLE IF NOT EXISTS backup_jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT,
    backup_type_id INT,
    job_name VARCHAR(255) NOT NULL,
    hostname VARCHAR(255) NOT NULL,
    notes TEXT,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (backup_type_id) REFERENCES backup_types(id)
);

-- Backup-Status
CREATE TABLE IF NOT EXISTS backup_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    color VARCHAR(7) NOT NULL,
    search_strings TEXT NOT NULL,
    priority INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Backup-Ergebnisse
CREATE TABLE IF NOT EXISTS backup_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    backup_job_id INT,
    status VARCHAR(50),
    date DATE NOT NULL,
    email_content LONGTEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (backup_job_id) REFERENCES backup_jobs(id) ON DELETE CASCADE
);

-- System-Konfiguration
CREATE TABLE IF NOT EXISTS system_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(50) NOT NULL UNIQUE,
    config_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Initial-Konfiguration
INSERT INTO system_config (config_key, config_value, description) VALUES
('mail_server', '', 'POP3 Server-Adresse'),
('mail_port', '995', 'POP3 Server-Port'),
('mail_user', '', 'E-Mail Benutzername'),
('mail_password', '', 'E-Mail Passwort'),
('mail_ssl', '1', 'SSL-Verschlüsselung verwenden (1=ja, 0=nein)'),
('mail_delete_after_processing', '0', 'Verarbeitete E-Mails in den Papierkorb verschieben (1=ja, 0=nein)');

-- Initial-Backup-Status
INSERT INTO backup_status (name, color, search_strings, priority) VALUES
('Erfolgreich', '#4CAF50', 'successful,success,completed successfully', 300),
('Warnung', '#FFC107', 'warning,attention required', 200),
('Fehlgeschlagen', '#F44336', 'failed,failure,error,critical', 100),
('Unbekannt', '#9E9E9E', '', 0);

-- Initial-Backup-Arten
INSERT INTO backup_types (name, identifier_type, notes) VALUES
('Veeam-Backup', 'email', 'Identifizierung über Absender-E-Mail'),
('Cloud-Backup', 'hostname', 'Suche nach Hostnamen in der Mail'),
('Synology-HyperBackup', 'email', 'Identifizierung über Absender-E-Mail'),
('Proxmox-Backup', 'email', 'Identifizierung über Absender-E-Mail');