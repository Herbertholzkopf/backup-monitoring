<h2>Mail-Einstellungen</h2>
<div class="section">
    <form method="post">
        <input type="hidden" name="action" value="save_mail_config">
        
        <div class="form-group">
            <label>IMAP Server:</label>
            <input type="text" 
                   name="mail_host" 
                   value="<?= htmlspecialchars($config['mail_host']) ?>" 
                   placeholder="imap.example.com" required>
        </div>

        <div class="form-group">
            <label>IMAP Port:</label>
            <input type="number" 
                   name="mail_port" 
                   value="<?= htmlspecialchars($config['mail_port']) ?>" 
                   placeholder="993" required>
        </div>

        <div class="form-group">
            <label>IMAP Benutzername:</label>
            <input type="text" 
                   name="mail_username" 
                   value="<?= htmlspecialchars($config['mail_username']) ?>" 
                   required>
        </div>

        <div class="form-group">
            <label>IMAP Passwort:</label>
            <input type="password" 
                   name="mail_password" 
                   value="<?= htmlspecialchars($config['mail_password']) ?>" 
                   required>
        </div>

        <div class="form-group">
            <label for="mail_encryption">Verschlüsselungstyp</label>
            <select id="mail_encryption" name="mail_encryption">
                <option value="none" <?= $config['mail_encryption'] === 'none' ? 'selected' : '' ?>>Keine</option>
                <option value="ssl" <?= $config['mail_encryption'] === 'ssl' ? 'selected' : '' ?>>SSL</option>
                <option value="tls" <?= $config['mail_encryption'] === 'tls' ? 'selected' : '' ?>>TLS</option>
            </select>
        </div>

        <div class="form-group">
            <label>
                <input type="checkbox" 
                       name="mail_delete_after_processing" 
                       value="1" 
                       <?= $config['mail_delete_after_processing'] ? 'checked' : '' ?>>
                Verarbeitete E-Mails löschen
            </label>
        </div>

        <button type="submit" class="button">Einstellungen speichern</button>
    </form>
</div>

<div class="section">
    <h3>CRON-Job Einrichtung</h3>
    <div class="code-block">
        <p>Fügen Sie folgenden Eintrag in Ihre Crontab ein (crontab -e):</p>
        <pre>*/5 * * * * php <?= dirname(__DIR__) ?>/../../process_mails.php</pre>
    </div>

    <div class="test-connection">
        <h3>Verbindungstest</h3>
        <button onclick="testMailConnection()" class="button">Verbindung testen</button>
        <div id="test-result"></div>
    </div>
</div>