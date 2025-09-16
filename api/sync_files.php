<?php
require_once '../config/config.php';

// Sprawdź czy użytkownik jest zalogowany i jest administratorem
if (!is_logged_in() || !is_admin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Brak uprawnień.']);
    exit;
}

try {
    $db = new DatabaseManager();
    $synced_files = [];
    $errors = [];
    
    // Pobierz wszystkie hostowane subdomeny
    $subdomains = $db->select(
        "SELECT id, subdomain_name FROM subdomains WHERE subdomain_type = 'hosted' AND status = 'active'"
    );
    
    foreach ($subdomains as $subdomain) {
        $subdomain_path = UPLOADS_PATH . '/subdomains/' . $subdomain['subdomain_name'];
        
        if (!is_dir($subdomain_path)) {
            continue;
        }
        
        // Skanuj katalog subdomeny
        $files = scandir($subdomain_path);
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $file_path = $subdomain_path . '/' . $file;
            
            // Sprawdź czy to plik (nie katalog)
            if (!is_file($file_path)) {
                continue;
            }
            
            // Sprawdź czy plik już istnieje w bazie
            $existing_file = $db->selectOne(
                "SELECT id FROM subdomain_files WHERE subdomain_id = ? AND file_path = ?",
                [$subdomain['id'], $file]
            );
            
            if (!$existing_file) {
                // Dodaj plik do bazy danych
                $file_size = filesize($file_path);
                $file_extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                $is_main_file = (strtolower($file) === 'index.html');
                
                try {
                    $db->execute(
                        "INSERT INTO subdomain_files (subdomain_id, file_name, file_path, file_size, file_type, is_main_file, uploaded_at) VALUES (?, ?, ?, ?, ?, ?, NOW())",
                        [$subdomain['id'], $file, $file, $file_size, $file_extension, $is_main_file]
                    );
                    
                    $synced_files[] = [
                        'subdomain' => $subdomain['subdomain_name'],
                        'file' => $file,
                        'size' => $file_size
                    ];
                    
                } catch (Exception $e) {
                    $errors[] = [
                        'subdomain' => $subdomain['subdomain_name'],
                        'file' => $file,
                        'error' => $e->getMessage()
                    ];
                }
            }
        }
    }
    
    // Sprawdź także czy w bazie są pliki, które nie istnieją na dysku
    $orphaned_files = [];
    $all_db_files = $db->select(
        "SELECT sf.*, s.subdomain_name FROM subdomain_files sf JOIN subdomains s ON sf.subdomain_id = s.id WHERE s.subdomain_type = 'hosted'"
    );
    
    foreach ($all_db_files as $db_file) {
        $file_path = UPLOADS_PATH . '/subdomains/' . $db_file['subdomain_name'] . '/' . $db_file['file_path'];
        
        if (!file_exists($file_path)) {
            $orphaned_files[] = [
                'id' => $db_file['id'],
                'subdomain' => $db_file['subdomain_name'],
                'file' => $db_file['file_path']
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Synchronizacja zakończona',
        'synced_files' => $synced_files,
        'synced_count' => count($synced_files),
        'errors' => $errors,
        'error_count' => count($errors),
        'orphaned_files' => $orphaned_files,
        'orphaned_count' => count($orphaned_files)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Błąd synchronizacji: ' . $e->getMessage()
    ]);
}
?>