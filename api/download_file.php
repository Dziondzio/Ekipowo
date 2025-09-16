<?php
require_once '../config/config.php';

// Sprawdź czy użytkownik jest zalogowany
if (!is_logged_in()) {
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
    
    // Pobierz plik
    $file_path = UPLOADS_PATH . '/subdomains/' . $file_info['subdomain_name'] . '/' . $file_info['file_path'];
    
    if (!file_exists($file_path)) {
        throw new Exception('Plik nie istnieje na serwerze.');
    }
    
    // Określ typ MIME
    $file_extension = strtolower(pathinfo($file_info['file_path'], PATHINFO_EXTENSION));
    $mime_types = [
        'html' => 'text/html',
        'htm' => 'text/html',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'xml' => 'application/xml',
        'txt' => 'text/plain',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon'
    ];
    
    $mime_type = $mime_types[$file_extension] ?? 'application/octet-stream';
    
    // Wyślij nagłówki
    header('Content-Type: ' . $mime_type);
    header('Content-Disposition: attachment; filename="' . basename($file_info['file_path']) . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    
    // Wyślij plik
    readfile($file_path);
    
    // Loguj aktywność
    log_activity($_SESSION['user_id'], 'file_downloaded', 'Pobrano plik: ' . $file_info['file_path'] . ' z subdomeny: ' . $file_info['subdomain_name']);
    
} catch (Exception $e) {
    http_response_code(400);
    echo $e->getMessage();
}
?>