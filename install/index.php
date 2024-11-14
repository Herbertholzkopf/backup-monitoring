<?php
// install/index.php

session_start();
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = null;
$success = null;

// Prüfen ob bereits installiert
if (file_exists('../config/config.php') && $step === 1) {
    die('Installation wurde bereits durchgeführt. Aus Sicherheitsgründen löschen Sie bitte den "install" Ordner.');
}

function testDatabaseConnection($host, $user, $password, $database) {
    try {
        $db = new mysqli($host, $user, $password);
        if ($db->connect_error) {
            throw new Exception("Verbindungsfehler: " . $db->connect_error);
        }
        
        // Prüfe ob Datenbank existiert
        $result = $db->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$database'");
        if ($result->num_rows === 0) {
            // Versuche Datenbank zu erstellen
            if (!$db->query("CREATE DATABASE IF NOT EXISTS `$database`")) {
                throw new Exception("Fehler beim Erstellen der Datenbank");
            }
        }
        
        $db->select_db($database);
        return true;
    } catch (Exception $e) {
        throw $e;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 1:
            // Datenbankverbindung testen
            try {
                if (testDatabaseConnection(
                    $_POST['db_host'],
                    $_POST['db_user'],
                    $_POST['db_password'],
                    $_POST['db_name']
                )) {
                    // Speichere Daten in Session für nächsten Schritt
                    $_SESSION['db_config'] = [
                        'host' => $_POST['db_host'],
                        'user' => $_POST['db_user'],
                        'password' => $_POST['db_password'],
                        'database' => $_POST['db_name']
                    ];
                    header('Location: ?step=2');
                    exit;
                }
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
            break;
            
        case 2:
            // Datenbank-Tabellen erstellen
            try {
                $db = new mysqli(
                    $_SESSION['db_config']['host'],
                    $_SESSION['db_config']['user'],
                    $_SESSION['db_config']['password'],
                    $_SESSION['db_config']['database']
                );
                
                // SQL für Tabellen-Erstellung
                $sql = file_get_contents('sql/install.sql');
                $queries = explode(';', $sql);
                
                foreach ($queries as $query) {
                    if (trim($query)) {
                        if (!$db->query($query)) {
                            throw new Exception("Fehler beim Ausführen der SQL-Query: " . $db->error);
                        }
                    }
                }
                
                // Config-Datei erstellen
                $config_content = "<?php\nreturn " . var_export([
                    'db' => $_SESSION['db_config'],
                    'installation_date' => date('Y-m-d H:i:s')
                ], true) . ";\n";
                
                if (!is_dir('../config')) {
                    mkdir('../config', 0755, true);
                }
                
                if (file_put_contents('../config/config.php', $config_content)) {
                    header('Location: ?step=3');
                    exit;
                } else {
                    throw new Exception("Fehler beim Erstellen der Konfigurationsdatei");
                }
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
            break;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Backup-Monitor Installation</title>
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