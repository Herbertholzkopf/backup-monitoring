<h2>Kunden verwalten</h2>

<div class="section">
    <h3>Neuen Kunden anlegen</h3>
    <form method="post">
        <input type="hidden" name="action" value="add_customer">
        
        <div class="form-group">
            <label>Name:</label>
            <input type="text" name="name" required>
        </div>
        
        <div class="form-group">
            <label>E-Mail-Adressen (eine pro Zeile):</label>
            <textarea name="emails" rows="4"></textarea>
        </div>
        
        <div class="form-group">
            <label>Notizen:</label>
            <textarea name="notes" rows="3"></textarea>
        </div>
        
        <button type="submit" class="button">Kunde anlegen</button>
    </form>
</div>

<div class="section">
    <h3>Bestehende Kunden</h3>
    <table class="table">
        <thead>
            <tr>
                <th>Kundennummer</th>
                <th>Name</th>
                <th>E-Mail-Adressen</th>
                <th>Notizen</th>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $result = $db->query("
                SELECT 
                    c.*,
                    GROUP_CONCAT(ce.email) as emails
                FROM customers c
                LEFT JOIN customer_emails ce ON c.id = ce.customer_id
                GROUP BY c.id
                ORDER BY c.name
            ");
            
            while ($customer = $result->fetch_assoc()):
            ?>
            <tr>
                <td><?= htmlspecialchars($customer['customer_number']) ?></td>
                <td><?= htmlspecialchars($customer['name']) ?></td>
                <td><?= nl2br(htmlspecialchars($customer['emails'])) ?></td>
                <td>
                    <textarea 
                        class="notes-editor" 
                        data-table="customers" 
                        data-id="<?= $customer['id'] ?>"
                    ><?= htmlspecialchars($customer['notes']) ?></textarea>
                </td>
                <td>
                    <button onclick="deleteCustomer(<?= $customer['id'] ?>)" class="button button-danger">
                        Löschen
                    </button>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>