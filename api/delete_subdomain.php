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
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metoda nie dozwolona.']);
    exit;
}

try {
    // Pobierz dane JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['id'])) {
        throw new Exception('Nieprawidłowe dane wejściowe.');
    }
    
    $subdomain_id = (int)$input['id'];
    
    if ($subdomain_id <= 0) {
        throw new Exception('Nieprawidłowe ID subdomeny.');
    }
    
    $subdomain = new Subdomain();
    
    // Sprawdź czy subdomena należy do użytkownika
    $subdomain_details = $subdomain->getSubdomainDetails($subdomain_id);
    
    if (!$subdomain_details) {
        throw new Exception('Subdomena nie została znaleziona.');
    }
    
    if ($subdomain_details['user_id'] != $_SESSION['user_id'] && !is_admin()) {
        throw new Exception('Nie masz uprawnień do usunięcia tej subdomeny.');
    }
    
    // Usuń subdomenę
    $result = $subdomain->deleteSubdomain($subdomain_id);
    
    if ($result['success']) {
        // Loguj aktywność
        log_activity($_SESSION['user_id'], 'subdomain_deleted', 'Usunięto subdomenę: ' . $subdomain_details['subdomain_name']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Subdomena została usunięta pomyślnie!'
        ]);
    } else {
        throw new Exception($result['message'] ?? 'Nie udało się usunąć subdomeny.');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>