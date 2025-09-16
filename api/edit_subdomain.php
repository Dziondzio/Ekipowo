<?php
require_once '../config/config.php';
require_once '../classes/Subdomain.php';

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
    if (!isset($_POST['action']) || $_POST['action'] !== 'update') {
        throw new Exception('Nieprawidłowa akcja.');
    }
    
    if (!isset($_POST['subdomain_id'])) {
        throw new Exception('Brak ID subdomeny.');
    }
    
    $subdomain_id = (int)$_POST['subdomain_id'];
    
    if ($subdomain_id <= 0) {
        throw new Exception('Nieprawidłowe ID subdomeny.');
    }
    
    $subdomain = new Subdomain();
    $db = new DatabaseManager();
    
    // Sprawdź czy subdomena należy do użytkownika
    $subdomain_details = $subdomain->getSubdomainById($subdomain_id, $_SESSION['user_id']);
    
    if (!$subdomain_details) {
        throw new Exception('Subdomena nie została znaleziona lub nie masz do niej dostępu.');
    }
    
    // Sprawdź czy to subdomena typu redirect (tylko te można edytować)
    if ($subdomain_details['subdomain_type'] === 'hosted') {
        throw new Exception('Nie można edytować subdomen typu hosting.');
    }
    
    if (!isset($_POST['target_ip']) || empty($_POST['target_ip'])) {
        throw new Exception('Adres IP jest wymagany.');
    }
    
    $target_ip = sanitize_input($_POST['target_ip']);
    
    // Walidacja IP
    if (!filter_var($target_ip, FILTER_VALIDATE_IP)) {
        throw new Exception('Nieprawidłowy format adresu IP.');
    }
    
    // Aktualizuj IP w bazie danych
    try {
        $result = $db->execute("UPDATE subdomains SET target_ip = ? WHERE id = ?", [$target_ip, $subdomain_id]);
        if (!$result) {
            throw new Exception('Nie udało się zaktualizować subdomeny w bazie danych.');
        }
    } catch (Exception $e) {
        error_log('Database update error in edit_subdomain.php: ' . $e->getMessage());
        throw new Exception('Błąd podczas aktualizacji subdomeny: ' . $e->getMessage());
    }
    
    // Aktualizuj rekord DNS w Cloudflare
    try {
        $cloudflare = new CloudflareAPI();
        $cloudflare->updateRecord($subdomain_details['subdomain_name'], $target_ip);
    } catch (Exception $e) {
        // Loguj błąd ale nie przerywaj operacji
        error_log('Cloudflare update error: ' . $e->getMessage());
    }
    
    // Loguj aktywność
    log_activity($_SESSION['user_id'], 'subdomain_updated', 'Zaktualizowano subdomenę: ' . $subdomain_details['subdomain_name'] . ' (nowe IP: ' . $target_ip . ')');
    
    echo json_encode([
        'success' => true,
        'message' => 'Subdomena została zaktualizowana.'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>