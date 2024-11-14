<?php
$db = new mysqli('localhost', 'your_username', 'your_password', 'backup_monitor');

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Hole alle Kunden mit ihren Backup-Jobs
$sql = "
    SELECT 
        c.id as customer_id,
        c.name as customer_name,
        c.customer_number,
        GROUP_CONCAT(DISTINCT ce.email) as emails,
        bj.id as job_id,
        bj.job_name,
        bj.hostname,
        bt.name as backup_type,
        br.status,
        br.date,
        br.created_at,
        br.id as result_id,
        br.notes as result_notes,
        br.updated_at
    FROM customers c
    LEFT JOIN customer_emails ce ON c.id = ce.customer_id
    LEFT JOIN backup_jobs bj ON c.id = bj.customer_id
    LEFT JOIN backup_types bt ON bj.backup_type_id = bt.id
    LEFT JOIN backup_results br ON bj.id = br.backup_job_id
    WHERE br.date >= DATE_SUB(CURRENT_DATE, INTERVAL 16 DAY) OR br.date IS NULL
    GROUP BY c.id, bj.id, br.id
    ORDER BY c.name, bj.job_name, br.date DESC, br.created_at DESC
";

$result = $db->query($sql);
$customers = [];

while ($row = $result->fetch_assoc()) {
    // ... [Verarbeitung wie zuvor] ...
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Backup Monitor</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        /* ... [Vorherige Styles bleiben] ... */
        
        .tooltip-content {
            width: 300px;
            padding: 15px;
        }
        
        .notes-section {
            margin-top: 10px;
            border-top: 1px solid #eee;
            padding-top: 10px;
        }
        
        .notes-textarea {
            width: 100%;
            min-height: 60px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 12px;
            resize: vertical;
            margin-top: 5px;
        }
        
        .notes-save-btn {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
            margin-top: 5px;
        }
        
        .save-indicator {
            font-size: 12px;
            color: #4CAF50;
            margin-left: 10px;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .tooltip.editing .tooltip-content {
            visibility: visible;
            opacity: 1;
        }
    </style>
</head>
<body>
    <!-- ... [Header bleibt gleich] ... -->

    <?php foreach ($customers as $customerId => $customer): ?>
        <div class="customer-card">
            <!-- ... [Kunde-Header bleibt gleich] ... -->
            
            <?php foreach ($customer['jobs'] as $jobId => $job): ?>
                <div class="backup-job">
                    <!-- ... [Job-Header bleibt gleich] ... -->
                    
                    <div class="status-grid">
                        <?php
                        $today = new DateTime();
                        for ($i = 15; $i >= 0; $i--) {
                            $date = clone $today;
                            $date->modify("-$i days");
                            $dateStr = $date->format('Y-m-d');
                            
                            $dayResults = array_filter($job['results'], function($result) use ($dateStr) {
                                return $result['date'] == $dateStr;
                            });
                            
                            usort($dayResults, function($a, $b) {
                                return strtotime($b['created_at']) - strtotime($a['created_at']);
                            });
                            
                            $status = empty($dayResults) ? 'unknown' : reset($dayResults)['status'];
                            $resultId = empty($dayResults) ? null : reset($dayResults)['result_id'];
                            $notes = empty($dayResults) ? '' : reset($dayResults)['notes'];
                            ?>
                            
                            <div class="tooltip" id="status-<?= $resultId ?>">
                                <div class="status-box status-<?= $status ?>">
                                    <?php if (count($dayResults) > 1): ?>
                                        <span class="multi-status"><?= count($dayResults) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="tooltip-content">
                                    <strong>Datum:</strong> <?= $dateStr ?><br>
                                    <?php foreach ($dayResults as $result): ?>
                                        <div class="status-entry">
                                            <strong>Status:</strong> <?= ucfirst($result['status']) ?><br>
                                            <strong>Zeit:</strong> <?= (new DateTime($result['created_at']))->format('H:i:s') ?><br>
                                            <button class="download-btn" onclick="downloadPdf(<?= $result['result_id'] ?>)">
                                                Mail öffnen
                                            </button>
                                            
                                            <?php if ($result['result_id'] === $resultId): ?>
                                                <div class="notes-section">
                                                    <strong>Notizen:</strong>
                                                    <textarea 
                                                        class="notes-textarea"
                                                        data-status-id="<?= $result['result_id'] ?>"
                                                        placeholder="Notizen hier eingeben..."
                                                        onfocus="handleNotesEdit(this)"
                                                        onblur="handleNotesBlur(this)"
                                                    ><?= htmlspecialchars($result['notes']) ?></textarea>
                                                    <div class="notes-actions">
                                                        <button class="notes-save-btn" onclick="saveNotes(<?= $result['result_id'] ?>)">
                                                            Speichern
                                                        </button>
                                                        <span class="save-indicator">✓ Gespeichert</span>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <script>
        function handleNotesEdit(textarea) {
            textarea.closest('.tooltip').classList.add('editing');
        }
        
        function handleNotesBlur(textarea) {
            setTimeout(() => {
                if (!textarea.closest('.tooltip').contains(document.activeElement)) {
                    textarea.closest('.tooltip').classList.remove('editing');
                }
            }, 200);
        }
        
        function saveNotes(statusId) {
            const tooltip = document.getElementById(`status-${statusId}`);
            const textarea = tooltip.querySelector('.notes-textarea');
            const saveIndicator = tooltip.querySelector('.save-indicator');
            
            fetch('save_notes.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    status_id: statusId,
                    notes: textarea.value
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    saveIndicator.classList.add('show');
                    setTimeout(() => {
                        saveIndicator.classList.remove('show');
                    }, 2000);
                }
            })
            .catch(error => {
                alert('Fehler beim Speichern der Notizen');
            });
        }

        // Automatisches Speichern
        document.querySelectorAll('.notes-textarea').forEach(textarea => {
            let saveTimeout;
            textarea.addEventListener('input', () => {
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(() => {
                    saveNotes(textarea.dataset.statusId);
                }, 1000);
            });
        });
    </script>
</body>
</html>