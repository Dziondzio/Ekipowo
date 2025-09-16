<?php
// Nagłówki dla AJAX
header('Access-Control-Allow-Credentials: true');
header('Cache-Control: no-cache, must-revalidate');
header('Content-Type: application/json');

require_once '../config/config.php';

// Sprawdź czy użytkownik jest zalogowany
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Musisz być zalogowany.']);
    exit;
}

// Sprawdź metodę HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metoda nie dozwolona.']);
    exit;
}

try {
    // Pobierz dane JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['file_id'])) {
        throw new Exception('Nieprawidłowe dane wejściowe.');
    }
    
    $file_id = (int)$input['file_id'];
    
    if ($file_id <= 0) {
        throw new Exception('Nieprawidłowe ID pliku.');
    }
    
    $db = new DatabaseManager();
    
    // Pobierz informacje o pliku
    $file_info = $db->selectOne(
        "SELECT sf.*, s.subdomain_name, s.user_id 
         FROM subdomain_files sf 
         JOIN subdomains s ON sf.subdomain_id = s.id 
         WHERE sf.id = ?",
        [$file_id]
    );
    
    if (!$file_info) {
        throw new Exception('Plik nie został znaleziony.');
    }
    
    // Sprawdź uprawnienia
    if ($file_info['user_id'] != $_SESSION['user_id'] && !is_admin()) {
        throw new Exception('Nie masz uprawnień do usunięcia tego pliku.');
    }
    
    // Usuń plik z systemu plików
    $file_path = UPLOADS_PATH . '/subdomains/' . $file_info['subdomain_name'] . '/' . $file_info['file_path'];
    
    if (file_exists($file_path)) {
        if (!unlink($file_path)) {
            throw new Exception('Nie udało się usunąć pliku z serwera.');
        }
    }
    
    // Usuń rekord z bazy danych
    $result = $db->execute(
        "DELETE FROM subdomain_files WHERE id = ?",
        [$file_id]
    );
    
    if ($result) {
        // Loguj aktywność
        log_activity($_SESSION['user_id'], 'file_deleted', 'Usunięto plik: ' . $file_info['file_path'] . ' z subdomeny: ' . $file_info['subdomain_name']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Plik został usunięty pomyślnie!'
        ]);
    } else {
        throw new Exception('Nie udało się usunąć pliku z bazy danych.');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>