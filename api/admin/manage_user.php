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
    $action = $_POST['action'] ?? '';
    $user_id = (int)($_POST['user_id'] ?? 0);
    
    if ($user_id <= 0) {
        throw new Exception('Nieprawidłowe ID użytkownika.');
    }
    
    // Sprawdź czy użytkownik nie próbuje modyfikować samego siebie
    if ($user_id == $_SESSION['user_id']) {
        throw new Exception('Nie możesz modyfikować własnego konta.');
    }
    
    $db = new DatabaseManager();
    
    // Sprawdź czy użytkownik istnieje
    $user = $db->selectOne("SELECT * FROM users WHERE id = ?", [$user_id]);
    if (!$user) {
        throw new Exception('Użytkownik nie został znaleziony.');
    }
    
    switch ($action) {
        case 'verify':
            // Zweryfikuj użytkownika
            $db->execute("UPDATE users SET is_verified = 1 WHERE id = ?", [$user_id]);
            
            // Loguj aktywność
            log_activity($_SESSION['user_id'], 'user_verified', 'Zweryfikowano użytkownika: ' . $user['username']);
            
            echo json_encode([
                'success' => true,
                'message' => 'Użytkownik został zweryfikowany.'
            ]);
            break;
            
        case 'toggle_admin':
            $make_admin = (int)($_POST['make_admin'] ?? 0);
            
            // Aktualizuj status administratora
            $db->execute("UPDATE users SET is_admin = ? WHERE id = ?", [$make_admin, $user_id]);
            
            // Loguj aktywność
            $action_desc = $make_admin ? 'Nadano uprawnienia administratora' : 'Odebrano uprawnienia administratora';
            log_activity($_SESSION['user_id'], 'admin_rights_changed', $action_desc . ' użytkownikowi: ' . $user['username']);
            
            echo json_encode([
                'success' => true,
                'message' => 'Uprawnienia zostały zaktualizowane.'
            ]);
            break;
            
        case 'delete':
            // Rozpocznij transakcję
            $db->beginTransaction();
            
            try {
                // Pobierz subdomeny użytkownika
                $subdomains = $db->select("SELECT * FROM subdomains WHERE user_id = ?", [$user_id]);
                
                foreach ($subdomains as $subdomain) {
                    // Usuń pliki subdomeny
                    $files = $db->select("SELECT * FROM subdomain_files WHERE subdomain_id = ?", [$subdomain['id']]);
                    
                    foreach ($files as $file) {
                        $file_path = UPLOADS_PATH . '/subdomains/' . $subdomain['subdomain_name'] . '/' . $file['file_path'];
                        if (file_exists($file_path)) {
                            unlink($file_path);
                        }
                    }
                    
                    // Usuń katalog subdomeny
                    $subdomain_dir = UPLOADS_PATH . '/subdomains/' . $subdomain['subdomain_name'];
                    if (is_dir($subdomain_dir)) {
                        rmdir($subdomain_dir);
                    }
                    
                    // Usuń rekord DNS z Cloudflare
                    try {
                        $cloudflare = new CloudflareAPI();
                        $cloudflare->deleteRecord($subdomain['subdomain_name']);
                    } catch (Exception $e) {
                        // Ignoruj błędy Cloudflare przy usuwaniu użytkownika
                    }
                }
                
                // Usuń rekordy z bazy danych
                $db->execute("DELETE FROM subdomain_files WHERE subdomain_id IN (SELECT id FROM subdomains WHERE user_id = ?)", [$user_id]);
                $db->execute("DELETE FROM subdomains WHERE user_id = ?", [$user_id]);
                $db->execute("DELETE FROM activity_logs WHERE user_id = ?", [$user_id]);
                $db->execute("DELETE FROM user_sessions WHERE user_id = ?", [$user_id]);
                $db->execute("DELETE FROM users WHERE id = ?", [$user_id]);
                
                // Zatwierdź transakcję
                $db->commit();
                
                // Loguj aktywność
                log_activity($_SESSION['user_id'], 'user_deleted', 'Usunięto użytkownika: ' . $user['username']);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Użytkownik został usunięty.'
                ]);
                
            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
            break;
            
        case 'reset_password':
            // Wygeneruj nowe hasło
            $new_password = bin2hex(random_bytes(8)); // 16-znakowe hasło
            $hashed_password = hash_password($new_password);
            
            // Aktualizuj hasło
            $db->execute("UPDATE users SET password = ? WHERE id = ?", [$hashed_password, $user_id]);
            
            // Loguj aktywność
            log_activity($_SESSION['user_id'], 'password_reset', 'Zresetowano hasło użytkownika: ' . $user['username']);
            
            echo json_encode([
                'success' => true,
                'message' => 'Hasło zostało zresetowane.',
                'new_password' => $new_password
            ]);
            break;
            
        default:
            throw new Exception('Nieznana akcja.');
    }
    
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