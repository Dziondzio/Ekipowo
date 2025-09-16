<?php
// Nagłówki dla AJAX
header('Access-Control-Allow-Credentials: true');
header('Cache-Control: no-cache, must-revalidate');

require_once '../config/config.php';

// Sprawdź czy użytkownik jest zalogowany
if (!is_logged_in()) {
    error_log('User not logged in, returning 401');
    http_response_code(401);
    echo 'Musisz być zalogowany.';
    exit;
}

// Sprawdź metodę HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo 'Metoda nie dozwolona.';
    exit;
}

try {
    $file_id = (int)($_GET['file_id'] ?? 0);
    
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
        throw new Exception('Nie masz uprawnień do tego pliku.');
    }
    
    // Sprawdź czy plik można edytować
    $file_extension = strtolower(pathinfo($file_info['file_path'], PATHINFO_EXTENSION));
    $editable_extensions = ['html', 'htm', 'css', 'js', 'txt', 'json', 'xml'];
    
    if (!in_array($file_extension, $editable_extensions)) {
        throw new Exception('Ten typ pliku nie może być edytowany.');
    }
    
    // Pobierz zawartość pliku
    $file_path = UPLOADS_PATH . '/subdomains/' . $file_info['subdomain_name'] . '/' . $file_info['file_path'];
    
    if (!file_exists($file_path)) {
        throw new Exception('Plik nie istnieje na serwerze.');
    }
    
    $content = file_get_contents($file_path);
    
    if ($content === false) {
        throw new Exception('Nie udało się odczytać zawartości pliku.');
    }
    
    // Zwróć zawartość pliku
    header('Content-Type: text/plain; charset=utf-8');
    echo $content;
    
} catch (Exception $e) {
    http_response_code(400);
    echo $e->getMessage();
}
?>