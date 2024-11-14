<h2>Status-Definitionen verwalten</h2>

<div class="section">
    <h3>Neuen Status anlegen</h3>
    <form method="post">
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
            <small>Diese Begriffe werden in den Backup-Mails gesucht</small>
        </div>
        
        <div class="form-group">
            <label>Priorität:</label>
            <input type="number" name="priority" value="100" required>
            <small>Höhere Priorität gewinnt bei mehreren Treffern</small>
        </div>
        
        <button type="submit" class="button">Status anlegen</button>
    </form>
</div>

<div class="section">
    <h3>Bestehende Status</h3>
    <table class="table">
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
                    <button onclick="editStatus(<?= $status['id'] ?>)" class="button">
                        Bearbeiten
                    </button>
                    <button onclick="deleteStatus(<?= $status['id'] ?>)" class="button button-danger">
                        Löschen
                    </button>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>