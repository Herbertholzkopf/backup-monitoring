<h2>Backup-Jobs verwalten</h2>

<div class="section">
    <h3>Neuen Backup-Job anlegen</h3>
    <form method="post">
        <input type="hidden" name="action" value="add_backup_job">
        
        <div class="form-group">
            <label>Kunde:</label>
            <select name="customer_id" required>
                <option value="">-- Kunde auswählen --</option>
                <?php
                $result = $db->query("SELECT id, name, customer_number FROM customers ORDER BY name");
                while ($customer = $result->fetch_assoc()) {
                    echo "<option value='{$customer['id']}'>" .
                         htmlspecialchars("{$customer['name']} ({$customer['customer_number']})") .
                         "</option>";
                }
                ?>
            </select>
        </div>
        
        <div class="form-group">
            <label>Backup-Art:</label>
            <select name="backup_type_id" required>
                <?php
                $result = $db->query("SELECT * FROM backup_types ORDER BY name");
                while ($type = $result->fetch_assoc()) {
                    echo "<option value='{$type['id']}'>" .
                         htmlspecialchars($type['name']) .
                         "</option>";
                }
                ?>
            </select>
        </div>
        
        <div class="form-group">
            <label>Job-Name:</label>
            <input type="text" name="job_name" required>
        </div>
        
        <div class="form-group">
            <label>Hostname/Rechnername:</label>
            <input type="text" name="hostname" required>
        </div>
        
        <div class="form-group">
            <label>Notizen:</label>
            <textarea name="notes" rows="3"></textarea>
        </div>
        
        <button type="submit" class="button">Job anlegen</button>
    </form>
</div>

<div class="section">
    <h3>Bestehende Jobs</h3>
    <table class="table">
        <thead>
            <tr>
                <th>Kunde</th>
                <th>Backup-Art</th>
                <th>Job-Name</th>
                <th>Hostname</th>
                <th>Notizen</th>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $result = $db->query("
                SELECT 
                    bj.*,
                    c.name as customer_name,
                    bt.name as backup_type
                FROM backup_jobs bj
                JOIN customers c ON bj.customer_id = c.id
                JOIN backup_types bt ON bj.backup_type_id = bt.id
                ORDER BY c.name, bj.job_name
            ");
            
            while ($job = $result->fetch_assoc()):
            ?>
            <tr>
                <td><?= htmlspecialchars($job['customer_name']) ?></td>
                <td><?= htmlspecialchars($job['backup_type']) ?></td>
                <td><?= htmlspecialchars($job['job_name']) ?></td>
                <td><?= htmlspecialchars($job['hostname']) ?></td>
                <td>
                    <textarea 
                        class="notes-editor" 
                        data-table="backup_jobs" 
                        data-id="<?= $job['id'] ?>"
                    ><?= htmlspecialchars($job['notes']) ?></textarea>
                </td>
                <td>
                    <button onclick="deleteJob(<?= $job['id'] ?>)" class="button button-danger">
                        Löschen
                    </button>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>