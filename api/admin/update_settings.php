<?php
require_once '../../config/config.php';

header('Content-Type: application/json');

// Sprawdź czy użytkownik jest zalogowany i jest administratorem
if (!is_logged_in() || !is_admin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Brak uprawnień.']);
    exit;
}

// Sprawdź metodę HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metoda nie dozwolona.']);
    exit;
}

try {
    $db = new DatabaseManager();
    
    // Rozpocznij transakcję
    $db->beginTransaction();
    
    $updated_settings = [];
    
    // Ustawienia Cloudflare
    if (isset($_POST['cf_api_token'])) {
        $cf_api_token = trim($_POST['cf_api_token']);
        if (!empty($cf_api_token)) {
            // Sprawdź czy ustawienie już istnieje
            $existing = $db->selectOne("SELECT id FROM system_settings WHERE setting_key = 'cf_api_token'");
            
            if ($existing) {
                $db->execute("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'cf_api_token'", [$cf_api_token]);
            } else {
                $db->execute("INSERT INTO system_settings (setting_key, setting_value) VALUES ('cf_api_token', ?)", [$cf_api_token]);
            }
            
            $updated_settings[] = 'Cloudflare API Token';
        }
    }
    
    if (isset($_POST['cf_zone_id'])) {
        $cf_zone_id = trim($_POST['cf_zone_id']);
        if (!empty($cf_zone_id)) {
            // Walidacja Zone ID (32 znaki hex)
            if (!preg_match('/^[a-f0-9]{32}$/i', $cf_zone_id)) {
                throw new Exception('Nieprawidłowy format Zone ID Cloudflare.');
            }
            
            // Sprawdź czy ustawienie już istnieje
            $existing = $db->selectOne("SELECT id FROM system_settings WHERE setting_key = 'cf_zone_id'");
            
            if ($existing) {
                $db->execute("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'cf_zone_id'", [$cf_zone_id]);
            } else {
                $db->execute("INSERT INTO system_settings (setting_key, setting_value) VALUES ('cf_zone_id', ?)", [$cf_zone_id]);
            }
            
            $updated_settings[] = 'Cloudflare Zone ID';
        }
    }
    
    // Ustawienia systemu
    if (isset($_POST['max_subdomains'])) {
        $max_subdomains = (int)$_POST['max_subdomains'];
        if ($max_subdomains < 1 || $max_subdomains > 100) {
            throw new Exception('Maksymalna liczba subdomen musi być między 1 a 100.');
        }
        
        // Sprawdź czy ustawienie już istnieje
        $existing = $db->selectOne("SELECT id FROM system_settings WHERE setting_key = 'max_subdomains_per_user'");
        
        if ($existing) {
            $db->execute("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'max_subdomains_per_user'", [$max_subdomains]);
        } else {
            $db->execute("INSERT INTO system_settings (setting_key, setting_value) VALUES ('max_subdomains_per_user', ?)", [$max_subdomains]);
        }
        
        $updated_settings[] = 'Maksymalna liczba subdomen';
    }
    
    if (isset($_POST['max_file_size'])) {
        $max_file_size_mb = (int)$_POST['max_file_size'];
        if ($max_file_size_mb < 1 || $max_file_size_mb > 100) {
            throw new Exception('Maksymalny rozmiar pliku musi być między 1 a 100 MB.');
        }
        
        $max_file_size_bytes = $max_file_size_mb * 1024 * 1024;
        
        // Sprawdź czy ustawienie już istnieje
        $existing = $db->selectOne("SELECT id FROM system_settings WHERE setting_key = 'max_file_size'");
        
        if ($existing) {
            $db->execute("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'max_file_size'", [$max_file_size_bytes]);
        } else {
            $db->execute("INSERT INTO system_settings (setting_key, setting_value) VALUES ('max_file_size', ?)", [$max_file_size_bytes]);
        }
        
        $updated_settings[] = 'Maksymalny rozmiar pliku';
    }
    
    // Zatwierdź transakcję
    $db->commit();
    
    // Loguj aktywność
    if (!empty($updated_settings)) {
        log_activity($_SESSION['user_id'], 'settings_updated', 'Zaktualizowano ustawienia: ' . implode(', ', $updated_settings));
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Ustawienia zostały zapisane pomyślnie.',
        'updated' => $updated_settings
    ]);
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollback();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>