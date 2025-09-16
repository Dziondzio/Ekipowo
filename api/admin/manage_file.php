<?php
require_once '../../config/config.php';

header('Content-Type: application/json');

// Sprawdź czy użytkownik jest zalogowany i jest administratorem
if (!is_logged_in() || !is_admin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Brak uprawnień.']);
    exit;
}

// Sprawdź metodę HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metoda nie dozwolona.']);
    exit;
}

// Sprawdź czy podano akcję
if (!isset($_POST['action']) || empty($_POST['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nie podano akcji.']);
    exit;
}

$action = $_POST['action'];

try {
    $db = new DatabaseManager();
    
    switch ($action) {
        case 'delete':
            if (!isset($_POST['file_id']) || empty($_POST['file_id'])) {
                throw new Exception('Nie podano ID pliku.');
            }
            
            $file_id = (int)$_POST['file_id'];
            
            // Pobierz informacje o pliku
            $file = $db->selectOne("
                SELECT f.*, s.subdomain_name, u.username 
                FROM subdomain_files f 
                JOIN subdomains s ON f.subdomain_id = s.id 
                JOIN users u ON s.user_id = u.id 
                WHERE f.id = ?
            ", [$file_id]);
            
            if (!$file) {
                throw new Exception('Plik nie został znaleziony.');
            }
            
            // Rozpocznij transakcję
            $db->beginTransaction();
            
            // Usuń plik z systemu plików
            $file_path = UPLOAD_DIR . '/' . $file['subdomain_name'] . '/' . $file['file_name'];
            if (file_exists($file_path)) {
                if (!unlink($file_path)) {
                    throw new Exception('Nie udało się usunąć pliku z serwera.');
                }
            }
            
            // Usuń rekord z bazy danych
            $db->execute("DELETE FROM subdomain_files WHERE id = ?", [$file_id]);
            
            // Zatwierdź transakcję
            $db->commit();
            
            // Loguj aktywność
            log_activity($_SESSION['user_id'], 'admin_file_deleted', 
                'Administrator usunął plik: ' . $file['file_name'] . ' (subdomena: ' . $file['subdomain_name'] . ', właściciel: ' . $file['username'] . ')');
            
            echo json_encode([
                'success' => true,
                'message' => 'Plik został usunięty pomyślnie.'
            ]);
            break;
            
        default:
            throw new Exception('Nieznana akcja.');
    }
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollback();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>