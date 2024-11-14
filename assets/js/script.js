// Notizen-Verwaltung
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
        } else {
            alert('Fehler beim Speichern der Notizen');
        }
    })
    .catch(error => {
        alert('Fehler beim Speichern der Notizen');
    });
}

// Automatisches Speichern bei Änderungen
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.notes-textarea').forEach(textarea => {
        let saveTimeout;
        textarea.addEventListener('input', () => {
            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(() => {
                saveNotes(textarea.dataset.statusId);
            }, 1000);
        });
    });
});

// PDF Download
function downloadPdf(resultId) {
    if (resultId) {
        window.location.href = `download_pdf.php?id=${resultId}`;
    }
}

// Modal-Funktionen
function openModal(modalId) {
    document.getElementById(modalId).style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Schließen bei Klick außerhalb
window.addEventListener('click', (event) => {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
});

// Admin-Funktionen
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

function deleteJob(id) {
    if (confirm('Backup-Job wirklich löschen?')) {
        const form = document.createElement('form');
        form.method = 'post';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_job">
            <input type="hidden" name="job_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteCustomer(id) {
    if (confirm('Kunden und alle zugehörigen Jobs wirklich löschen?')) {
        const form = document.createElement('form');
        form.method = 'post';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_customer">
            <input type="hidden" name="customer_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Farbwähler-Vorschau
document.querySelectorAll('input[type="color"]').forEach(input => {
    input.addEventListener('input', (e) => {
        const preview = e.target.nextElementSibling;
        if (preview && preview.classList.contains('color-preview')) {
            preview.style.backgroundColor = e.target.value;
        }
    });
});

// Mail-Test im Admin-Bereich
function testMailConnection() {
    const resultDiv = document.getElementById('test-result');
    resultDiv.innerHTML = 'Teste Verbindung...';
    
    fetch('test_mail_connection.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                resultDiv.innerHTML = `<div style="color: green;">Verbindung erfolgreich! ${data.message}</div>`;
            } else {
                resultDiv.innerHTML = `<div style="color: red;">Verbindungsfehler: ${data.error}</div>`;
            }
        })
        .catch(error => {
            resultDiv.innerHTML = `<div style="color: red;">Fehler beim Testen der Verbindung: ${error}</div>`;
        });
}