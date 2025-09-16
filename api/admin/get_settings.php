<?php
require_once '../../config/config.php';

header('Content-Type: application/json');

// Sprawdź czy użytkownik jest zalogowany i jest administratorem
if (!is_logged_in() || !is_admin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Brak uprawnień.']);
    exit;
}

try {
    $db = new DatabaseManager();
    
    // Pobierz ustawienia z bazy danych
    $settings_raw = $db->select("SELECT setting_key, setting_value FROM system_settings");
    
    $settings = [];
    foreach ($settings_raw as $setting) {
        $settings[$setting['setting_key']] = $setting['setting_value'];
    }
    
    // Dodaj domyślne wartości jeśli nie istnieją
    $default_settings = [
        'max_subdomains_per_user' => 5,
        'max_file_size' => MAX_FILE_SIZE,
        'cf_zone_id' => '',
        'cf_api_token' => ''
    ];
    
    foreach ($default_settings as $key => $default_value) {
        if (!isset($settings[$key])) {
            $settings[$key] = $default_value;
        }
    }
    
    echo json_encode([
        'success' => true,
        'settings' => $settings
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Błąd podczas pobierania ustawień: ' . $e->getMessage()
    ]);
}
?>