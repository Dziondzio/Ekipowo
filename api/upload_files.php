<?php
// Nagłówki dla AJAX
header('Access-Control-Allow-Credentials: true');
header('Cache-Control: no-cache, must-revalidate');
header('Content-Type: application/json');

require_once '../config/config.php';

// Funkcja do synchronizacji plików dla konkretnej subdomeny
function syncSubdomainFiles($subdomain_id, $subdomain_name) {
    $db = new DatabaseManager();
    $synced_files = [];
    $errors = [];
    
    $subdomain_path = UPLOADS_PATH . '/subdomains/' . $subdomain_name;
    
    if (!is_dir($subdomain_path)) {
        return ['synced_count' => 0, 'errors' => ['Katalog subdomeny nie istnieje']];
    }
    
    // Funkcja rekurencyjna do skanowania katalogów
    function scanDirectory($dir, $base_path, $subdomain_id, $db, &$synced_files, &$errors) {
        $files = scandir($dir);
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $file_path = $dir . '/' . $file;
            $relative_path = str_replace($base_path . '/', '', $file_path);
            
            if (is_dir($file_path)) {
                // Rekurencyjnie skanuj podkatalogi
                scanDirectory($file_path, $base_path, $subdomain_id, $db, $synced_files, $errors);
            } else {
                // Sprawdź czy plik już istnieje w bazie
                $existing_file = $db->selectOne(
                    "SELECT id FROM subdomain_files WHERE subdomain_id = ? AND file_path = ?",
                    [$subdomain_id, $relative_path]
                );
                
                if (!$existing_file) {
                    // Dodaj plik do bazy danych
                    $file_size = filesize($file_path);
                    $file_extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    $is_main_file = (strtolower(basename($relative_path)) === 'index.html');
                    
                    try {
                        $db->execute(
                            "INSERT INTO subdomain_files (subdomain_id, file_name, file_path, file_size, file_type, is_main_file, uploaded_at) VALUES (?, ?, ?, ?, ?, ?, NOW())",
                            [$subdomain_id, basename($file), $relative_path, $file_size, $file_extension, $is_main_file]
                        );
                        
                        $synced_files[] = $relative_path;
                        
                    } catch (Exception $e) {
                        $errors[] = 'Błąd synchronizacji pliku ' . $relative_path . ': ' . $e->getMessage();
                    }
                }
            }
        }
    }
    
    // Skanuj katalog subdomeny
    scanDirectory($subdomain_path, $subdomain_path, $subdomain_id, $db, $synced_files, $errors);
    
    return [
        'synced_count' => count($synced_files),
        'synced_files' => $synced_files,
        'errors' => $errors
    ];
}

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
    // Debug logging
    error_log('=== UPLOAD DEBUG START ===');
    error_log('POST data: ' . print_r($_POST, true));
    error_log('FILES data: ' . print_r($_FILES, true));
    
    $subdomain_name = sanitize_input($_POST['subdomain'] ?? $_POST['subdomain_name'] ?? '');
    $upload_path = trim($_POST['upload_path'] ?? ''); // Nie używaj sanitize_input dla ścieżek plików
    
    error_log('Subdomain name: ' . $subdomain_name);
    error_log('Upload path: ' . $upload_path);
    
    if (empty($subdomain_name)) {
        throw new Exception('Nazwa subdomeny jest wymagana.');
    }
    
    // Sprawdź czy subdomena należy do użytkownika
    error_log('Checking subdomain ownership...');
    $subdomain = new Subdomain();
    $user_subdomains = $subdomain->getUserSubdomains($_SESSION['user_id']);
    error_log('User subdomains: ' . print_r($user_subdomains, true));
    $current_subdomain = null;
    
    foreach ($user_subdomains as $sub) {
        if ($sub['subdomain_name'] === $subdomain_name && $sub['subdomain_type'] === 'hosted') {
            $current_subdomain = $sub;
            break;
        }
    }
    
    error_log('Current subdomain: ' . print_r($current_subdomain, true));
    
    if (!$current_subdomain) {
        throw new Exception('Subdomena nie została znaleziona lub nie jest hostowana.');
    }
    
    // Sprawdź czy przesłano pliki
    error_log('Checking uploaded files...');
    if (!isset($_FILES['files'])) {
        error_log('ERROR: No files data in request');
        throw new Exception('Brak danych o plikach w żądaniu.');
    }
    
    if (empty($_FILES['files']['name']) || empty($_FILES['files']['name'][0])) {
        error_log('ERROR: No files selected');
        throw new Exception('Nie wybrano żadnych plików.');
    }
    
    error_log('Files validation passed, proceeding with upload...');
    

    
    $subdomain_path = UPLOADS_PATH . '/subdomains/' . $subdomain_name;
    
    // Utwórz katalog jeśli nie istnieje
    if (!is_dir($subdomain_path)) {
        if (!mkdir($subdomain_path, 0755, true)) {
            throw new Exception('Nie udało się utworzyć katalogu subdomeny.');
        }
    }
    
    $uploaded_files = [];
    
    try {
        $db = new DatabaseManager();
        error_log('Database connection established successfully');
    } catch (Exception $e) {
        error_log('Database connection failed: ' . $e->getMessage());
        throw new Exception('Nie udało się połączyć z bazą danych: ' . $e->getMessage());
    }
    
    // Przetwórz każdy plik
    for ($i = 0; $i < count($_FILES['files']['name']); $i++) {
        $file_name = $_FILES['files']['name'][$i];
        $file_tmp = $_FILES['files']['tmp_name'][$i];
        $file_size = $_FILES['files']['size'][$i];
        $file_error = $_FILES['files']['error'][$i];
        
        // Sprawdź błędy uploadu
        if ($file_error !== UPLOAD_ERR_OK) {
            $error_messages = [
                UPLOAD_ERR_INI_SIZE => 'Plik przekracza maksymalny rozmiar określony w php.ini',
                UPLOAD_ERR_FORM_SIZE => 'Plik przekracza maksymalny rozmiar określony w formularzu',
                UPLOAD_ERR_PARTIAL => 'Plik został wgrany tylko częściowo',
                UPLOAD_ERR_NO_FILE => 'Nie wybrano pliku',
                UPLOAD_ERR_NO_TMP_DIR => 'Brak katalogu tymczasowego',
                UPLOAD_ERR_CANT_WRITE => 'Nie można zapisać pliku na dysk',
                UPLOAD_ERR_EXTENSION => 'Upload zatrzymany przez rozszerzenie PHP'
            ];
            $error_msg = $error_messages[$file_error] ?? 'Nieznany błąd uploadu';
            throw new Exception('Błąd podczas wgrywania pliku "' . $file_name . '": ' . $error_msg);
        }
        
        // Sprawdź rozmiar pliku
        if ($file_size > MAX_FILE_SIZE) {
            throw new Exception('Plik ' . $file_name . ' jest za duży. Maksymalny rozmiar: ' . format_file_size(MAX_FILE_SIZE));
        }
        
        // Sprawdź rozszerzenie pliku
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        if (!in_array($file_extension, ALLOWED_FILE_TYPES)) {
            throw new Exception('Niedozwolony typ pliku: ' . $file_name . '. Dozwolone typy: ' . implode(', ', ALLOWED_FILE_TYPES));
        }
        
        // Sanityzuj nazwę pliku
        $safe_file_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file_name);
        
        // Określ ścieżkę docelową
        $target_path = $upload_path ? trim($upload_path, '/') . '/' : '';
        $full_file_path = $target_path . $safe_file_name;
        $full_system_path = $subdomain_path . '/' . $full_file_path;
        

        
        // Utwórz katalogi jeśli potrzeba
        $target_dir = dirname($full_system_path);
        if (!is_dir($target_dir)) {
            if (!mkdir($target_dir, 0755, true)) {
                throw new Exception('Nie udało się utworzyć katalogu: ' . $target_dir);
            }
        }
        
        // Przenieś plik
        if (!move_uploaded_file($file_tmp, $full_system_path)) {
            // Dodatkowe informacje o błędzie
            $error_info = [];
            if (!is_uploaded_file($file_tmp)) {
                $error_info[] = 'Plik nie został poprawnie wgrany';
            }
            if (!is_writable(dirname($full_system_path))) {
                $error_info[] = 'Brak uprawnień do zapisu w katalogu: ' . dirname($full_system_path);
            }
            if (!file_exists($file_tmp)) {
                $error_info[] = 'Plik tymczasowy nie istnieje: ' . $file_tmp;
            }
            $error_details = empty($error_info) ? '' : ' (' . implode(', ', $error_info) . ')';
            throw new Exception('Nie udało się zapisać pliku: ' . $file_name . $error_details);
        }
        
        // Sprawdź czy plik już istnieje w bazie
        error_log('Checking if file exists in database: ' . $full_file_path);
        try {
            $existing_file = $db->selectOne(
                "SELECT id FROM subdomain_files WHERE subdomain_id = ? AND file_path = ?",
                [$current_subdomain['id'], $full_file_path]
            );
            error_log('File check completed successfully');
        } catch (Exception $e) {
            error_log('Database error during file check: ' . $e->getMessage());
            throw $e;
        }
        
        // Sprawdź czy to główny plik index.html
        $is_main_file = (strtolower($safe_file_name) === 'index.html') ? 1 : 0;
        
        if ($existing_file) {
            // Aktualizuj istniejący rekord
            error_log('Updating existing file record: ' . $existing_file['id']);
            try {
                $db->execute(
                    "UPDATE subdomain_files SET file_name = ?, file_size = ?, file_type = ?, is_main_file = ?, uploaded_at = NOW() WHERE id = ?",
                    [$safe_file_name, $file_size, $file_extension, $is_main_file, $existing_file['id']]
                );
                error_log('File record updated successfully');
            } catch (Exception $e) {
                error_log('Database error during file update: ' . $e->getMessage());
                throw $e;
            }
        } else {
            // Dodaj nowy rekord
            error_log('Inserting new file record');
            try {
                $db->execute(
                    "INSERT INTO subdomain_files (subdomain_id, file_name, file_path, file_size, file_type, is_main_file, uploaded_at) VALUES (?, ?, ?, ?, ?, ?, NOW())",
                    [$current_subdomain['id'], $safe_file_name, $full_file_path, $file_size, $file_extension, $is_main_file]
                );
                error_log('File record inserted successfully');
            } catch (Exception $e) {
                error_log('Database error during file insert: ' . $e->getMessage());
                throw $e;
            }
        }
        
        $uploaded_files[] = $full_file_path;
    }
    
    // Automatyczna synchronizacja plików po uploadzie
    $sync_result = syncSubdomainFiles($current_subdomain['id'], $subdomain_name);
    
    // Loguj aktywność
    log_activity($_SESSION['user_id'], 'files_uploaded', 'Wgrano pliki dla subdomeny: ' . $subdomain_name . ' (' . count($uploaded_files) . ' plików)');
    
    error_log('Upload completed successfully. Files: ' . print_r($uploaded_files, true));
    error_log('Sync result: ' . print_r($sync_result, true));
    error_log('=== UPLOAD DEBUG END ===');
    
    // Przekieruj z komunikatem sukcesu
    $_SESSION['flash_message'] = [
        'type' => 'success',
        'message' => 'Pliki zostały wgrane pomyślnie!'
    ];
    header('Location: /dashboard.php?section=files');
    exit;
    
} catch (Exception $e) {
    error_log('Upload error: ' . $e->getMessage());
    error_log('Error trace: ' . $e->getTraceAsString());
    error_log('=== UPLOAD DEBUG END (ERROR) ===');
    
    // Przekieruj z komunikatem błędu
    $_SESSION['flash_message'] = [
        'type' => 'error',
        'message' => $e->getMessage()
    ];
    header('Location: /dashboard.php?section=files');
    exit;
}
?>