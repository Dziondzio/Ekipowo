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
    
    // Pobierz wszystkie subdomeny z informacjami o użytkownikach
    $subdomains = $db->select(
        "SELECT s.*, u.username, u.email,
                COUNT(sf.id) as file_count,
                COALESCE(SUM(sf.file_size), 0) as total_file_size
         FROM subdomains s 
         JOIN users u ON s.user_id = u.id 
         LEFT JOIN subdomain_files sf ON s.id = sf.subdomain_id 
         GROUP BY s.id 
         ORDER BY s.created_at DESC"
    );
    
    echo '<div class="overflow-x-auto">';
    echo '<table class="min-w-full divide-y divide-gray-200">';
    echo '<thead class="bg-gray-50">';
    echo '<tr>';
    echo '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subdomena</th>';
    echo '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Właściciel</th>';
    echo '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Typ</th>';
    echo '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cel/IP</th>';
    echo '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pliki</th>';
    echo '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data utworzenia</th>';
    echo '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Akcje</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody class="bg-white divide-y divide-gray-200">';
    
    foreach ($subdomains as $subdomain) {
        echo '<tr class="hover:bg-gray-50">';
        
        // Subdomena
        echo '<td class="px-6 py-4 whitespace-nowrap">';
        echo '<div class="flex items-center">';
        echo '<div class="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center">';
        echo '<i class="fas fa-globe text-indigo-600"></i>';
        echo '</div>';
        echo '<div class="ml-4">';
        echo '<div class="text-sm font-medium text-gray-900">' . htmlspecialchars($subdomain['subdomain_name']) . '</div>';
        echo '<div class="text-sm text-gray-500">ID: ' . $subdomain['id'] . '</div>';
        echo '</div>';
        echo '</div>';
        echo '</td>';
        
        // Właściciel
        echo '<td class="px-6 py-4 whitespace-nowrap">';
        echo '<div class="text-sm font-medium text-gray-900">' . htmlspecialchars($subdomain['username']) . '</div>';
        echo '<div class="text-sm text-gray-500">' . htmlspecialchars($subdomain['email']) . '</div>';
        echo '</td>';
        
        // Typ
        echo '<td class="px-6 py-4 whitespace-nowrap">';
        if ($subdomain['subdomain_type'] === 'hosted') {
            echo '<span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">';
            echo '<i class="fas fa-server mr-1"></i>Hosting';
            echo '</span>';
        } else {
            echo '<span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">';
            echo '<i class="fas fa-external-link-alt mr-1"></i>Przekierowanie';
            echo '</span>';
        }
        echo '</td>';
        
        // Cel/IP
        echo '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">';
        if ($subdomain['subdomain_type'] === 'hosted') {
            echo '<span class="text-green-600">Serwer platformy</span>';
        } else {
            echo htmlspecialchars($subdomain['target_ip'] ?? '');
        }
        echo '</td>';
        
        // Pliki
        echo '<td class="px-6 py-4 whitespace-nowrap">';
        if ($subdomain['subdomain_type'] === 'hosted') {
            echo '<div class="text-sm text-gray-900">' . $subdomain['file_count'] . ' plików</div>';
            echo '<div class="text-sm text-gray-500">' . format_file_size($subdomain['total_file_size']) . '</div>';
        } else {
            echo '<span class="text-gray-400">-</span>';
        }
        echo '</td>';
        
        // Data utworzenia
        echo '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' . date('d.m.Y H:i', strtotime($subdomain['created_at'])) . '</td>';
        
        // Akcje
        echo '<td class="px-6 py-4 whitespace-nowrap text-sm font-medium">';
        echo '<div class="flex space-x-2">';
        
        if ($subdomain['subdomain_type'] === 'hosted') {
            echo '<button onclick="viewFiles(' . $subdomain['id'] . ')" class="text-blue-600 hover:text-blue-900" title="Zobacz pliki">';
            echo '<i class="fas fa-folder-open"></i>';
            echo '</button>';
        }
        
        if ($subdomain['subdomain_type'] === 'redirect') {
            echo '<button onclick="editSubdomain(' . $subdomain['id'] . ')" class="text-green-600 hover:text-green-900" title="Edytuj subdomenę">';
            echo '<i class="fas fa-edit"></i>';
            echo '</button>';
        }
        
        echo '<button onclick="deleteSubdomain(' . $subdomain['id'] . ')" class="text-red-600 hover:text-red-900" title="Usuń subdomenę">';
        echo '<i class="fas fa-trash"></i>';
        echo '</button>';
        
        echo '</div>';
        echo '</td>';
        
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
    
    // Modal do edycji subdomeny
    echo '<div id="edit-subdomain-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">';
    echo '<div class="flex items-center justify-center min-h-screen p-4">';
    echo '<div class="bg-white rounded-lg shadow-xl max-w-md w-full">';
    echo '<div class="p-6">';
    echo '<h3 class="text-lg font-medium text-gray-900 mb-4">Edytuj subdomenę</h3>';
    echo '<form id="edit-subdomain-form">';
    echo '<input type="hidden" id="edit-subdomain-id">';
    echo '<div class="mb-4">';
    echo '<label class="block text-sm font-medium text-gray-700 mb-2">Nazwa subdomeny</label>';
    echo '<input type="text" id="edit-subdomain-name" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" readonly>';
    echo '</div>';
    echo '<div class="mb-4">';
    echo '<label class="block text-sm font-medium text-gray-700 mb-2">Docelowe IP (tylko dla przekierowań)</label>';
    echo '<input type="text" id="edit-target-ip" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">';
    echo '</div>';
    echo '<div class="flex justify-end space-x-3">';
    echo '<button type="button" onclick="closeEditModal()" class="px-4 py-2 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300">Anuluj</button>';
    echo '<button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Zapisz</button>';
    echo '</div>';
    echo '</form>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    
    // JavaScript functions
    echo '<script>';
    
    echo 'function viewFiles(subdomainId) {';
    echo '    window.open("/api/admin/view_files.php?subdomain_id=" + subdomainId, "_blank");';
    echo '}';
    
    echo 'function editSubdomain(subdomainId) {';
    echo '    fetch("/api/admin/get_subdomain.php?id=" + subdomainId)';
    echo '        .then(response => response.json())';
    echo '        .then(data => {';
    echo '            if (data.success) {';
    echo '                document.getElementById("edit-subdomain-id").value = data.subdomain.id;';
    echo '                document.getElementById("edit-subdomain-name").value = data.subdomain.subdomain_name;';
    echo '                document.getElementById("edit-target-ip").value = data.subdomain.target_ip || "";';
    echo '                document.getElementById("edit-target-ip").disabled = data.subdomain.subdomain_type === "hosted";';
    echo '                document.getElementById("edit-subdomain-modal").classList.remove("hidden");';
    echo '            } else {';
    echo '                alert("Błąd: " + data.message);';
    echo '            }';
    echo '        });';
    echo '}';
    
    echo 'function closeEditModal() {';
    echo '    document.getElementById("edit-subdomain-modal").classList.add("hidden");';
    echo '}';
    
    echo 'document.getElementById("edit-subdomain-form").addEventListener("submit", function(e) {';
    echo '    e.preventDefault();';
    echo '    const formData = new FormData();';
    echo '    formData.append("action", "update");';
    echo '    formData.append("subdomain_id", document.getElementById("edit-subdomain-id").value);';
    echo '    formData.append("target_ip", document.getElementById("edit-target-ip").value);';
    echo '    ';
    echo '    fetch("/api/admin/manage_subdomain.php", {';
    echo '        method: "POST",';
    echo '        body: formData';
    echo '    })';
    echo '    .then(response => response.json())';
    echo '    .then(data => {';
    echo '        if (data.success) {';
    echo '            alert("Subdomena została zaktualizowana!");';
    echo '            closeEditModal();';
    echo '            loadSubdomains();';
    echo '        } else {';
    echo '            alert("Błąd: " + data.message);';
    echo '        }';
    echo '    });';
    echo '});';
    
    echo 'function deleteSubdomain(subdomainId) {';
    echo '    if (confirm("Czy na pewno chcesz usunąć tę subdomenę? Ta akcja jest nieodwracalna!")) {';
    echo '        fetch("/api/admin/manage_subdomain.php", {';
    echo '            method: "POST",';
    echo '            headers: { "Content-Type": "application/x-www-form-urlencoded" },';
    echo '            body: "action=delete&subdomain_id=" + subdomainId';
    echo '        })';
    echo '        .then(response => response.json())';
    echo '        .then(data => {';
    echo '            if (data.success) {';
    echo '                alert("Subdomena została usunięta!");';
    echo '                loadSubdomains();';
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