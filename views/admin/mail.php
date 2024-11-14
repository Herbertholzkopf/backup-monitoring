<h2>Mail-Einstellungen</h2>

<div class="section">
    <form method="post">
        <input type="hidden" name="action" value="save_mail_config">
        
        <div class="form-group">
            <label>POP3 Server:</label>
            <input type="text" 
                   name="mail_server" 
                   value="<?= htmlspecialchars($config['mail_server']) ?>"
                   placeholder="pop.example.com" required>
        </div>
        
        <div class="form-group">
            <label>Port:</label>
            <input type="number" 
                   name="mail_port" 
                   value="<?= htmlspecialchars($config['mail_port']) ?>"
                   placeholder="995" required>
        </div>
        
        <div class="form-group">
            <label>Benutzername:</label>
            <input type="text" 
                   name="mail_user" 
                   value="<?= htmlspecialchars($config['mail_user']) ?>"
                   required>
        </div>
        
        <div class="form-group">
            <label>Passwort:</label>
            <input type="password" 
                   name="mail_password" 
                   value="<?= htmlspecialchars($config['mail_password']) ?>"
                   required>
        </div>
        
        <div class="form-group">
            <label>
                <input type="checkbox" 
                       name="mail_ssl" 
                       value="1" 
                       <?= $config['mail_ssl'] ? 'checked' : '' ?>>
                SSL-Verschlüsselung verwenden
            </label>
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
        <pre>*/5 * * * * php <?= __DIR__ ?>/../../process_mails.php</pre>
    </div>
    
    <div class="test-connection">
        <h3>Verbindungstest</h3>
        <button onclick="testMailConnection()" class="button">Verbindung testen</button>
        <div id="test-result"></div>
    </div>
</div>