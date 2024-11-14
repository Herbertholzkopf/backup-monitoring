<?php
// Aktive Seite bestimmen
$current_page = $_GET['page'] ?? 'mail';

// Status-Verwaltung
if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'add_status':
            $stmt = $db->prepare("INSERT INTO backup_status (name, color, search_strings, priority) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $_POST['name'], $_POST['color'], $_POST['search_strings'], $_POST['priority']);
            $stmt->execute();
            break;
            
        case 'update_status':
            $stmt = $db->prepare("UPDATE backup_status SET name=?, color=?, search_strings=?, priority=? WHERE id=?");
            $stmt->bind_param("sssii", $_POST['name'], $_POST['color'], $_POST['search_strings'], $_POST['priority'], $_POST['status_id']);
            $stmt->execute();
            break;
            
        case 'delete_status':
            $stmt = $db->prepare("DELETE FROM backup_status WHERE id=?");
            $stmt->bind_param("i", $_POST['status_id']);
            $stmt->execute();
            break;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Backup Monitor - Einstellungen</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }
        
        .container {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 250px;
            background: #2c3e50;
            color: white;
            padding: 20px 0;
        }
        
        .sidebar a {
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            display: block;
        }
        
        .sidebar a:hover {
            background: #34495e;
        }
        
        .sidebar a.active {
            background: #3498db;
        }
        
        .content {
            flex: 1;
            padding: 20px;
            background: #f5f5f5;
        }
        
        .section {
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .button {
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 3px;
            cursor: pointer;
        }
        
        .button:hover {
            background: #2980b9;
        }
        
        .status-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .status-table th, .status-table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        
        .status-table th {
            background: #f5f5f5;
        }
        
        .color-preview {
            width: 20px;
            height: 20px;
            display: inline-block;
            border: 1px solid #ddd;
            vertical-align: middle;
        }
        
        .code-block {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            font-family: monospace;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <h2 style="padding: 0 20px;">Einstellungen</h2>
            <a href="?page=mail" class="<?= $current_page == 'mail' ? 'active' : '' ?>">
                E-Mail-Einstellungen
            </a>
            <a href="?page=status" class="<?= $current_page == 'status' ? 'active' : '' ?>">
                Status-Verwaltung
            </a>
            <a href="?page=backup_types" class="<?= $current_page == 'backup_types' ? 'active' : '' ?>">
                Backup-Arten
            </a>
            <a href="?page=system" class="<?= $current_page == 'system' ? 'active' : '' ?>">
                System
            </a>
        </div>

        <div class="content">
            <?php if ($current_page == 'mail'): ?>
                <div class="section">
                    <h2>E-Mail-Einstellungen</h2>
                    <form method="post">
                        <input type="hidden" name="action" value="save_config">
                        
                        <div class="form-group">
                            <label>POP3 Server:</label>
                            <input type="text" 
                                   name="config[mail_server]" 
                                   value="<?= htmlspecialchars($config['mail_server']['config_value']) ?>"
                                   placeholder="pop.example.com">
                            <span class="help-text">z.B. pop.gmail.com</span>
                        </div>

                        <!-- [Weitere Mail-Einstellungen wie zuvor] -->

                        <div class="section">
                            <h3>CRON-Job Einrichtung</h3>
                            <p>Um die E-Mails regelmäßig abzuholen, richten Sie bitte folgenden CRON-Job ein:</p>
                            
                            <div class="code-block">
                                # E-Mails alle 5 Minuten abholen<br>
                                */5 * * * * /usr/bin/php /path/to/mail_processor.php
                            </div>
                            
                            <p>Alternative mit Python:</p>
                            <div class="code-block">
                                */5 * * * * /usr/bin/python3 /path/to/mail_processor.py
                            </div>
                            
                            <p>Installation über crontab:</p>
                            <div class="code-block">
                                1. Öffnen Sie den Crontab-Editor:<br>
                                crontab -e<br><br>
                                2. Fügen Sie eine der obigen Zeilen ein<br>
                                3. Speichern und beenden Sie den Editor
                            </div>
                        </div>
                    </form>
                </div>

            <?php elseif ($current_page == 'status'): ?>
                <div class="section">
                    <h2>Status-Verwaltung</h2>
                    
                    <!-- Neuen Status anlegen -->
                    <form method="post" id="addStatusForm">
                        <input type="hidden" name="action" value="add_status">
                        
                        <div class="form-group">
                            <label>Name:</label>
                            <input type="text" name="name" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Farbe:</label>
                            <input type="color" name="color" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Suchbegriffe (kommagetrennt):</label>
                            <textarea name="search_strings" required></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Priorität:</label>
                            <input type="number" name="priority" value="100" required>
                        </div>
                        
                        <button type="submit" class="button">Status hinzufügen</button>
                    </form>
                    
                    <!-- Status-Übersicht -->
                    <table class="status-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Farbe</th>
                                <th>Suchbegriffe</th>
                                <th>Priorität</th>
                                <th>Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $result = $db->query("SELECT * FROM backup_status ORDER BY priority DESC");
                            while ($status = $result->fetch_assoc()):
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($status['name']) ?></td>
                                <td>
                                    <span class="color-preview" style="background-color: <?= htmlspecialchars($status['color']) ?>"></span>
                                    <?= htmlspecialchars($status['color']) ?>
                                </td>
                                <td><?= htmlspecialchars($status['search_strings']) ?></td>
                                <td><?= $status['priority'] ?></td>
                                <td>
                                    <button onclick="editStatus(<?= $status['id'] ?>)" class="button">Bearbeiten</button>
                                    <button onclick="deleteStatus(<?= $status['id'] ?>)" class="button" style="background: #e74c3c;">Löschen</button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

            <?php endif; ?>
        </div>
    </div>

    <script>
        function editStatus(id) {
            // Implementierung des Status-Bearbeiten-Dialogs
        }
        
        function deleteStatus(id) {
            if (confirm('Status wirklich löschen?')) {
                const form = document.createElement('form');
                form.method = 'post';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_status">
                    <input type="hidden" name="status_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>