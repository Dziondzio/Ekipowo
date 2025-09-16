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
    $file_id = (int)($_POST['file_id'] ?? 0);
    $content = $_POST['content'] ?? '';
    
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
        throw new Exception('Nie masz uprawnień do edycji tego pliku.');
    }
    
    // Sprawdź czy plik można edytować
    $file_extension = strtolower(pathinfo($file_info['file_path'], PATHINFO_EXTENSION));
    $editable_extensions = ['html', 'htm', 'css', 'js', 'txt', 'json', 'xml'];
    
    if (!in_array($file_extension, $editable_extensions)) {
        throw new Exception('Ten typ pliku nie może być edytowany.');
    }
    
    // Zapisz zawartość pliku
    $file_path = UPLOADS_PATH . '/subdomains/' . $file_info['subdomain_name'] . '/' . $file_info['file_path'];
    
    // Sprawdź czy katalog istnieje i ma odpowiednie uprawnienia
    $dir_path = dirname($file_path);
    if (!is_dir($dir_path)) {
        if (!mkdir($dir_path, 0755, true)) {
            throw new Exception('Nie udało się utworzyć katalogu: ' . $dir_path);
        }
    }
    
    // Sprawdź uprawnienia do zapisu
    if (!is_writable($dir_path)) {
        throw new Exception('Brak uprawnień do zapisu w katalogu: ' . $dir_path);
    }
    
    // Utwórz backup
    $backup_path = $file_path . '.backup.' . time();
    if (file_exists($file_path)) {
        if (!copy($file_path, $backup_path)) {
            throw new Exception('Nie udało się utworzyć kopii zapasowej.');
        }
    }
    
    // Zapisz nową zawartość
    $result = file_put_contents($file_path, $content, LOCK_EX);
    
    if ($result === false) {
        // Przywróć backup jeśli się nie udało
        if (file_exists($backup_path)) {
            copy($backup_path, $file_path);
        }
        $error = error_get_last();
        throw new Exception('Nie udało się zapisać pliku. Błąd: ' . ($error['message'] ?? 'Nieznany błąd'));
    }
    
    // Ustaw odpowiednie uprawnienia do pliku
    chmod($file_path, 0644);
    
    // Aktualizuj rozmiar pliku w bazie danych
    $new_size = filesize($file_path);
    $db->execute(
        "UPDATE subdomain_files SET file_size = ?, uploaded_at = NOW() WHERE id = ?",
        [$new_size, $file_id]
    );
    
    // Usuń backup
    if (file_exists($backup_path)) {
        unlink($backup_path);
    }
    
    // Loguj aktywność
    try {
        log_activity($_SESSION['user_id'], 'file_edited', 'Edytowano plik: ' . $file_info['file_path'] . ' w subdomenie: ' . $file_info['subdomain_name']);
    } catch (Exception $log_error) {
        // Ignoruj błędy logowania - nie przerywaj procesu zapisywania pliku
        error_log('Log activity error: ' . $log_error->getMessage());
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Plik został zapisany pomyślnie!',
        'file_size' => format_file_size($new_size)
    ]);
    
} catch (Exception $e) {
    // Loguj szczegółowy błąd
    error_log('Save file error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
} catch (Error $e) {
    // Obsługa błędów PHP (np. Fatal Error)
    error_log('Save file fatal error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Wystąpił krytyczny błąd serwera.',
        'debug' => [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}
?>