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
    $subdomain_id = (int)($_POST['subdomain_id'] ?? 0);
    
    if ($subdomain_id <= 0) {
        throw new Exception('Nieprawidłowe ID subdomeny.');
    }
    
    $db = new DatabaseManager();
    $subdomain_obj = new Subdomain();
    
    // Sprawdź czy subdomena istnieje
    $subdomain = $db->selectOne("SELECT * FROM subdomains WHERE id = ?", [$subdomain_id]);
    if (!$subdomain) {
        throw new Exception('Subdomena nie została znaleziona.');
    }
    
    switch ($action) {
        case 'update':
            $target_ip = trim($_POST['target_ip'] ?? '');
            
            // Tylko subdomeny typu redirect mogą mieć zmieniane IP
            if ($subdomain['subdomain_type'] === 'redirect') {
                if (empty($target_ip)) {
                    throw new Exception('Docelowe IP jest wymagane dla subdomen typu przekierowanie.');
                }
                
                if (!filter_var($target_ip, FILTER_VALIDATE_IP)) {
                    throw new Exception('Nieprawidłowy format adresu IP.');
                }
                
                // Aktualizuj IP w bazie danych
                $db->execute("UPDATE subdomains SET target_ip = ? WHERE id = ?", [$target_ip, $subdomain_id]);
                
                // Aktualizuj rekord DNS w Cloudflare
                try {
                    $cloudflare = new CloudflareAPI();
                    $cloudflare->updateRecord($subdomain['subdomain_name'], $target_ip);
                } catch (Exception $e) {
                    // Loguj błąd ale nie przerywaj operacji
                    error_log('Cloudflare update error: ' . $e->getMessage());
                }
                
                // Loguj aktywność
                log_activity($_SESSION['user_id'], 'subdomain_updated', 'Zaktualizowano subdomenę: ' . $subdomain['subdomain_name'] . ' (nowe IP: ' . $target_ip . ')');
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Subdomena została zaktualizowana.'
                ]);
            } else {
                throw new Exception('Nie można edytować subdomen typu hosting.');
            }
            break;
            
        case 'delete':
            // Rozpocznij transakcję
            $db->beginTransaction();
            
            try {
                // Usuń pliki jeśli to subdomena hostowana
                if ($subdomain['type'] === 'hosted') {
                    $files = $db->select("SELECT * FROM subdomain_files WHERE subdomain_id = ?", [$subdomain_id]);
                    
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
                    
                    // Usuń rekordy plików z bazy
                    $db->execute("DELETE FROM subdomain_files WHERE subdomain_id = ?", [$subdomain_id]);
                }
                
                // Usuń rekord DNS z Cloudflare
                try {
                    $cloudflare = new CloudflareAPI();
                    $cloudflare->deleteRecord($subdomain['subdomain_name']);
                } catch (Exception $e) {
                    // Loguj błąd ale nie przerywaj operacji
                    error_log('Cloudflare delete error: ' . $e->getMessage());
                }
                
                // Usuń subdomenę z bazy danych
                $db->execute("DELETE FROM subdomains WHERE id = ?", [$subdomain_id]);
                
                // Zatwierdź transakcję
                $db->commit();
                
                // Loguj aktywność
                log_activity($_SESSION['user_id'], 'subdomain_deleted', 'Usunięto subdomenę: ' . $subdomain['subdomain_name']);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Subdomena została usunięta.'
                ]);
                
            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
            break;
            
        case 'toggle_status':
            // Przełącz status aktywności subdomeny (jeśli taka funkcja będzie potrzebna)
            $new_status = $subdomain['is_active'] ? 0 : 1;
            $db->execute("UPDATE subdomains SET is_active = ? WHERE id = ?", [$new_status, $subdomain_id]);
            
            // Loguj aktywność
            $status_text = $new_status ? 'aktywowano' : 'dezaktywowano';
            log_activity($_SESSION['user_id'], 'subdomain_status_changed', 'Zmieniono status subdomeny: ' . $subdomain['subdomain_name'] . ' (' . $status_text . ')');
            
            echo json_encode([
                'success' => true,
                'message' => 'Status subdomeny został zmieniony.'
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