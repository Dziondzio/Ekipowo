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
    
    // Pobierz wszystkie pliki z informacjami o subdomenach i użytkownikach
    $files = $db->select("
        SELECT 
            f.id,
            f.file_name,
            f.file_size,
            f.uploaded_at,
            s.subdomain_name,
            s.subdomain_type,
            u.username,
            u.email
        FROM subdomain_files f
        JOIN subdomains s ON f.subdomain_id = s.id
        JOIN users u ON s.user_id = u.id
        ORDER BY f.uploaded_at DESC
    ");
    
    // Generuj HTML tabeli
    $html = '<div class="overflow-x-auto">';
    $html .= '<table class="min-w-full bg-white border border-gray-200">';
    $html .= '<thead class="bg-gray-50">';
    $html .= '<tr>';
    $html .= '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Plik</th>';
    $html .= '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rozmiar</th>';
    $html .= '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subdomena</th>';
    $html .= '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Właściciel</th>';
    $html .= '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data wgrania</th>';
    $html .= '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Akcje</th>';
    $html .= '</tr>';
    $html .= '</thead>';
    $html .= '<tbody class="bg-white divide-y divide-gray-200">';
    
    if (empty($files)) {
        $html .= '<tr><td colspan="6" class="px-6 py-4 text-center text-gray-500">Brak plików w systemie.</td></tr>';
    } else {
        foreach ($files as $file) {
            $file_extension = strtolower(pathinfo($file['file_name'], PATHINFO_EXTENSION));
            $icon_class = get_file_icon($file_extension);
            
            $html .= '<tr>';
            $html .= '<td class="px-6 py-4 whitespace-nowrap">';
            $html .= '<div class="flex items-center">';
            $html .= '<i class="' . $icon_class . ' text-blue-500 mr-2"></i>';
            $html .= '<span class="text-sm font-medium text-gray-900">' . htmlspecialchars($file['file_name']) . '</span>';
            $html .= '</div>';
            $html .= '</td>';
            $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' . format_file_size($file['file_size']) . '</td>';
            $html .= '<td class="px-6 py-4 whitespace-nowrap">';
            $html .= '<div class="text-sm text-gray-900">' . htmlspecialchars($file['subdomain_name']) . '</div>';
            $html .= '<div class="text-sm text-gray-500">' . ucfirst($file['subdomain_type']) . '</div>';
            $html .= '</td>';
            $html .= '<td class="px-6 py-4 whitespace-nowrap">';
            $html .= '<div class="text-sm text-gray-900">' . htmlspecialchars($file['username']) . '</div>';
            $html .= '<div class="text-sm text-gray-500">' . htmlspecialchars($file['email']) . '</div>';
            $html .= '</td>';
            $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' . date('d.m.Y H:i', strtotime($file['uploaded_at'])) . '</td>';
            $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm font-medium">';
            $html .= '<button onclick="downloadFile(' . $file['id'] . ')" class="text-blue-600 hover:text-blue-900 mr-3">';
            $html .= '<i class="fas fa-download"></i> Pobierz';
            $html .= '</button>';
            $html .= '<button onclick="deleteFile(' . $file['id'] . ')" class="text-red-600 hover:text-red-900">';
            $html .= '<i class="fas fa-trash"></i> Usuń';
            $html .= '</button>';
            $html .= '</td>';
            $html .= '</tr>';
        }
    }
    
    $html .= '</tbody>';
    $html .= '</table>';
    $html .= '</div>';
    
    // Dodaj JavaScript do obsługi akcji
    $html .= '<script>';
    $html .= 'function downloadFile(fileId) {';
    $html .= '    window.open("/api/download_file.php?id=" + fileId, "_blank");';
    $html .= '}';
    $html .= '';
    $html .= 'function deleteFile(fileId) {';
    $html .= '    if (confirm("Czy na pewno chcesz usunąć ten plik?")) {';
    $html .= '        fetch("/api/admin/manage_file.php", {';
    $html .= '            method: "POST",';
    $html .= '            headers: {';
    $html .= '                "Content-Type": "application/x-www-form-urlencoded"';
    $html .= '            },';
    $html .= '            body: "action=delete&file_id=" + fileId';
    $html .= '        })';
    $html .= '        .then(response => response.json())';
    $html .= '        .then(data => {';
    $html .= '            if (data.success) {';
    $html .= '                showNotification("Plik został usunięty pomyślnie.", "success");';
    $html .= '                loadFiles();';
    $html .= '            } else {';
    $html .= '                showNotification("Błąd: " + data.message, "error");';
    $html .= '            }';
    $html .= '        })';
    $html .= '        .catch(error => {';
    $html .= '            console.error("Error:", error);';
    $html .= '            showNotification("Wystąpił błąd podczas usuwania pliku.", "error");';
    $html .= '        });';
    $html .= '    }';
    $html .= '}';
    $html .= '</script>';
    
    echo $html;
    
} catch (Exception $e) {
    error_log("Error in get_files.php: " . $e->getMessage());
    echo '<div class="text-center py-8">';
    echo '<i class="fas fa-exclamation-triangle text-red-400 text-3xl mb-2"></i>';
    echo '<p class="text-red-500">Błąd podczas ładowania plików</p>';
    echo '<p class="text-sm text-gray-500">' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '</div>';
}

// Funkcja do określania ikony pliku
function get_file_icon($extension) {
    $icons = [
        'html' => 'fab fa-html5',
        'css' => 'fab fa-css3-alt',
        'js' => 'fab fa-js-square',
        'json' => 'fas fa-code',
        'xml' => 'fas fa-code',
        'txt' => 'fas fa-file-alt',
        'md' => 'fab fa-markdown',
        'php' => 'fab fa-php',
        'jpg' => 'fas fa-image',
        'jpeg' => 'fas fa-image',
        'png' => 'fas fa-image',
        'gif' => 'fas fa-image',
        'svg' => 'fas fa-image',
        'pdf' => 'fas fa-file-pdf',
        'zip' => 'fas fa-file-archive',
        'rar' => 'fas fa-file-archive'
    ];
    
    return isset($icons[$extension]) ? $icons[$extension] : 'fas fa-file';
}
?>