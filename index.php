<?php
require_once 'includes/Database.php';
require_once 'includes/functions.php';

// Prüfe Installation
checkInstallation();

// Datenbankverbindung
$db = Database::getInstance()->getConnection();

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
    $customerId = $row['customer_id'];
    $jobId = $row['job_id'];
    
    if (!isset($customers[$customerId])) {
        $customers[$customerId] = [
            'name' => $row['customer_name'],
            'number' => $row['customer_number'],
            'emails' => $row['emails'],
            'jobs' => []
        ];
    }
    
    if ($jobId && !isset($customers[$customerId]['jobs'][$jobId])) {
        $customers[$customerId]['jobs'][$jobId] = [
            'name' => $row['job_name'],
            'hostname' => $row['hostname'],
            'backup_type' => $row['backup_type'],
            'results' => []
        ];
    }
    
    if ($row['date']) {
        $customers[$customerId]['jobs'][$jobId]['results'][] = [
            'status' => $row['status'],
            'date' => $row['date'],
            'created_at' => $row['created_at'],
            'result_id' => $row['result_id'],
            'notes' => $row['result_notes']
        ];
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Backup Monitor</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div class="header">
        <h1>Backup Monitor</h1>
        <a href="/admin.php" class="settings-button">Einstellungen</a>
    </div>

    <?php foreach ($customers as $customerId => $customer): ?>
        <div class="customer-card">
            <div class="customer-header tooltip">
                <h2 class="customer-name">
                    <?= htmlspecialchars($customer['name']) ?>
                    <span class="customer-number">(<?= htmlspecialchars($customer['number']) ?>)</span>
                </h2>
                <div class="tooltip-content">
                    <strong>E-Mail-Adressen:</strong><br>
                    <?= nl2br(htmlspecialchars($customer['emails'])) ?>
                </div>
            </div>
            
            <?php foreach ($customer['jobs'] as $jobId => $job): ?>
                <div class="backup-job">
                    <div class="job-header tooltip">
                        <span class="backup-type"><?= htmlspecialchars($job['backup_type']) ?></span>
                        <span class="job-name"><?= htmlspecialchars($job['name']) ?></span>
                        <div class="tooltip-content">
                            <strong>Hostname:</strong> <?= htmlspecialchars($job['hostname']) ?>
                        </div>
                    </div>
                    
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

    <script src="/assets/js/script.js"></script>
</body>
</html>
