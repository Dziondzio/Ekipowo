<?php
require_once '../../config/config.php';

// Sprawdź czy użytkownik jest zalogowany i jest administratorem
if (!is_logged_in() || !is_admin()) {
    http_response_code(403);
    echo 'Brak uprawnień.';
    exit;
}

try {
    $db = new DatabaseManager();
    
    // Pobierz wszystkich użytkowników z dodatkowymi informacjami
    $users = $db->select(
        "SELECT u.*, 
                COUNT(s.id) as subdomain_count,
                COALESCE(SUM(sf.file_size), 0) as total_file_size
         FROM users u 
         LEFT JOIN subdomains s ON u.id = s.user_id 
         LEFT JOIN subdomain_files sf ON s.id = sf.subdomain_id 
         GROUP BY u.id 
         ORDER BY u.created_at DESC"
    );
    
    echo '<div class="overflow-x-auto">';
    echo '<table class="min-w-full divide-y divide-gray-200">';
    echo '<thead class="bg-gray-50">';
    echo '<tr>';
    echo '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Użytkownik</th>';
    echo '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>';
    echo '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>';
    echo '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subdomeny</th>';
    echo '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rozmiar plików</th>';
    echo '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data rejestracji</th>';
    echo '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Akcje</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody class="bg-white divide-y divide-gray-200">';
    
    foreach ($users as $user) {
        echo '<tr class="hover:bg-gray-50">';
        
        // Użytkownik
        echo '<td class="px-6 py-4 whitespace-nowrap">';
        echo '<div class="flex items-center">';
        echo '<div class="w-10 h-10 bg-gray-200 rounded-full flex items-center justify-center">';
        echo '<i class="fas fa-user text-gray-600"></i>';
        echo '</div>';
        echo '<div class="ml-4">';
        echo '<div class="text-sm font-medium text-gray-900">' . htmlspecialchars($user['username']) . '</div>';
        echo '<div class="text-sm text-gray-500">ID: ' . $user['id'] . '</div>';
        echo '</div>';
        echo '</div>';
        echo '</td>';
        
        // Email
        echo '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">' . htmlspecialchars($user['email']) . '</td>';
        
        // Status
        echo '<td class="px-6 py-4 whitespace-nowrap">';
        if ($user['email_verified']) {
            echo '<span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Zweryfikowany</span>';
        } else {
            echo '<span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Niezweryfikowany</span>';
        }
        if ($user['is_admin']) {
            echo '<br><span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800 mt-1">Administrator</span>';
        }
        echo '</td>';
        
        // Subdomeny
        echo '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">' . $user['subdomain_count'] . '</td>';
        
        // Rozmiar plików
        echo '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">' . format_file_size($user['total_file_size']) . '</td>';
        
        // Data rejestracji
        echo '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' . date('d.m.Y H:i', strtotime($user['created_at'])) . '</td>';
        
        // Akcje
        echo '<td class="px-6 py-4 whitespace-nowrap text-sm font-medium">';
        echo '<div class="flex space-x-2">';
        
        if (!$user['email_verified']) {
            echo '<button onclick="verifyUser(' . $user['id'] . ')" class="text-green-600 hover:text-green-900" title="Zweryfikuj użytkownika">';
            echo '<i class="fas fa-check"></i>';
            echo '</button>';
        }
        
        if (!$user['is_admin']) {
            echo '<button onclick="toggleAdmin(' . $user['id'] . ', true)" class="text-purple-600 hover:text-purple-900" title="Nadaj uprawnienia administratora">';
            echo '<i class="fas fa-user-shield"></i>';
            echo '</button>';
        } else {
            echo '<button onclick="toggleAdmin(' . $user['id'] . ', false)" class="text-orange-600 hover:text-orange-900" title="Odbierz uprawnienia administratora">';
            echo '<i class="fas fa-user-minus"></i>';
            echo '</button>';
        }
        
        echo '<button onclick="deleteUser(' . $user['id'] . ')" class="text-red-600 hover:text-red-900" title="Usuń użytkownika">';
        echo '<i class="fas fa-trash"></i>';
        echo '</button>';
        
        echo '</div>';
        echo '</td>';
        
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
    
    // JavaScript functions
    echo '<script>';
    echo 'function verifyUser(userId) {';
    echo '    if (confirm("Czy na pewno chcesz zweryfikować tego użytkownika?")) {';
    echo '        fetch("/api/admin/manage_user.php", {';
    echo '            method: "POST",';
    echo '            headers: { "Content-Type": "application/x-www-form-urlencoded" },';
    echo '            body: "action=verify&user_id=" + userId';
    echo '        })';
    echo '        .then(response => response.json())';
    echo '        .then(data => {';
    echo '            if (data.success) {';
    echo '                alert("Użytkownik został zweryfikowany!");';
    echo '                loadUsers();';
    echo '            } else {';
    echo '                alert("Błąd: " + data.message);';
    echo '            }';
    echo '        });';
    echo '    }';
    echo '}';
    
    echo 'function toggleAdmin(userId, makeAdmin) {';
    echo '    const action = makeAdmin ? "Nadać" : "Odebrać";';
    echo '    if (confirm(`Czy na pewno chcesz ${action.toLowerCase()} uprawnienia administratora?`)) {';
    echo '        fetch("/api/admin/manage_user.php", {';
    echo '            method: "POST",';
    echo '            headers: { "Content-Type": "application/x-www-form-urlencoded" },';
    echo '            body: "action=toggle_admin&user_id=" + userId + "&make_admin=" + (makeAdmin ? "1" : "0")';
    echo '        })';
    echo '        .then(response => response.json())';
    echo '        .then(data => {';
    echo '            if (data.success) {';
    echo '                alert("Uprawnienia zostały zaktualizowane!");';
    echo '                loadUsers();';
    echo '            } else {';
    echo '                alert("Błąd: " + data.message);';
    echo '            }';
    echo '        });';
    echo '    }';
    echo '}';
    
    echo 'function deleteUser(userId) {';
    echo '    if (confirm("Czy na pewno chcesz usunąć tego użytkownika? Ta akcja jest nieodwracalna!")) {';
    echo '        fetch("/api/admin/manage_user.php", {';
    echo '            method: "POST",';
    echo '            headers: { "Content-Type": "application/x-www-form-urlencoded" },';
    echo '            body: "action=delete&user_id=" + userId';
    echo '        })';
    echo '        .then(response => response.json())';
    echo '        .then(data => {';
    echo '            if (data.success) {';
    echo '                alert("Użytkownik został usunięty!");';
    echo '                loadUsers();';
    echo '            } else {';
    echo '                alert("Błąd: " + data.message);';
    echo '            }';
    echo '        });';
    echo '    }';
    echo '}';
    echo '</script>';
    
} catch (Exception $e) {
    echo '<div class="text-red-600">Błąd: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>