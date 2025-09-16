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
    $subdomain_name = sanitize_input($_POST['subdomain_name'] ?? '');
    $subdomain_type = sanitize_input($_POST['subdomain_type'] ?? '');
    $target_ip = sanitize_input($_POST['target_ip'] ?? '');
    
    // Walidacja podstawowa
    if (empty($subdomain_name)) {
        throw new Exception('Nazwa subdomeny jest wymagana.');
    }
    
    if (!in_array($subdomain_type, ['redirect', 'hosted'])) {
        throw new Exception('Nieprawidłowy typ subdomeny.');
    }
    
    if ($subdomain_type === 'redirect' && empty($target_ip)) {
        throw new Exception('Adres IP jest wymagany dla przekierowań.');
    }
    
    // Typ subdomeny jest już zgodny z bazą danych
    $mapped_type = $subdomain_type;
    
    // Walidacja nazwy subdomeny
    if (!preg_match('/^[a-z0-9-]{3,30}$/', $subdomain_name)) {
        throw new Exception('Nazwa subdomeny może zawierać tylko małe litery, cyfry i myślniki (3-30 znaków).');
    }
    
    // Walidacja IP (jeśli podane)
    if ($subdomain_type === 'redirect' && !filter_var($target_ip, FILTER_VALIDATE_IP)) {
        throw new Exception('Nieprawidłowy format adresu IP.');
    }
    
    $subdomain = new Subdomain();
    
    // Sprawdź dostępność
    if (!$subdomain->isAvailable($subdomain_name)) {
        throw new Exception('Ta subdomena jest już zajęta.');
    }
    
    // Sprawdź limit użytkownika
    $user_subdomains = $subdomain->getUserSubdomains($_SESSION['user_id']);
    $max_subdomains = $subdomain->getMaxSubdomainsPerUser();
    
    if (count($user_subdomains) >= $max_subdomains) {
        throw new Exception('Osiągnąłeś maksymalną liczbę subdomen (' . $max_subdomains . ').');
    }
    
    // Utwórz subdomenę
    $result = $subdomain->create(
        $_SESSION['user_id'],
        $subdomain_name,
        $mapped_type,
        $subdomain_type === 'redirect' ? $target_ip : null
    );
    
    if ($result['success']) {
        // Loguj aktywność
        log_activity($_SESSION['user_id'], 'subdomain_created', 'Utworzono subdomenę: ' . $subdomain_name);
        
        echo json_encode([
            'success' => true,
            'message' => 'Subdomena została utworzona pomyślnie!',
            'subdomain_id' => $result['subdomain_id']
        ]);
    } else {
        throw new Exception($result['message'] ?? 'Nie udało się utworzyć subdomeny.');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>