<?php
session_start();

// Zusätzliche Sicherheitsmaßnahmen
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = null;
$success = null;

// Verbesserte Installations-Prüfung
if (file_exists('../config/config.php')) {
    if ($step === 1) {
        die('Installation wurde bereits durchgeführt. Bitte löschen Sie den "install" Ordner.');
    } elseif ($step !== 3) {
        header('Location: ?step=3');
        exit;
    }
}

function testDatabaseConnection($host, $user, $password, $database) {
    try {
        $db = @new mysqli($host, $user, $password);
        if ($db->connect_error) {
            throw new Exception("Verbindungsfehler: " . $db->connect_error);
        }
        
        // SQL-Injection Prevention
        $database = $db->real_escape_string($database);
        $result = $db->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$database'");
        
        if ($result->num_rows === 0) {
            if (!$db->query("CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
                throw new Exception("Fehler beim Erstellen der Datenbank");
            }
        }
        
        $db->select_db($database);
        
        // Prüfe Schreibrechte
        $testTable = "test_" . uniqid();
        if (!$db->query("CREATE TABLE `$testTable` (id INT)")) {
            throw new Exception("Keine Schreibrechte in der Datenbank");
        }
        $db->query("DROP TABLE `$testTable`");
        
        return true;
    } catch (Exception $e) {
        throw $e;
    } finally {
        if (isset($db)) {
            $db->close();
        }
    }
}

// Zusätzliche Anforderungsprüfungen
$requirements = [
    'PHP Version >= 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
    'MySQLi Erweiterung' => extension_loaded('mysqli'),
    'PDO Erweiterung' => extension_loaded('pdo'),
    'config/ Verzeichnis beschreibbar' => is_writable('../config') || is_writable('..'),
    'IMAP Erweiterung' => extension_loaded('imap'),
    'OpenSSL Erweiterung' => extension_loaded('openssl'),
    'Ausreichend Speicherplatz' => disk_free_space('/') > 100 * 1024 * 1024, // 100MB
    'PHP Memory Limit >= 128M' => (int)ini_get('memory_limit') >= 128,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch ($step) {
            case 1:
                if (empty($_POST['db_host']) || empty($_POST['db_user']) || empty($_POST['db_name'])) {
                    throw new Exception("Alle Pflichtfelder müssen ausgefüllt werden");
                }
                
                if (testDatabaseConnection(
                    $_POST['db_host'],
                    $_POST['db_user'],
                    $_POST['db_password'],
                    $_POST['db_name']
                )) {
                    $_SESSION['db_config'] = [
                        'host' => $_POST['db_host'],
                        'user' => $_POST['db_user'],
                        'password' => $_POST['db_password'],
                        'database' => $_POST['db_name']
                    ];
                    header('Location: ?step=2');
                    exit;
                }
                break;
                
            case 2:
                if (!isset($_SESSION['db_config'])) {
                    header('Location: ?step=1');
                    exit;
                }
                
                $db = new mysqli(
                    $_SESSION['db_config']['host'],
                    $_SESSION['db_config']['user'],
                    $_SESSION['db_config']['password'],
                    $_SESSION['db_config']['database']
                );
                $db->set_charset('utf8mb4');
                
                $sql = file_get_contents('sql/install.sql');
                $queries = explode(';', $sql);
                
                $db->begin_transaction();
                
                try {
                    foreach ($queries as $query) {
                        if (trim($query)) {
                            if (!$db->query($query)) {
                                throw new Exception($db->error);
                            }
                        }
                    }
                    
                    // Erstelle Konfigurationsdatei
                    $config = [
                        'db' => $_SESSION['db_config'],
                        'app' => [
                            'name' => 'Backup Monitor',
                            'timezone' => 'Europe/Berlin',
                            'debug' => false,
                            'log_path' => __DIR__ . '/../logs'
                        ],
                        'security' => [
                            'allowed_ips' => ['127.0.0.1']
                        ],
                        'installation_date' => date('Y-m-d H:i:s')
                    ];
                    
                    if (!is_dir('../config')) {
                        if (!mkdir('../config', 0755, true)) {
                            throw new Exception("Konnte Verzeichnis config/ nicht erstellen");
                        }
                    }
                    
                    if (!file_put_contents('../config/config.php', 
                        "<?php\nreturn " . var_export($config, true) . ";\n")) {
                        throw new Exception("Konnte Konfigurationsdatei nicht erstellen");
                    }
                    
                    $db->commit();
                    header('Location: ?step=3');
                    exit;
                    
                } catch (Exception $e) {
                    $db->rollback();
                    throw $e;
                }
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// [Rest des HTML-Codes bleibt unverändert]

<!DOCTYPE html>
<html>
<head>
    <title>Backup-Monitor Installation</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .step-indicator {
            display: flex;
            margin-bottom: 30px;
            border-bottom: 2px solid #eee;
            padding-bottom: 20px;
        }
        
        .step {
            flex: 1;
            text-align: center;
            color: #999;
        }
        
        .step.active {
            color: #2196F3;
            font-weight: bold;
        }
        
        .step.completed {
            color: #4CAF50;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        button {
            background: #2196F3;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        
        button:hover {
            background: #1976D2;
        }
        
        .error {
            background: #ffebee;
            color: #c62828;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .success {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .requirements {
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        
        .requirement {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .requirement-status {
            margin-right: 10px;
            font-weight: bold;
        }
        
        .requirement-status.ok {
            color: #4CAF50;
        }
        
        .requirement-status.error {
            color: #F44336;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="step-indicator">
            <div class="step <?= $step === 1 ? 'active' : ($step > 1 ? 'completed' : '') ?>">
                1. Systemcheck & Datenbank
            </div>
            <div class="step <?= $step === 2 ? 'active' : ($step > 2 ? 'completed' : '') ?>">
                2. Installation
            </div>
            <div class="step <?= $step === 3 ? 'active' : '' ?>">
                3. Fertigstellung
            </div>
        </div>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <h2>Systemanforderungen</h2>
            <div class="requirements">
                <?php
                $requirements = [
                    'PHP Version >= 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
                    'MySQLi Erweiterung' => extension_loaded('mysqli'),
                    'PDO Erweiterung' => extension_loaded('pdo'),
                    'config/ Verzeichnis beschreibbar' => is_writable('../config') || is_writable('..'),
                ];
                
                $allRequirementsMet = true;
                foreach ($requirements as $requirement => $met):
                    $allRequirementsMet = $allRequirementsMet && $met;
                ?>
                    <div class="requirement">
                        <span class="requirement-status <?= $met ? 'ok' : 'error' ?>">
                            <?= $met ? '✓' : '✗' ?>
                        </span>
                        <?= htmlspecialchars($requirement) ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($allRequirementsMet): ?>
                <h2>Datenbank-Konfiguration</h2>
                <form method="post">
                    <div class="form-group">
                        <label>Datenbank-Host:</label>
                        <input type="text" name="db_host" value="localhost" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Datenbank-Name:</label>
                        <input type="text" name="db_name" value="backup_monitor" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Datenbank-Benutzer:</label>
                        <input type="text" name="db_user" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Datenbank-Passwort:</label>
                        <input type="password" name="db_password">
                    </div>
                    
                    <button type="submit">Weiter</button>
                </form>
            <?php else: ?>
                <div class="error">
                    Bitte beheben Sie zuerst die nicht erfüllten Systemanforderungen.
                </div>
            <?php endif; ?>

        <?php elseif ($step === 2): ?>
            <h2>Installation</h2>
            <div class="progress">
                <p>Die Datenbank wird eingerichtet und die Tabellen werden erstellt...</p>
                <form method="post">
                    <button type="submit">Installation starten</button>
                </form>
            </div>

        <?php elseif ($step === 3): ?>
            <h2>Installation abgeschlossen</h2>
            <div class="success">
                <p>Die Installation wurde erfolgreich abgeschlossen!</p>
                <p>Bitte löschen Sie aus Sicherheitsgründen den "install" Ordner.</p>
            </div>
            <a href="../" class="button">Zum Backup-Monitor</a>
        <?php endif; ?>
    </div>
</body>
</html>