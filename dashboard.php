<?php
require_once 'config/config.php';

// Sprawdź czy użytkownik jest zalogowany
if (!is_logged_in()) {
    redirect('/login.php');
}

$user = new User();
$subdomain = new Subdomain();
$current_user = $user->getUserById($_SESSION['user_id']);

if (!$current_user) {
    session_destroy();
    redirect('/login.php');
}

// Pobierz subdomeny użytkownika
$user_subdomains = $subdomain->getUserSubdomains($_SESSION['user_id']);

// Pobierz statystyki
$stats = [
    'total_subdomains' => count($user_subdomains),
    'hosted_subdomains' => count(array_filter($user_subdomains, function($s) { return $s['subdomain_type'] === 'hosted'; })),
    'redirect_subdomains' => count(array_filter($user_subdomains, function($s) { return $s['subdomain_type'] === 'redirect'; })),
    'max_subdomains' => $subdomain->getMaxSubdomainsPerUser()
];

// Obsługa komunikatów flash
$flash_message = get_flash_message();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel użytkownika - <?= SITE_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .sidebar-transition {
            transition: transform 0.3s ease-in-out;
        }
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Sidebar -->
    <div id="sidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-white shadow-lg sidebar-transition transform -translate-x-full lg:translate-x-0">
        <div class="flex items-center justify-center h-16 bg-indigo-600">
            <div class="flex items-center">
                <i class="fas fa-rocket text-white text-xl mr-2"></i>
                <span class="text-white text-lg font-semibold"><?= SITE_NAME ?></span>
            </div>
        </div>
        
        <nav class="mt-8">
            <div class="px-4 space-y-2">
                <a href="#dashboard" onclick="showSection('dashboard')" class="nav-link flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-indigo-50 hover:text-indigo-600 transition-colors">
                    <i class="fas fa-tachometer-alt mr-3"></i>
                    Dashboard
                </a>
                <a href="#subdomains" onclick="showSection('subdomains')" class="nav-link flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-indigo-50 hover:text-indigo-600 transition-colors">
                    <i class="fas fa-globe mr-3"></i>
                    Moje subdomeny
                </a>
                <a href="#add-subdomain" onclick="showSection('add-subdomain')" class="nav-link flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-indigo-50 hover:text-indigo-600 transition-colors">
                    <i class="fas fa-plus mr-3"></i>
                    Dodaj subdomenę
                </a>
                <a href="#files" onclick="showSection('files')" class="nav-link flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-indigo-50 hover:text-indigo-600 transition-colors">
                    <i class="fas fa-folder mr-3"></i>
                    Zarządzaj plikami
                </a>
                <a href="#profile" onclick="showSection('profile')" class="nav-link flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-indigo-50 hover:text-indigo-600 transition-colors">
                    <i class="fas fa-user mr-3"></i>
                    Profil
                </a>
            </div>
        </nav>
        
        <div class="absolute bottom-0 w-full p-4">
            <div class="bg-gray-100 rounded-lg p-3">
                <div class="flex items-center">
                    <div class="w-8 h-8 bg-indigo-600 rounded-full flex items-center justify-center">
                        <i class="fas fa-user text-white text-sm"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-700"><?= htmlspecialchars($current_user['username']) ?></p>
                        <p class="text-xs text-gray-500"><?= htmlspecialchars($current_user['email']) ?></p>
                    </div>
                </div>
                <a href="/logout.php" class="mt-2 w-full bg-red-500 text-white py-2 px-3 rounded text-sm hover:bg-red-600 transition-colors flex items-center justify-center">
                    <i class="fas fa-sign-out-alt mr-2"></i>
                    Wyloguj
                </a>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <div class="lg:ml-64">
        <!-- Top bar -->
        <div class="bg-white shadow-sm border-b border-gray-200">
            <div class="flex items-center justify-between h-16 px-6">
                <button id="sidebar-toggle" class="lg:hidden text-gray-500 hover:text-gray-700">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                
                <div class="flex items-center space-x-4">
                    <div class="text-sm text-gray-500">
                        Ostatnie logowanie: <?= date('d.m.Y H:i', strtotime($current_user['last_login'])) ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="p-6">
            <?php if ($flash_message): ?>
                <div class="mb-6 p-4 rounded-lg <?= $flash_message['type'] === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700' ?>">
                    <div class="flex items-center">
                        <i class="fas <?= $flash_message['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
                        <?= htmlspecialchars($flash_message['message']) ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Dashboard Section -->
            <div id="section-dashboard" class="section">
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-900">Witaj, <?= htmlspecialchars($current_user['username']) ?>!</h1>
                    <p class="text-gray-600">Zarządzaj swoimi subdomenami i plikami</p>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white rounded-lg shadow p-6 card-hover">
                        <div class="flex items-center">
                            <div class="p-2 bg-indigo-100 rounded-lg">
                                <i class="fas fa-globe text-indigo-600 text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Wszystkie subdomeny</p>
                                <p class="text-2xl font-bold text-gray-900"><?= $stats['total_subdomains'] ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6 card-hover">
                        <div class="flex items-center">
                            <div class="p-2 bg-green-100 rounded-lg">
                                <i class="fas fa-server text-green-600 text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Hostowane</p>
                                <p class="text-2xl font-bold text-gray-900"><?= $stats['hosted_subdomains'] ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6 card-hover">
                        <div class="flex items-center">
                            <div class="p-2 bg-blue-100 rounded-lg">
                                <i class="fas fa-external-link-alt text-blue-600 text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Przekierowania</p>
                                <p class="text-2xl font-bold text-gray-900"><?= $stats['redirect_subdomains'] ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6 card-hover">
                        <div class="flex items-center">
                            <div class="p-2 bg-yellow-100 rounded-lg">
                                <i class="fas fa-chart-line text-yellow-600 text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Limit</p>
                                <p class="text-2xl font-bold text-gray-900"><?= $stats['max_subdomains'] ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Szybkie akcje</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <button onclick="showSection('add-subdomain')" class="flex items-center justify-center p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-indigo-500 hover:bg-indigo-50 transition-colors">
                            <div class="text-center">
                                <i class="fas fa-plus text-2xl text-gray-400 mb-2"></i>
                                <p class="text-sm font-medium text-gray-600">Dodaj subdomenę</p>
                            </div>
                        </button>
                        
                        <button onclick="showSection('files')" class="flex items-center justify-center p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-indigo-500 hover:bg-indigo-50 transition-colors">
                            <div class="text-center">
                                <i class="fas fa-upload text-2xl text-gray-400 mb-2"></i>
                                <p class="text-sm font-medium text-gray-600">Wgraj pliki</p>
                            </div>
                        </button>
                        
                        <button onclick="showSection('subdomains')" class="flex items-center justify-center p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-indigo-500 hover:bg-indigo-50 transition-colors">
                            <div class="text-center">
                                <i class="fas fa-cog text-2xl text-gray-400 mb-2"></i>
                                <p class="text-sm font-medium text-gray-600">Zarządzaj</p>
                            </div>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Subdomains Section -->
            <div id="section-subdomains" class="section hidden">
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-900">Moje subdomeny</h1>
                    <p class="text-gray-600">Zarządzaj swoimi subdomenami</p>
                </div>

                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <?php if (empty($user_subdomains)): ?>
                        <div class="p-8 text-center">
                            <i class="fas fa-globe text-4xl text-gray-300 mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Brak subdomen</h3>
                            <p class="text-gray-600 mb-4">Nie masz jeszcze żadnych subdomen. Dodaj pierwszą!</p>
                            <button onclick="showSection('add-subdomain')" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition-colors">
                                <i class="fas fa-plus mr-2"></i>
                                Dodaj subdomenę
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subdomena</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Typ</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cel</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data utworzenia</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Akcje</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($user_subdomains as $sub): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <i class="fas fa-globe text-indigo-600 mr-2"></i>
                                                    <a href="https://<?= htmlspecialchars($sub['subdomain_name']) ?>.<?= DOMAIN_NAME ?>" target="_blank" class="text-indigo-600 hover:text-indigo-900 font-medium">
                                                        <?= htmlspecialchars($sub['subdomain_name']) ?>.<?= DOMAIN_NAME ?>
                                                    </a>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $sub['subdomain_type'] === 'hosted' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' ?>">
                                    <i class="fas <?= $sub['subdomain_type'] === 'hosted' ? 'fa-server' : 'fa-external-link-alt' ?> mr-1"></i>
                                    <?= $sub['subdomain_type'] === 'hosted' ? 'Hostowana' : 'Przekierowanie' ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?= $sub['subdomain_type'] === 'hosted' ? 'Nasze serwery' : htmlspecialchars($sub['target_ip']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $sub['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                                                    <?= $sub['status'] === 'active' ? 'Aktywna' : 'Oczekująca' ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= date('d.m.Y H:i', strtotime($sub['created_at'])) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <div class="flex space-x-2">
                                                    <?php if ($sub['subdomain_type'] === 'hosted'): ?>
                                                        <button onclick="manageFiles('<?= htmlspecialchars($sub['subdomain_name']) ?>')" class="text-indigo-600 hover:text-indigo-900">
                                                            <i class="fas fa-folder"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($sub['subdomain_type'] === 'redirect'): ?>
                                                        <button onclick="editSubdomain('<?= $sub['id'] ?>')" class="text-yellow-600 hover:text-yellow-900">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <button onclick="deleteSubdomain('<?= $sub['id'] ?>', '<?= htmlspecialchars($sub['subdomain_name']) ?>')" class="text-red-600 hover:text-red-900">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Add Subdomain Section -->
            <div id="section-add-subdomain" class="section hidden">
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-900">Dodaj nową subdomenę</h1>
                    <p class="text-gray-600">Utwórz nową subdomenę dla swojego projektu</p>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <form id="addSubdomainForm" class="space-y-6">
                        <div>
                            <label for="subdomain_name" class="block text-sm font-medium text-gray-700 mb-2">
                                Nazwa subdomeny
                            </label>
                            <div class="flex">
                                <input 
                                    type="text" 
                                    id="subdomain_name" 
                                    name="subdomain_name" 
                                    required 
                                    pattern="[a-z0-9-]{3,30}"
                                    class="flex-1 px-3 py-2 border border-gray-300 rounded-l-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                    placeholder="moja-subdomena"
                                >
                                <span class="inline-flex items-center px-3 py-2 border border-l-0 border-gray-300 bg-gray-50 text-gray-500 rounded-r-lg">
                                    .<?= DOMAIN_NAME ?>
                                </span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">3-30 znaków: małe litery, cyfry, myślniki</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-4">Typ subdomeny</label>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <label class="relative">
                                    <input type="radio" name="subdomain_type" value="redirect" class="sr-only" checked>
                                    <div class="border-2 border-gray-300 rounded-lg p-4 cursor-pointer hover:border-indigo-500 transition-colors subdomain-type-option">
                                        <div class="flex items-center mb-2">
                                            <i class="fas fa-external-link-alt text-blue-600 mr-2"></i>
                                            <span class="font-medium">Przekierowanie na własne IP</span>
                                        </div>
                                        <p class="text-sm text-gray-600">Subdomena będzie przekierowywać na Twój serwer</p>
                                    </div>
                                </label>
                                
                                <label class="relative">
                                    <input type="radio" name="subdomain_type" value="hosted" class="sr-only">
                                    <div class="border-2 border-gray-300 rounded-lg p-4 cursor-pointer hover:border-indigo-500 transition-colors subdomain-type-option">
                                        <div class="flex items-center mb-2">
                                            <i class="fas fa-server text-green-600 mr-2"></i>
                                            <span class="font-medium">Hosting na naszych serwerach</span>
                                        </div>
                                        <p class="text-sm text-gray-600">Wgraj pliki HTML/CSS/JS na nasze serwery</p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div id="target_ip_section">
                            <label for="target_ip" class="block text-sm font-medium text-gray-700 mb-2">
                                Docelowe IP
                            </label>
                            <input 
                                type="text" 
                                id="target_ip" 
                                name="target_ip" 
                                pattern="^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                placeholder="192.168.1.1"
                            >
                            <p class="text-xs text-gray-500 mt-1">Adres IP Twojego serwera</p>
                        </div>

                        <div id="hosted_info_section" class="hidden">
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <div class="flex items-center mb-2">
                                    <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                                    <span class="font-medium text-blue-900">Hosting na naszych serwerach</span>
                                </div>
                                <p class="text-sm text-blue-800">Po utworzeniu subdomeny będziesz mógł wgrać pliki HTML, CSS i JavaScript w sekcji "Zarządzaj plikami".</p>
                            </div>
                        </div>

                        <button 
                            type="submit" 
                            class="w-full bg-indigo-600 text-white py-3 px-4 rounded-lg font-semibold hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-colors"
                        >
                            <i class="fas fa-plus mr-2"></i>
                            Utwórz subdomenę
                        </button>
                    </form>
                </div>
            </div>

            <!-- Files Section -->
            <div id="section-files" class="section hidden">
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-900">Zarządzanie plikami</h1>
                    <p class="text-gray-600">Wgraj i zarządzaj plikami dla hostowanych subdomen</p>
                </div>

                <?php 
                $hosted_subdomains = array_filter($user_subdomains, function($sub) {
                    return $sub['subdomain_type'] === 'hosted';
                });
                ?>

                <?php if (empty($hosted_subdomains)): ?>
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="text-center py-8">
                            <i class="fas fa-info-circle text-4xl text-blue-300 mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Brak hostowanych subdomen</h3>
                            <p class="text-gray-600 mb-4">Aby zarządzać plikami, musisz najpierw utworzyć subdomenę typu "Hostowana"</p>
                            <button onclick="showSection('subdomains')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md">
                                <i class="fas fa-plus mr-2"></i>Utwórz subdomenę
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="bg-white rounded-lg shadow p-6 mb-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Wybierz subdomenę do zarządzania</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php foreach ($hosted_subdomains as $sub): ?>
                                <div class="border border-gray-200 rounded-lg p-4 hover:border-indigo-300 hover:shadow-md transition-all cursor-pointer" onclick="loadFileManager('<?= htmlspecialchars($sub['subdomain_name']) ?>')">
                                    <div class="flex items-center mb-2">
                                        <i class="fas fa-server text-green-600 mr-2"></i>
                                        <h4 class="font-medium text-gray-900"><?= htmlspecialchars($sub['subdomain_name']) ?>.<?= DOMAIN_NAME ?></h4>
                                    </div>
                                    <p class="text-sm text-gray-600 mb-3">Utworzona: <?= date('d.m.Y', strtotime($sub['created_at'])) ?></p>
                                    <div class="flex items-center justify-between">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $sub['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                                            <?= $sub['status'] === 'active' ? 'Aktywna' : 'Oczekująca' ?>
                                        </span>
                                        <i class="fas fa-arrow-right text-indigo-600"></i>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow p-6">
                        <div id="files-content">
                            <div class="text-center py-8">
                                <i class="fas fa-folder-open text-4xl text-gray-300 mb-4"></i>
                                <h3 class="text-lg font-medium text-gray-900 mb-2">Wybierz subdomenę</h3>
                                <p class="text-gray-600">Kliknij na jedną z hostowanych subdomen powyżej, aby zarządzać jej plikami</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- File Editor Modal -->
            <div id="fileEditorModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
                <div class="flex items-center justify-center min-h-screen p-4">
                    <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-screen overflow-hidden">
                        <div class="flex items-center justify-between p-4 border-b border-gray-200">
                            <h3 id="editorTitle" class="text-lg font-semibold text-gray-900">Edytuj plik</h3>
                            <button onclick="closeFileEditor()" class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        </div>
                        
                        <div class="p-4">
                            <form id="fileEditorForm">
                                <input type="hidden" id="editFileId" name="file_id">
                                <textarea 
                                    id="fileContent" 
                                    name="content" 
                                    rows="20" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono text-sm"
                                    placeholder="Zawartość pliku..."
                                ></textarea>
                                
                                <div class="flex justify-end space-x-2 mt-4">
                                    <button 
                                        type="button" 
                                        onclick="closeFileEditor()" 
                                        class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors"
                                    >
                                        Anuluj
                                    </button>
                                    <button 
                                        type="submit" 
                                        class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors"
                                    >
                                        Zapisz zmiany
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile Section -->
            <div id="section-profile" class="section hidden">
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-900">Profil użytkownika</h1>
                    <p class="text-gray-600">Zarządzaj swoimi danymi osobowymi</p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Informacje o koncie -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">Informacje o koncie</h2>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Nazwa użytkownika</label>
                                <p class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($current_user['username']) ?></p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Adres email</label>
                                <p class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($current_user['email']) ?></p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Imię i nazwisko</label>
                                <p class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($current_user['full_name'] ?: 'Nie podano') ?></p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Data rejestracji</label>
                                <p class="mt-1 text-sm text-gray-900"><?= date('d.m.Y H:i', strtotime($current_user['created_at'])) ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Zmiana hasła -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">Zmiana hasła</h2>
                        <form id="changePasswordForm" class="space-y-4">
                            <div>
                                <label for="current_password" class="block text-sm font-medium text-gray-700">Obecne hasło</label>
                                <input type="password" id="current_password" name="current_password" required class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label for="new_password" class="block text-sm font-medium text-gray-700">Nowe hasło</label>
                                <input type="password" id="new_password" name="new_password" required minlength="<?= PASSWORD_MIN_LENGTH ?>" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label for="confirm_password" class="block text-sm font-medium text-gray-700">Potwierdź nowe hasło</label>
                                <input type="password" id="confirm_password" name="confirm_password" required class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <button type="submit" class="w-full bg-indigo-600 text-white py-2 px-4 rounded-lg hover:bg-indigo-700 transition-colors">
                                Zmień hasło
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal do edycji subdomeny -->
    <div id="edit-subdomain-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Edytuj subdomenę</h3>
                    <form id="edit-subdomain-form">
                        <input type="hidden" id="edit-subdomain-id">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nazwa subdomeny</label>
                            <input type="text" id="edit-subdomain-name" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" readonly>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Docelowe IP (tylko dla przekierowań)</label>
                            <input type="text" id="edit-target-ip" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="closeEditModal()" class="px-4 py-2 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300">Anuluj</button>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Zapisz</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Sidebar toggle
        document.getElementById('sidebar-toggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('-translate-x-full');
        });

        // Section navigation
        function showSection(sectionName) {
            // Hide all sections
            document.querySelectorAll('.section').forEach(section => {
                section.classList.add('hidden');
            });
            
            // Show selected section
            document.getElementById('section-' + sectionName).classList.remove('hidden');
            
            // Update navigation
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('bg-indigo-50', 'text-indigo-600');
            });
            
            // Find and highlight the correct nav link
            const targetLink = document.querySelector(`a[href="#${sectionName}"]`);
            if (targetLink) {
                targetLink.classList.add('bg-indigo-50', 'text-indigo-600');
            }
        }

        // Subdomain type selection
        document.querySelectorAll('input[name="subdomain_type"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const targetIpSection = document.getElementById('target_ip_section');
                const hostedInfoSection = document.getElementById('hosted_info_section');
                
                if (this.value === 'redirect') {
                    targetIpSection.style.display = 'block';
                    hostedInfoSection.classList.add('hidden');
                    document.getElementById('target_ip').required = true;
                } else {
                    targetIpSection.style.display = 'none';
                    hostedInfoSection.classList.remove('hidden');
                    document.getElementById('target_ip').required = false;
                }
                
                // Update visual selection
                document.querySelectorAll('.subdomain-type-option').forEach(option => {
                    option.classList.remove('border-indigo-500', 'bg-indigo-50');
                });
                this.closest('.subdomain-type-option').classList.add('border-indigo-500', 'bg-indigo-50');
            });
        });

        // Initialize first radio button
        document.querySelector('input[name="subdomain_type"]:checked').dispatchEvent(new Event('change'));

        // Add subdomain form
        document.getElementById('addSubdomainForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('/api/add_subdomain.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Subdomena została utworzona pomyślnie!');
                    location.reload();
                } else {
                    alert('Błąd: ' + data.message);
                }
            })
            .catch(error => {
                alert('Wystąpił błąd podczas tworzenia subdomeny.');
            });
        });

        // Change password form
        document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                alert('Nowe hasła nie są identyczne!');
                return;
            }
            
            const formData = new FormData(this);
            
            fetch('/api/change_password.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Hasło zostało zmienione pomyślnie!');
                    this.reset();
                } else {
                    alert('Błąd: ' + data.message);
                }
            })
            .catch(error => {
                alert('Wystąpił błąd podczas zmiany hasła.');
            });
        });

        // Delete subdomain
        function deleteSubdomain(id, name) {
            if (confirm('Czy na pewno chcesz usunąć subdomenę ' + name + '?')) {
                fetch('/api/delete_subdomain.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: id })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Subdomena została usunięta!');
                        location.reload();
                    } else {
                        alert('Błąd: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Wystąpił błąd podczas usuwania subdomeny.');
                });
            }
        }
        
        // Edit subdomain
        function editSubdomain(subdomainId) {
            fetch('/api/get_subdomain.php?id=' + subdomainId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('edit-subdomain-id').value = data.subdomain.id;
                        document.getElementById('edit-subdomain-name').value = data.subdomain.subdomain_name;
                        document.getElementById('edit-target-ip').value = data.subdomain.target_ip || '';
                        document.getElementById('edit-target-ip').disabled = data.subdomain.subdomain_type === 'hosted';
                        document.getElementById('edit-subdomain-modal').classList.remove('hidden');
                    } else {
                        alert('Błąd: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Wystąpił błąd podczas pobierania danych subdomeny.');
                });
        }
        
        // Close edit modal
        function closeEditModal() {
            document.getElementById('edit-subdomain-modal').classList.add('hidden');
        }
        
        // Handle edit subdomain form submission
        document.getElementById('edit-subdomain-form').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData();
            formData.append('action', 'update');
            formData.append('subdomain_id', document.getElementById('edit-subdomain-id').value);
            formData.append('target_ip', document.getElementById('edit-target-ip').value);
            
            fetch('/api/edit_subdomain.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Subdomena została zaktualizowana!');
                    closeEditModal();
                    location.reload();
                } else {
                    alert('Błąd: ' + data.message);
                }
            })
            .catch(error => {
                alert('Wystąpił błąd podczas aktualizacji subdomeny.');
            });
        });

        // Manage files
        function manageFiles(subdomainName) {
            showSection('files');
            // Load file manager for specific subdomain
            loadFileManager(subdomainName);
        }

        function loadFileManager(subdomainName) {
            const filesContent = document.getElementById('files-content');
            filesContent.innerHTML = '<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-2xl text-gray-400"></i><p class="text-gray-600 mt-2">Ładowanie...</p></div>';
            
            fetch('/api/get_files.php?subdomain=' + encodeURIComponent(subdomainName), {
                credentials: 'include'
            })
                .then(response => response.text())
                .then(html => {
                    filesContent.innerHTML = html;
                })
                .catch(error => {
                    filesContent.innerHTML = '<div class="text-center py-8"><i class="fas fa-exclamation-triangle text-2xl text-red-400"></i><p class="text-red-600 mt-2">Błąd podczas ładowania plików</p></div>';
                });
        }

        // Global functions for file management
        window.editFile = function(fileId, fileName) {
            console.log('editFile called with:', fileId, fileName);
            
            const editFileIdElement = document.getElementById('editFileId');
            const editorTitleElement = document.getElementById('editorTitle');
            const fileContentElement = document.getElementById('fileContent');
            const modalElement = document.getElementById('fileEditorModal');
            
            if (!editFileIdElement || !editorTitleElement || !fileContentElement || !modalElement) {
                alert('Błąd: Nie znaleziono elementów edytora plików.');
                return;
            }
            
            editFileIdElement.value = fileId;
            editorTitleElement.textContent = 'Edytuj plik: ' + fileName;
            
            // Load file content
            fetch('/api/get_file_content.php?file_id=' + fileId, {
                credentials: 'include'
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }
                    return response.text();
                })
                .then(content => {
                    fileContentElement.value = content;
                    modalElement.classList.remove('hidden');
                })
                .catch(error => {
                    console.error('Error loading file content:', error);
                    alert('Nie udało się załadować zawartości pliku: ' + error.message);
                });
        };
        
        window.deleteFile = function(fileId, fileName) {
            if (confirm('Czy na pewno chcesz usunąć plik "' + fileName + '"?')) {
                fetch('/api/delete_file.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ file_id: fileId }),
                    credentials: 'include'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Plik został usunięty!');
                        location.reload();
                    } else {
                        alert('Błąd: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Wystąpił błąd podczas usuwania pliku.');
                });
            }
        };
        
        window.closeFileEditor = function() {
            const modalElement = document.getElementById('fileEditorModal');
            const formElement = document.getElementById('fileEditorForm');
            if (modalElement) modalElement.classList.add('hidden');
            if (formElement) formElement.reset();
        };
        
        // File editor form submission
        document.addEventListener('DOMContentLoaded', function() {
            const fileEditorForm = document.getElementById('fileEditorForm');
            const fileEditorModal = document.getElementById('fileEditorModal');
            
            if (fileEditorForm) {
                fileEditorForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    
                    fetch('/api/save_file.php', {
                        method: 'POST',
                        body: formData,
                        credentials: 'include'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Plik został zapisany!');
                            closeFileEditor();
                            location.reload();
                        } else {
                            alert('Błąd: ' + data.message);
                        }
                    })
                    .catch(error => {
                        alert('Wystąpił błąd podczas zapisywania pliku.');
                    });
                });
            }
            
            // Close modal on outside click
            if (fileEditorModal) {
                fileEditorModal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeFileEditor();
                    }
                });
            }
        });

        // Check URL parameters for section
        const urlParams = new URLSearchParams(window.location.search);
        const section = urlParams.get('section');
        
        if (section) {
            showSection(section);
        } else {
            // Set default active section
            document.querySelector('.nav-link').classList.add('bg-indigo-50', 'text-indigo-600');
        }
    </script>
</body>
</html>