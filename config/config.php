<?php
/**
 * Główny plik konfiguracyjny
 * Ekipowo.pl - System zarządzania subdomenami
 */

// Rozpoczęcie sesji
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ustawienia błędów (wyłącz w produkcji)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Definicje ścieżek
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('CLASSES_PATH', ROOT_PATH . '/classes');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('TEMPLATES_PATH', ROOT_PATH . '/templates');
define('ASSETS_PATH', ROOT_PATH . '/assets');

// Ustawienia aplikacji
define('SITE_NAME', 'Ekipowo.pl');
define('SITE_URL', 'https://ekipowo.pl');
define('ADMIN_EMAIL', 'admin@dziondzio.pl');

// Ustawienia bezpieczeństwa
define('SESSION_LIFETIME', 3600 * 24 * 7); // 7 dni
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minut

// Ustawienia plików
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_FILE_TYPES', ['html', 'css', 'js', 'txt', 'json', 'xml', 'ico', 'png', 'jpg', 'jpeg', 'gif', 'svg']);

// Ustawienia Cloudflare
define('CLOUDFLARE_API_URL', 'https://api.cloudflare.com/client/v4');
define('DOMAIN_NAME', 'ekipowo.pl');

// Ustawienia Cloudflare Turnstile CAPTCHA
define('TURNSTILE_SITE_KEY', 'X'); // Zmień na swój Site Key
define('TURNSTILE_SECRET_KEY', 'X'); // Zmień na swój Secret Key
define('TURNSTILE_VERIFY_URL', 'https://challenges.cloudflare.com/turnstile/v0/siteverify');

// Ustawienia SMTP dla PHPMailer
define('SMTP_HOST', 'X'); // Zmień na swój serwer SMTP
define('SMTP_AUTH', true);
define('SMTP_USERNAME', 'X'); // Zmień na swój email
define('SMTP_PASSWORD', 'X'); // Zmień na hasło aplikacji
define('SMTP_SECURE', 'ssl'); // 'tls' lub 'ssl'
define('SMTP_PORT', 465); // 587 dla TLS, 465 dla SSL
define('SMTP_DEBUG', 0); // 0 = wyłączone, 1 = błędy, 2 = wszystko

// Dołącz klasę DatabaseManager
require_once CONFIG_PATH . '/database.php';

// Autoloader Composer (dla PHPMailer)
if (file_exists(ROOT_PATH . '/vendor/autoload.php')) {
    require_once ROOT_PATH . '/vendor/autoload.php';
}

// Autoloader dla klas
spl_autoload_register(function ($class_name) {
    $file = CLASSES_PATH . '/' . $class_name . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Funkcje pomocnicze
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function validate_subdomain($subdomain) {
    return preg_match('/^[a-z0-9][a-z0-9\-]{1,28}[a-z0-9]$/', $subdomain) || 
           (strlen($subdomain) >= 3 && strlen($subdomain) <= 30 && preg_match('/^[a-z0-9\-]+$/', $subdomain));
}

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validate_ip($ip) {
    return filter_var($ip, FILTER_VALIDATE_IP) !== false;
}

function generate_token($length = 32) {
    return bin2hex(random_bytes($length));
}

function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function is_admin() {
    return isset($_SESSION['is_admin']) && ($_SESSION['is_admin'] === true || $_SESSION['is_admin'] === 1 || $_SESSION['is_admin'] === '1');
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: /login.php');
        exit;
    }
}

function require_admin() {
    require_login();
    if (!is_admin()) {
        header('Location: /dashboard.php');
        exit;
    }
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function set_flash_message($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

function get_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

function format_file_size($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

function log_activity($user_id, $action, $description = '', $ip_address = null) {
    try {
        $db = new DatabaseManager();
        $ip_address = $ip_address ?: $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $db->execute(
            "INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)",
            [$user_id, $action, $description, $ip_address, $user_agent]
        );
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

// Sprawdzenie czy katalogi istnieją, jeśli nie - utwórz je
$required_dirs = [UPLOADS_PATH, UPLOADS_PATH . '/subdomains'];
foreach ($required_dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Ustawienia czasowe
date_default_timezone_set('Europe/Warsaw');

// Zabezpieczenie przed CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = generate_token();
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function get_csrf_token() {
    return $_SESSION['csrf_token'] ?? '';
}
?>