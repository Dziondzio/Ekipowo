<?php
// Nagłówki dla AJAX
header('Access-Control-Allow-Credentials: true');
header('Cache-Control: no-cache, must-revalidate');

require_once '../config/config.php';

// Sprawdź czy użytkownik jest zalogowany
if (!is_logged_in()) {
    redirect('/login.php');
}

$subdomain_name = sanitize_input($_GET['subdomain'] ?? '');

if (empty($subdomain_name)) {
    echo '<div class="text-center py-8"><i class="fas fa-exclamation-triangle text-2xl text-red-400"></i><p class="text-red-600 mt-2">Nie podano nazwy subdomeny</p></div>';
    exit;
}

// Sprawdź czy subdomena należy do użytkownika
$subdomain = new Subdomain();
$user_subdomains = $subdomain->getUserSubdomains($_SESSION['user_id']);
$current_subdomain = null;

foreach ($user_subdomains as $sub) {
    if ($sub['subdomain_name'] === $subdomain_name && $sub['subdomain_type'] === 'hosted') {
        $current_subdomain = $sub;
        break;
    }
}

if (!$current_subdomain) {
    echo '<div class="text-center py-8"><i class="fas fa-exclamation-triangle text-2xl text-red-400"></i><p class="text-red-600 mt-2">Subdomena nie została znaleziona lub nie jest hostowana</p></div>';
    exit;
}

// Pobierz pliki subdomeny
$db = new DatabaseManager();
$files = $db->select(
    "SELECT * FROM subdomain_files WHERE subdomain_id = ? ORDER BY file_path",
    [$current_subdomain['id']]
);

// Ścieżka do katalogu subdomeny
$subdomain_path = UPLOADS_PATH . '/subdomains/' . $subdomain_name;
?>

<div class="mb-6">
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold text-gray-900">
            Pliki dla <?= htmlspecialchars($subdomain_name) ?>.<?= DOMAIN_NAME ?>
        </h2>
        <a href="https://<?= htmlspecialchars($subdomain_name) ?>.<?= DOMAIN_NAME ?>" target="_blank" class="text-indigo-600 hover:text-indigo-800 text-sm">
            <i class="fas fa-external-link-alt mr-1"></i>
            Otwórz stronę
        </a>
    </div>
    <p class="text-sm text-gray-600 mt-1">
        Wgraj pliki HTML, CSS i JavaScript dla swojej subdomeny
    </p>
</div>

<!-- Upload form -->
<div class="bg-gray-50 rounded-lg p-4 mb-6">
    <h3 class="text-md font-medium text-gray-900 mb-3">Wgraj nowe pliki</h3>
    <form id="uploadForm" method="POST" action="/api/upload_files.php" enctype="multipart/form-data" class="space-y-4">
        <input type="hidden" name="MAX_FILE_SIZE" value="<?= MAX_FILE_SIZE ?>">
        <input type="hidden" name="subdomain_name" value="<?= htmlspecialchars($subdomain_name) ?>">
        
        <div>
            <label for="files" class="block text-sm font-medium text-gray-700 mb-2">
                Wybierz pliki
            </label>
            <input 
                type="file" 
                id="files" 
                name="files[]" 
                multiple 
                required
                accept=".html,.htm,.css,.js,.txt,.json,.xml,.svg,.ico,.png,.jpg,.jpeg,.gif,.webp"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
            <p class="text-xs text-gray-500 mt-1">
                Dozwolone typy: HTML, CSS, JS, TXT, JSON, XML, SVG, ICO, PNG, JPG, GIF, WEBP (max <?= format_file_size(MAX_FILE_SIZE) ?> na plik)
            </p>
        </div>
        
        <div>
            <label for="upload_path" class="block text-sm font-medium text-gray-700 mb-2">
                Ścieżka docelowa (opcjonalna)
            </label>
            <input 
                type="text" 
                id="upload_path" 
                name="upload_path" 
                placeholder="np. css/ lub js/script.js"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
            <p class="text-xs text-gray-500 mt-1">
                Pozostaw puste, aby wgrać do katalogu głównego
            </p>
        </div>
        
        <button 
            type="submit" 
            class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition-colors flex items-center"
        >
            <i class="fas fa-upload mr-2"></i>
            Wgraj pliki
        </button>
    </form>
</div>

<!-- Files list -->
<div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
    <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
        <h3 class="text-md font-medium text-gray-900">Pliki na serwerze</h3>
    </div>
    
    <?php if (empty($files)): ?>
        <div class="p-8 text-center">
            <i class="fas fa-file text-4xl text-gray-300 mb-4"></i>
            <h4 class="text-lg font-medium text-gray-900 mb-2">Brak plików</h4>
            <p class="text-gray-600">Wgraj pierwszy plik, aby rozpocząć budowanie swojej strony</p>
        </div>
    <?php else: ?>
        <div class="divide-y divide-gray-200">
            <?php foreach ($files as $file): ?>
                <div class="px-4 py-3 flex items-center justify-between hover:bg-gray-50">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <?php
                            $extension = strtolower(pathinfo($file['file_path'], PATHINFO_EXTENSION));
                            $icon_class = 'fas fa-file';
                            $icon_color = 'text-gray-400';
                            
                            switch ($extension) {
                                case 'html':
                                case 'htm':
                                    $icon_class = 'fab fa-html5';
                                    $icon_color = 'text-orange-500';
                                    break;
                                case 'css':
                                    $icon_class = 'fab fa-css3-alt';
                                    $icon_color = 'text-blue-500';
                                    break;
                                case 'js':
                                    $icon_class = 'fab fa-js-square';
                                    $icon_color = 'text-yellow-500';
                                    break;
                                case 'png':
                                case 'jpg':
                                case 'jpeg':
                                case 'gif':
                                case 'webp':
                                    $icon_class = 'fas fa-image';
                                    $icon_color = 'text-green-500';
                                    break;
                                case 'svg':
                                    $icon_class = 'fas fa-vector-square';
                                    $icon_color = 'text-purple-500';
                                    break;
                                case 'json':
                                case 'xml':
                                    $icon_class = 'fas fa-code';
                                    $icon_color = 'text-indigo-500';
                                    break;
                            }
                            ?>
                            <i class="<?= $icon_class ?> <?= $icon_color ?> text-xl"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-gray-900">
                                <?= htmlspecialchars($file['file_path']) ?>
                            </p>
                            <p class="text-xs text-gray-500">
                                <?= format_file_size($file['file_size']) ?> • 
                                <?= date('d.m.Y H:i', strtotime($file['uploaded_at'])) ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-2">
                        <?php if (in_array($extension, ['html', 'htm', 'css', 'js', 'txt', 'json', 'xml'])): ?>
                            <button 
                            onclick="editFile(<?= $file['id'] ?>, '<?= htmlspecialchars($file['file_path']) ?>')" 
                            class="text-indigo-600 hover:text-indigo-800 text-sm"
                            title="Edytuj plik"
                        >
                            <i class="fas fa-edit"></i>
                        </button>
                        <?php endif; ?>
                        
                        <a 
                            href="/api/download_file.php?file_id=<?= $file['id'] ?>" 
                            class="text-green-600 hover:text-green-800 text-sm"
                            title="Pobierz plik"
                        >
                            <i class="fas fa-download"></i>
                        </a>
                        
                        <button 
                            onclick="deleteFile(<?= $file['id'] ?>, '<?= htmlspecialchars($file['file_path']) ?>')" 
                            class="text-red-600 hover:text-red-800 text-sm"
                            title="Usuń plik"
                        >
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- File editor modal -->
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

<script>
// Edit file - make it globally accessible
window.editFile = function(fileId, fileName) {
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
    console.log('Loading file content for ID:', fileId);
    fetch('get_file_content.php?file_id=' + fileId, {
        credentials: 'include'
    })
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.text();
        })
        .then(content => {
            console.log('Content loaded, length:', content.length);
            fileContentElement.value = content;
            modalElement.classList.remove('hidden');
        })
        .catch(error => {
            console.error('Error loading file content:', error);
            alert('Nie udało się załadować zawartości pliku: ' + error.message);
        });
};

// Close file editor - make it globally accessible
window.closeFileEditor = function() {
    document.getElementById('fileEditorModal').classList.add('hidden');
    document.getElementById('fileEditorForm').reset();
};

// Delete file - make it globally accessible
window.deleteFile = function(fileId, fileName) {
    if (confirm('Czy na pewno chcesz usunąć plik "' + fileName + '"?')) {
        fetch('delete_file.php', {
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

document.addEventListener('DOMContentLoaded', function() {
    // Debug: sprawdź czy funkcje są dostępne
    console.log('editFile function available:', typeof window.editFile);
    console.log('deleteFile function available:', typeof window.deleteFile);
    console.log('closeFileEditor function available:', typeof window.closeFileEditor);
    
    // Upload form
    const uploadForm = document.getElementById('uploadForm');
    if (!uploadForm) {
        console.error('Upload form not found!');
        return;
    }
    
    uploadForm.addEventListener('submit', function(e) {
        // Nie blokujemy domyślnego zachowania - pozwalamy na normalne przesłanie formularza
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Wgrywanie...';
        submitBtn.disabled = true;
    });



    // File editor form
    document.getElementById('fileEditorForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('save_file.php', {
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

    // Close modal on outside click
    document.getElementById('fileEditorModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeFileEditor();
        }
    });
});
</script>