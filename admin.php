<?php
require_once 'includes/Database.php';
require_once 'includes/functions.php';

// Prüfe Installation
checkInstallation();
try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    error_log("Fehler bei der Datenbankverbindung: " . $e->getMessage());
    // Weiterleitung auf eine Fehlerseite oder Anzeige einer Fehlermeldung
    http_response_code(500);
    echo "Es ist ein Fehler aufgetreten. Bitte versuche es später erneut.";
    exit;
}

// Initialisiere Datenbankverbindung
$db = Database::getInstance()->getConnection();

// Verarbeite POST-Anfragen
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_customer':
            $stmt = $db->prepare("INSERT INTO customers (customer_number, name, notes) VALUES (?, ?, ?)");
            $number = generateCustomerNumber($db);
            $stmt->bind_param("sss", $number, $_POST['name'], $_POST['notes']);
            
            if ($stmt->execute()) {
                $customer_id = $db->insert_id;
                
                // E-Mail-Adressen hinzufügen
                $emails = explode("\n", $_POST['emails']);
                $stmt = $db->prepare("INSERT INTO customer_emails (customer_id, email) VALUES (?, ?)");
                
                foreach ($emails as $email) {
                    $email = trim($email);
                    if (isValidEmail($email)) {
                        $stmt->bind_param("is", $customer_id, $email);
                        $stmt->execute();
                    }
                }
                $success = "Kunde wurde angelegt";
            }
            break;

        case 'add_backup_job':
            $stmt = $db->prepare("
                INSERT INTO backup_jobs 
                (customer_id, backup_type_id, job_name, hostname, notes) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "iisss", 
                $_POST['customer_id'], 
                $_POST['backup_type_id'],
                $_POST['job_name'],
                $_POST['hostname'],
                $_POST['notes']
            );
            
            if ($stmt->execute()) {
                $success = "Backup-Job wurde angelegt";
            }
            break;

        case 'add_status':
            $stmt = $db->prepare("
                INSERT INTO backup_status 
                (name, color, search_strings, priority) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "sssi",
                $_POST['name'],
                $_POST['color'],
                $_POST['search_strings'],
                $_POST['priority']
            );
            
            if ($stmt->execute()) {
                $success = "Status wurde angelegt";
            }
            break;

        case 'save_mail_config':
            foreach (['mail_host', 'mail_port', 'mail_username', 'mail_password', 'mail_encryption'] as $key) {
                if (isset($_POST[$key])) {
                    saveSystemConfig($db, $key, $_POST[$key]);
                }
            }
            $success = "Mail-Einstellungen wurden gespeichert";
            break;

        case 'delete_customer':
            $stmt = $db->prepare("DELETE FROM customers WHERE id = ?");
            $stmt->bind_param("i", $_POST['customer_id']);
            if ($stmt->execute()) {
                $success = "Kunde wurde gelöscht";
            }
            break;

        case 'delete_job':
            $stmt = $db->prepare("DELETE FROM backup_jobs WHERE id = ?");
            $stmt->bind_param("i", $_POST['job_id']);
            if ($stmt->execute()) {
                $success = "Job wurde gelöscht";
            }
            break;

        case 'update_notes':
            $table = $_POST['table'];
            $allowed_tables = ['customers', 'backup_jobs', 'backup_results'];
            
            if (in_array($table, $allowed_tables)) {
                $stmt = $db->prepare("UPDATE $table SET notes = ? WHERE id = ?");
                $stmt->bind_param("si", $_POST['notes'], $_POST['id']);
                $stmt->execute();
                exit(json_encode(['success' => true]));
            }
            break;
    }
}

// Hole aktuelle Konfiguration
$config = getSystemConfig($db);

// Bestimme aktive Seite
$page = $_GET['page'] ?? 'customers';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Backup Monitor - Administration</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <!-- Seitenleiste -->
        <div class="sidebar">
            <h2>Administration</h2>
            <nav>
                <a href="?page=customers" class="sidebar-button <?= $page === 'customers' ? 'active' : '' ?>">
                    Kunden
                </a>
                <a href="?page=backup_jobs" class="sidebar-button <?= $page === 'backup_jobs' ? 'active' : '' ?>">
                    Backup-Jobs
                </a>
                <a href="?page=status" class="sidebar-button <?= $page === 'status' ? 'active' : '' ?>">
                    Status
                </a>
                <a href="?page=mail" class="sidebar-button <?= $page === 'mail' ? 'active' : '' ?>">
                    Mail-Einstellungen
                </a>
            </nav>
        </div>

        <!-- Hauptbereich -->
        <div class="content">
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php
            // Lade entsprechende Teilansicht
            switch ($page) {
                case 'customers':
                    include 'views/admin/customers.php';
                    break;
                case 'backup_jobs':
                    include 'views/admin/backup_jobs.php';
                    break;
                case 'status':
                    include 'views/admin/status.php';
                    break;
                case 'mail':
                    include 'views/admin/mail.php';
                    break;
            }
            ?>
        </div>
    </div>

    <script src="assets/js/script.js"></script>
</body>
</html>