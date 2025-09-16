<?php
require_once '../../config/config.php';

// Sprawdź czy użytkownik jest zalogowany i jest administratorem
if (!is_logged_in() || !is_admin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Brak uprawnień']);
    exit;
}

try {
    $db = new DatabaseManager();
    
    // Pobierz logi aktywności z paginacją
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 50;
    $offset = ($page - 1) * $limit;
    
    $logs = $db->select(
        "SELECT al.*, u.username 
         FROM activity_logs al 
         LEFT JOIN users u ON al.user_id = u.id 
         ORDER BY al.created_at DESC 
         LIMIT $limit OFFSET $offset"
    );
    
    // Pobierz całkowitą liczbę logów
    $total_logs = $db->selectOne("SELECT COUNT(*) as count FROM activity_logs")['count'];
    $total_pages = ceil($total_logs / $limit);
    
    if (empty($logs)) {
        echo '<div class="text-center py-8">';
        echo '<i class="fas fa-history text-gray-400 text-3xl mb-2"></i>';
        echo '<p class="text-gray-500">Brak logów aktywności</p>';
        echo '</div>';
        exit;
    }
    
    // Wyświetl logi
    echo '<div class="space-y-3">';
    foreach ($logs as $log) {
        $action_color = 'bg-blue-100 text-blue-800';
        $icon = 'fas fa-info-circle';
        
        // Ustaw kolory i ikony na podstawie akcji
        switch (strtolower($log['action'])) {
            case 'login':
                $action_color = 'bg-green-100 text-green-800';
                $icon = 'fas fa-sign-in-alt';
                break;
            case 'logout':
                $action_color = 'bg-gray-100 text-gray-800';
                $icon = 'fas fa-sign-out-alt';
                break;
            case 'create':
            case 'created':
                $action_color = 'bg-green-100 text-green-800';
                $icon = 'fas fa-plus';
                break;
            case 'edit':
            case 'update':
            case 'updated':
                $action_color = 'bg-yellow-100 text-yellow-800';
                $icon = 'fas fa-edit';
                break;
            case 'delete':
            case 'deleted':
                $action_color = 'bg-red-100 text-red-800';
                $icon = 'fas fa-trash';
                break;
            case 'upload':
            case 'uploaded':
                $action_color = 'bg-purple-100 text-purple-800';
                $icon = 'fas fa-upload';
                break;
            case 'download':
            case 'downloaded':
                $action_color = 'bg-indigo-100 text-indigo-800';
                $icon = 'fas fa-download';
                break;
        }
        
        echo '<div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">';
        echo '<div class="flex items-center">';
        echo '<div class="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center">';
        echo '<i class="' . $icon . ' text-gray-600 text-sm"></i>';
        echo '</div>';
        echo '<div class="ml-3">';
        echo '<p class="text-sm font-medium text-gray-900">' . htmlspecialchars($log['username'] ?? 'System') . '</p>';
        echo '<p class="text-xs text-gray-600">' . htmlspecialchars($log['description']) . '</p>';
        if (!empty($log['ip_address'])) {
            echo '<p class="text-xs text-gray-500">IP: ' . htmlspecialchars($log['ip_address']) . '</p>';
        }
        echo '</div>';
        echo '</div>';
        echo '<div class="text-right">';
        echo '<span class="inline-block px-2 py-1 text-xs rounded-full ' . $action_color . '">';
        echo htmlspecialchars($log['action']);
        echo '</span>';
        echo '<p class="text-xs text-gray-500 mt-1">' . date('d.m.Y H:i:s', strtotime($log['created_at'])) . '</p>';
        echo '</div>';
        echo '</div>';
    }
    echo '</div>';
    
    // Paginacja
    if ($total_pages > 1) {
        echo '<div class="mt-6 flex justify-center">';
        echo '<nav class="flex space-x-2">';
        
        // Poprzednia strona
        if ($page > 1) {
            echo '<button onclick="loadLogsPage(' . ($page - 1) . ')" class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50">';
            echo '<i class="fas fa-chevron-left"></i>';
            echo '</button>';
        }
        
        // Numery stron
        $start = max(1, $page - 2);
        $end = min($total_pages, $page + 2);
        
        for ($i = $start; $i <= $end; $i++) {
            $active_class = ($i == $page) ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50';
            echo '<button onclick="loadLogsPage(' . $i . ')" class="px-3 py-2 text-sm border border-gray-300 rounded-md ' . $active_class . '">';
            echo $i;
            echo '</button>';
        }
        
        // Następna strona
        if ($page < $total_pages) {
            echo '<button onclick="loadLogsPage(' . ($page + 1) . ')" class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50">';
            echo '<i class="fas fa-chevron-right"></i>';
            echo '</button>';
        }
        
        echo '</nav>';
        echo '</div>';
        
        echo '<p class="text-center text-sm text-gray-500 mt-2">';
        echo 'Strona ' . $page . ' z ' . $total_pages . ' (łącznie ' . number_format($total_logs) . ' logów)';
        echo '</p>';
    }
    
} catch (Exception $e) {
    error_log("Error in get_logs.php: " . $e->getMessage());
    echo '<div class="text-center py-8">';
    echo '<i class="fas fa-exclamation-triangle text-red-400 text-3xl mb-2"></i>';
    echo '<p class="text-red-500">Błąd podczas ładowania logów</p>';
    echo '<p class="text-sm text-gray-500">' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '</div>';
}
?>