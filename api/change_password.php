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
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Walidacja podstawowa
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        throw new Exception('Wszystkie pola są wymagane.');
    }
    
    if ($new_password !== $confirm_password) {
        throw new Exception('Nowe hasła nie są identyczne.');
    }
    
    if (strlen($new_password) < PASSWORD_MIN_LENGTH) {
        throw new Exception('Nowe hasło musi mieć co najmniej ' . PASSWORD_MIN_LENGTH . ' znaków.');
    }
    
    $user = new User();
    
    // Sprawdź obecne hasło
    $current_user = $user->getUserById($_SESSION['user_id']);
    
    if (!$current_user || !verify_password($current_password, $current_user['password'])) {
        throw new Exception('Obecne hasło jest nieprawidłowe.');
    }
    
    // Zmień hasło
    $result = $user->changePassword($_SESSION['user_id'], $new_password);
    
    if ($result['success']) {
        // Loguj aktywność
        log_activity($_SESSION['user_id'], 'password_changed', 'Zmieniono hasło');
        
        echo json_encode([
            'success' => true,
            'message' => 'Hasło zostało zmienione pomyślnie!'
        ]);
    } else {
        throw new Exception($result['message'] ?? 'Nie udało się zmienić hasła.');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>