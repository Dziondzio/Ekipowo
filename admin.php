<?php
require_once 'config/config.php';

// Sprawdź czy użytkownik jest zalogowany i jest administratorem
if (!is_logged_in()) {
    redirect('login.php');
}

if (!is_admin()) {
    set_flash_message('error', 'Nie masz uprawnień administratora.');
    redirect('dashboard.php');
}

$db = new DatabaseManager();
$user = new User();
$subdomain = new Subdomain();

// Pobierz statystyki
$stats = [
    'total_users' => $db->selectOne("SELECT COUNT(*) as count FROM users")['count'],
    'total_subdomains' => $db->selectOne("SELECT COUNT(*) as count FROM subdomains")['count'],
    'hosted_subdomains' => $db->selectOne("SELECT COUNT(*) as count FROM subdomains WHERE subdomain_type = 'hosted'")['count'],
    'redirect_subdomains' => $db->selectOne("SELECT COUNT(*) as count FROM subdomains WHERE subdomain_type = 'redirect'")['count'],
    'total_files' => $db->selectOne("SELECT COUNT(*) as count FROM subdomain_files")['count'],
    'total_file_size' => $db->selectOne("SELECT COALESCE(SUM(file_size), 0) as size FROM subdomain_files")['size']
];

// Pobierz ostatnich użytkowników
$recent_users = $db->select(
    "SELECT id, username, email, created_at, email_verified FROM users ORDER BY created_at DESC LIMIT 10"
);

// Pobierz ostatnie subdomeny
$recent_subdomains = $db->select(
    "SELECT s.*, u.username FROM subdomains s 
     JOIN users u ON s.user_id = u.id 
     ORDER BY s.created_at DESC LIMIT 10"
);

// Pobierz logi aktywności
$activity_logs = $db->select(
    "SELECT al.*, u.username FROM activity_logs al 
     LEFT JOIN users u ON al.user_id = u.id 
     ORDER BY al.created_at DESC LIMIT 20"
);
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Administratora - <?php echo SITE_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .sidebar-item { transition: all 0.2s ease; }
        .sidebar-item:hover { background-color: rgba(99, 102, 241, 0.1); }
        .sidebar-item.active { background-color: rgba(99, 102, 241, 0.1); border-right: 3px solid #6366f1; }
        .stat-card { transition: all 0.3s ease; }
        .stat-card:hover { transform: translateY(-2px); }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <div class="w-64 bg-white shadow-lg">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center">
                    <i class="fas fa-shield-alt text-2xl text-indigo-600 mr-3"></i>
                    <div>
                        <h1 class="text-lg font-bold text-gray-900">Panel Admin</h1>
                        <p class="text-sm text-gray-500"><?php echo $_SESSION['username']; ?></p>
                    </div>
                </div>
            </div>
            
            <nav class="mt-6">
                <a href="#dashboard" class="sidebar-item active flex items-center px-6 py-3 text-gray-700 hover:text-indigo-600">
                    <i class="fas fa-chart-bar mr-3"></i>
                    Dashboard
                </a>
                <a href="#users" class="sidebar-item flex items-center px-6 py-3 text-gray-700 hover:text-indigo-600">
                    <i class="fas fa-users mr-3"></i>
                    Użytkownicy
                </a>
                <a href="#subdomains" class="sidebar-item flex items-center px-6 py-3 text-gray-700 hover:text-indigo-600">
                    <i class="fas fa-globe mr-3"></i>
                    Subdomeny
                </a>
                <a href="#files" class="sidebar-item flex items-center px-6 py-3 text-gray-700 hover:text-indigo-600">
                    <i class="fas fa-file mr-3"></i>
                    Pliki
                </a>
                <a href="#logs" class="sidebar-item flex items-center px-6 py-3 text-gray-700 hover:text-indigo-600">
                    <i class="fas fa-list mr-3"></i>
                    Logi aktywności
                </a>
                <a href="#settings" class="sidebar-item flex items-center px-6 py-3 text-gray-700 hover:text-indigo-600">
                    <i class="fas fa-cog mr-3"></i>
                    Ustawienia
                </a>
                <div class="border-t border-gray-200 mt-6 pt-6">
                    <a href="dashboard.php" class="sidebar-item flex items-center px-6 py-3 text-gray-700 hover:text-indigo-600">
                        <i class="fas fa-user mr-3"></i>
                        Panel użytkownika
                    </a>
                    <a href="logout.php" class="sidebar-item flex items-center px-6 py-3 text-gray-700 hover:text-red-600">
                        <i class="fas fa-sign-out-alt mr-3"></i>
                        Wyloguj
                    </a>
                </div>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="flex-1 overflow-auto">
            <!-- Header -->
            <header class="bg-white shadow-sm border-b border-gray-200 px-6 py-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-2xl font-bold text-gray-900">Panel Administratora</h2>
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-500"><?php echo date('d.m.Y H:i'); ?></span>
                        <div class="w-8 h-8 bg-indigo-600 rounded-full flex items-center justify-center">
                            <i class="fas fa-user text-white text-sm"></i>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Dashboard Section -->
            <div id="dashboard-section" class="p-6">
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
                    <div class="stat-card bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                        <div class="flex items-center">
                            <div class="p-3 bg-blue-100 rounded-lg">
                                <i class="fas fa-users text-blue-600 text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm text-gray-500">Użytkownicy</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['total_users']); ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                        <div class="flex items-center">
                            <div class="p-3 bg-green-100 rounded-lg">
                                <i class="fas fa-globe text-green-600 text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm text-gray-500">Subdomeny</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['total_subdomains']); ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                        <div class="flex items-center">
                            <div class="p-3 bg-purple-100 rounded-lg">
                                <i class="fas fa-server text-purple-600 text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm text-gray-500">Hostowane</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['hosted_subdomains']); ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                        <div class="flex items-center">
                            <div class="p-3 bg-blue-100 rounded-lg">
                                <i class="fas fa-external-link-alt text-blue-600 text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm text-gray-500">Przekierowania</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['redirect_subdomains']); ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                        <div class="flex items-center">
                            <div class="p-3 bg-orange-100 rounded-lg">
                                <i class="fas fa-file text-orange-600 text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm text-gray-500">Pliki</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['total_files']); ?></p>
                                <p class="text-xs text-gray-400"><?php echo format_file_size($stats['total_file_size']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="grid lg:grid-cols-2 gap-6">
                    <!-- Recent Users -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                        <div class="p-6 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900">Najnowsi użytkownicy</h3>
                        </div>
                        <div class="p-6">
                            <div class="space-y-4">
                                <?php foreach ($recent_users as $recent_user): ?>
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center">
                                            <i class="fas fa-user text-gray-600 text-sm"></i>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($recent_user['username']); ?></p>
                                            <p class="text-xs text-gray-500"><?php echo htmlspecialchars($recent_user['email']); ?></p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-xs text-gray-500"><?php echo date('d.m.Y', strtotime($recent_user['created_at'])); ?></p>
                                        <?php if ($recent_user['email_verified']): ?>
                                            <span class="inline-block w-2 h-2 bg-green-400 rounded-full"></span>
                                        <?php else: ?>
                                            <span class="inline-block w-2 h-2 bg-red-400 rounded-full"></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Subdomains -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                        <div class="p-6 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900">Najnowsze subdomeny</h3>
                        </div>
                        <div class="p-6">
                            <div class="space-y-4">
                                <?php foreach ($recent_subdomains as $recent_subdomain): ?>
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center">
                                            <i class="fas fa-globe text-indigo-600 text-sm"></i>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($recent_subdomain['subdomain_name']); ?></p>
                                            <p class="text-xs text-gray-500">przez <?php echo htmlspecialchars($recent_subdomain['username']); ?></p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <span class="inline-block px-2 py-1 text-xs rounded-full <?php echo $recent_subdomain['subdomain_type'] === 'hosted' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?>">
                                            <?php echo $recent_subdomain['subdomain_type'] === 'hosted' ? 'Hosting' : 'Przekierowanie'; ?>
                                        </span>
                                        <p class="text-xs text-gray-500 mt-1"><?php echo date('d.m.Y', strtotime($recent_subdomain['created_at'])); ?></p>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Users Section -->
            <div id="users-section" class="p-6 hidden">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Zarządzanie użytkownikami</h3>
                    </div>
                    <div class="p-6">
                        <div id="users-list">
                            <!-- Users will be loaded here via AJAX -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Subdomains Section -->
            <div id="subdomains-section" class="p-6 hidden">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Zarządzanie subdomenami</h3>
                    </div>
                    <div class="p-6">
                        <div id="subdomains-list">
                            <!-- Subdomains will be loaded here via AJAX -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Files Section -->
            <div id="files-section" class="p-6 hidden">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-gray-900">Zarządzanie plikami</h3>
                            <button onclick="syncFiles()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                                <i class="fas fa-sync-alt mr-2"></i>Synchronizuj pliki
                            </button>
                        </div>
                    </div>
                    <div class="p-6">
                        <div id="sync-results" class="mb-4 hidden"></div>
                        <div id="files-list">
                            <!-- Files will be loaded here via AJAX -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Logs Section -->
            <div id="logs-section" class="p-6 hidden">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Logi aktywności</h3>
                    </div>
                    <div class="p-6">
                        <div id="logs-list">
                            <div class="text-center py-8">
                                <i class="fas fa-spinner fa-spin text-gray-400 text-2xl mb-2"></i>
                                <p class="text-gray-500">Ładowanie logów...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settings Section -->
            <div id="settings-section" class="p-6 hidden">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Ustawienia systemu</h3>
                    </div>
                    <div class="p-6">
                        <div class="grid md:grid-cols-2 gap-6">
                            <div>
                                <h4 class="text-md font-medium text-gray-900 mb-4">Ustawienia Cloudflare</h4>
                                <form id="cloudflare-settings-form">
                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">API Token</label>
                                            <input type="password" id="cf_api_token" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="Cloudflare API Token">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Zone ID</label>
                                            <input type="text" id="cf_zone_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="Cloudflare Zone ID">
                                        </div>
                                        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition-colors">
                                            <i class="fas fa-save mr-2"></i>Zapisz ustawienia
                                        </button>
                                    </div>
                                </form>
                            </div>
                            
                            <div>
                                <h4 class="text-md font-medium text-gray-900 mb-4">Ustawienia systemu</h4>
                                <form id="system-settings-form">
                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Maksymalna liczba subdomen na użytkownika</label>
                                            <input type="number" id="max_subdomains" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" min="1" max="100">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Maksymalny rozmiar pliku (MB)</label>
                                            <input type="number" id="max_file_size" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" min="1" max="100">
                                        </div>
                                        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition-colors">
                                            <i class="fas fa-save mr-2"></i>Zapisz ustawienia
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Navigation
        document.querySelectorAll('.sidebar-item').forEach(item => {
            item.addEventListener('click', function(e) {
                if (this.getAttribute('href').startsWith('#')) {
                    e.preventDefault();
                    
                    // Remove active class from all items
                    document.querySelectorAll('.sidebar-item').forEach(i => i.classList.remove('active'));
                    
                    // Add active class to clicked item
                    this.classList.add('active');
                    
                    // Hide all sections
                    document.querySelectorAll('[id$="-section"]').forEach(section => {
                        section.classList.add('hidden');
                    });
                    
                    // Show target section
                    const targetId = this.getAttribute('href').substring(1) + '-section';
                    const targetSection = document.getElementById(targetId);
                    if (targetSection) {
                        targetSection.classList.remove('hidden');
                        
                        // Load content for specific sections
                        if (targetId === 'users-section') {
                            loadUsers();
                        } else if (targetId === 'subdomains-section') {
                            loadSubdomains();
                        } else if (targetId === 'files-section') {
                            loadFiles();
                        } else if (targetId === 'logs-section') {
                            loadLogs();
                        }
                    }
                }
            });
        });

        // Load users
        function loadUsers() {
            fetch('/api/admin/get_users.php')
                .then(response => response.text())
                .then(data => {
                    document.getElementById('users-list').innerHTML = data;
                })
                .catch(error => {
                    console.error('Error loading users:', error);
                });
        }

        // Load subdomains
        function loadSubdomains() {
            fetch('/api/admin/get_subdomains.php')
                .then(response => response.text())
                .then(data => {
                    document.getElementById('subdomains-list').innerHTML = data;
                })
                .catch(error => {
                    console.error('Error loading subdomains:', error);
                });
        }

        // Load files
        function loadFiles() {
            fetch('/api/admin/get_files.php')
                .then(response => response.text())
                .then(data => {
                    document.getElementById('files-list').innerHTML = data;
                })
                .catch(error => {
                    console.error('Error loading files:', error);
                });
        }

        // Load logs
        function loadLogs(page = 1) {
            fetch(`/api/admin/get_logs.php?page=${page}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('logs-list').innerHTML = data;
                })
                .catch(error => {
                    console.error('Error loading logs:', error);
                    document.getElementById('logs-list').innerHTML = '<div class="text-center py-8"><i class="fas fa-exclamation-triangle text-red-400 text-3xl mb-2"></i><p class="text-red-500">Błąd podczas ładowania logów</p></div>';
                });
        }

        // Load logs page (for pagination)
        function loadLogsPage(page) {
            loadLogs(page);
        }

        // Settings forms
        document.getElementById('cloudflare-settings-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('cf_api_token', document.getElementById('cf_api_token').value);
            formData.append('cf_zone_id', document.getElementById('cf_zone_id').value);
            
            fetch('/api/admin/update_settings.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Ustawienia Cloudflare zostały zapisane!');
                } else {
                    alert('Błąd: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Wystąpił błąd podczas zapisywania ustawień.');
            });
        });

        document.getElementById('system-settings-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('max_subdomains', document.getElementById('max_subdomains').value);
            formData.append('max_file_size', document.getElementById('max_file_size').value);
            
            fetch('/api/admin/update_settings.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Ustawienia systemu zostały zapisane!');
                } else {
                    alert('Błąd: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Wystąpił błąd podczas zapisywania ustawień.');
            });
        });

        // Sync files function
        function syncFiles() {
            const syncResults = document.getElementById('sync-results');
            const button = event.target;
            
            // Show loading state
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Synchronizowanie...';
            
            syncResults.className = 'mb-4 p-4 rounded-lg bg-blue-50 border border-blue-200';
            syncResults.innerHTML = '<p class="text-blue-800"><i class="fas fa-spinner fa-spin mr-2"></i>Synchronizowanie plików z systemem plików...</p>';
            syncResults.classList.remove('hidden');
            
            fetch('/api/sync_files.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let html = '<div class="text-green-800">';
                        html += '<h4 class="font-semibold mb-2"><i class="fas fa-check-circle mr-2"></i>Synchronizacja zakończona pomyślnie!</h4>';
                        html += '<p class="mb-2">Zsynchronizowano: <strong>' + data.synced_count + '</strong> plików</p>';
                        
                        if (data.synced_count > 0) {
                            html += '<details class="mb-2"><summary class="cursor-pointer font-medium">Zsynchronizowane pliki (' + data.synced_count + ')</summary>';
                            html += '<ul class="mt-2 ml-4 space-y-1">';
                            data.synced_files.forEach(file => {
                                html += '<li class="text-sm">• ' + file.subdomain + '/' + file.file + ' (' + (file.size / 1024).toFixed(1) + ' KB)</li>';
                            });
                            html += '</ul></details>';
                        }
                        
                        if (data.orphaned_count > 0) {
                            html += '<p class="text-orange-600 mb-2">Znaleziono <strong>' + data.orphaned_count + '</strong> plików w bazie, które nie istnieją na dysku</p>';
                            html += '<details class="mb-2"><summary class="cursor-pointer font-medium text-orange-600">Pliki sierocce (' + data.orphaned_count + ')</summary>';
                            html += '<ul class="mt-2 ml-4 space-y-1">';
                            data.orphaned_files.forEach(file => {
                                html += '<li class="text-sm text-orange-600">• ' + file.subdomain + '/' + file.file + '</li>';
                            });
                            html += '</ul></details>';
                        }
                        
                        if (data.error_count > 0) {
                            html += '<p class="text-red-600 mb-2">Błędy: <strong>' + data.error_count + '</strong></p>';
                            html += '<details class="mb-2"><summary class="cursor-pointer font-medium text-red-600">Błędy (' + data.error_count + ')</summary>';
                            html += '<ul class="mt-2 ml-4 space-y-1">';
                            data.errors.forEach(error => {
                                html += '<li class="text-sm text-red-600">• ' + error.subdomain + '/' + error.file + ': ' + error.error + '</li>';
                            });
                            html += '</ul></details>';
                        }
                        
                        html += '</div>';
                        
                        syncResults.className = 'mb-4 p-4 rounded-lg bg-green-50 border border-green-200';
                        syncResults.innerHTML = html;
                        
                        // Reload files list if we're in files section
                        if (!document.getElementById('files-section').classList.contains('hidden')) {
                            loadFiles();
                        }
                        
                    } else {
                        syncResults.className = 'mb-4 p-4 rounded-lg bg-red-50 border border-red-200';
                        syncResults.innerHTML = '<p class="text-red-800"><i class="fas fa-exclamation-triangle mr-2"></i>Błąd synchronizacji: ' + data.message + '</p>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    syncResults.className = 'mb-4 p-4 rounded-lg bg-red-50 border border-red-200';
                    syncResults.innerHTML = '<p class="text-red-800"><i class="fas fa-exclamation-triangle mr-2"></i>Wystąpił błąd podczas synchronizacji.</p>';
                })
                .finally(() => {
                    // Reset button state
                    button.disabled = false;
                    button.innerHTML = '<i class="fas fa-sync-alt mr-2"></i>Synchronizuj pliki';
                });
        }

        // Load current settings on page load
        fetch('/api/admin/get_settings.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.settings.cf_zone_id) {
                        document.getElementById('cf_zone_id').value = data.settings.cf_zone_id;
                    }
                    if (data.settings.max_subdomains_per_user) {
                        document.getElementById('max_subdomains').value = data.settings.max_subdomains_per_user;
                    }
                    if (data.settings.max_file_size) {
                        document.getElementById('max_file_size').value = Math.round(data.settings.max_file_size / (1024 * 1024));
                    }
                }
            })
            .catch(error => {
                console.error('Error loading settings:', error);
            });
    </script>
</body>
</html>