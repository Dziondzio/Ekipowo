<?php
require_once '../../config/config.php';

header('Content-Type: application/json');

// Sprawdź czy użytkownik jest zalogowany i jest administratorem
if (!is_logged_in() || !is_admin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Brak uprawnień.']);
    exit;
}

// Sprawdź czy podano ID subdomeny
if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nie podano ID subdomeny.']);
    exit;
}

$subdomain_id = (int)$_GET['id'];

try {
    $db = new DatabaseManager();
    
    // Pobierz informacje o subdomenie
    $subdomain = $db->selectOne("
        SELECT s.*, u.username, u.email 
        FROM subdomains s 
        JOIN users u ON s.user_id = u.id 
        WHERE s.id = ?
    ", [$subdomain_id]);
    
    if (!$subdomain) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Subdomena nie została znaleziona.']);
        exit;
    }
    
    // Pobierz statystyki plików dla hostowanych subdomen
    $file_stats = null;
    if ($subdomain['type'] === 'hosted') {
        $file_stats = $db->selectOne("
            SELECT 
                COUNT(*) as file_count,
                COALESCE(SUM(file_size), 0) as total_size
            FROM subdomain_files 
            WHERE subdomain_id = ?
        ", [$subdomain_id]);
    }
    
    // Formatuj dane
    $response_data = [
        'id' => $subdomain['id'],
        'name' => $subdomain['name'],
        'type' => $subdomain['type'],
        'target_ip' => $subdomain['target_ip'],
        'status' => $subdomain['status'],
        'created_at' => $subdomain['created_at'],
        'owner' => [
            'username' => $subdomain['username'],
            'email' => $subdomain['email']
        ]
    ];
    
    if ($file_stats) {
        $response_data['files'] = [
            'count' => (int)$file_stats['file_count'],
            'total_size' => (int)$file_stats['total_size'],
            'total_size_formatted' => format_file_size($file_stats['total_size'])
        ];
    }
    
    echo json_encode([
        'success' => true,
        'subdomain' => $response_data
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Błąd podczas pobierania danych subdomeny: ' . $e->getMessage()
    ]);
}
?>