<?php
require_once '../config/config.php';

header('Content-Type: application/json');

// Sprawdź czy użytkownik jest zalogowany
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Musisz być zalogowany.']);
    exit;
}

// Sprawdź metodę HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metoda nie dozwolona.']);
    exit;
}

try {
    if (!isset($_GET['id'])) {
        throw new Exception('Brak ID subdomeny.');
    }
    
    $subdomain_id = (int)$_GET['id'];
    
    if ($subdomain_id <= 0) {
        throw new Exception('Nieprawidłowe ID subdomeny.');
    }
    
    $subdomain = new Subdomain();
    
    // Pobierz szczegóły subdomeny
    $subdomain_details = $subdomain->getSubdomainById($subdomain_id, $_SESSION['user_id']);
    
    if (!$subdomain_details) {
        throw new Exception('Subdomena nie została znaleziona lub nie masz do niej dostępu.');
    }
    
    echo json_encode([
        'success' => true,
        'subdomain' => $subdomain_details
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>