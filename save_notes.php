<?php
// save_notes.php
header('Content-Type: application/json');

try {
    // Datenbank-Verbindung
    $db = new mysqli('localhost', 'your_username', 'your_password', 'backup_monitor');
    
    // JSON-Daten aus dem Request Body lesen
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['status_id']) || !isset($input['notes'])) {
        throw new Exception('Fehlende Parameter');
    }
    
    // Notizen in der Datenbank aktualisieren
    $stmt = $db->prepare("
        UPDATE backup_results 
        SET notes = ?,
            updated_at = CURRENT_TIMESTAMP 
        WHERE id = ?
    ");
    
    $stmt->bind_param('si', $input['notes'], $input['status_id']);
    $success = $stmt->execute();
    
    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => 'Notizen gespeichert'
        ]);
    } else {
        throw new Exception('Fehler beim Speichern');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>