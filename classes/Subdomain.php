<?php
/**
 * Klasa Subdomain - zarządzanie subdomenami
 * Ekipowo.pl - System zarządzania subdomenami
 */

// Sprawdź czy CONFIG_PATH jest zdefiniowane
if (!defined('CONFIG_PATH')) {
    define('CONFIG_PATH', dirname(__DIR__) . '/config');
}

require_once CONFIG_PATH . '/config.php';
require_once CONFIG_PATH . '/database.php';

class Subdomain {
    private $db;
    
    public function __construct() {
        $this->db = new DatabaseManager();
    }
    
    /**
     * Sprawdź dostępność subdomeny
     */
    public function checkAvailability($subdomain_name) {
        try {
            if (!validate_subdomain($subdomain_name)) {
                return [
                    'available' => false,
                    'message' => 'Nieprawidłowa nazwa subdomeny. Używaj małych liter, cyfr i myślników (3-30 znaków).'
                ];
            }
            
            // Sprawdź w bazie danych
            $existing = $this->db->selectOne(
                "SELECT id FROM subdomains WHERE subdomain_name = ? AND status != 'deleted'",
                [$subdomain_name]
            );
            
            if ($existing) {
                return [
                    'available' => false,
                    'message' => "Subdomena <strong>{$subdomain_name}</strong> jest już zajęta."
                ];
            }
            
            // Sprawdź zarezerwowane nazwy
            $reserved = ['www', 'admin', 'root', 'api', 'mail', 'ftp', 'smtp', 'pop', 'imap', 'ns1', 'ns2', 'mx', 'cpanel', 'whm', 'webmail'];
            if (in_array($subdomain_name, $reserved)) {
                return [
                    'available' => false,
                    'message' => "Subdomena <strong>{$subdomain_name}</strong> jest zarezerwowana przez system."
                ];
            }
            
            return [
                'available' => true,
                'message' => "Subdomena <strong>{$subdomain_name}</strong> jest dostępna!"
            ];
            
        } catch (Exception $e) {
            return [
                'available' => false,
                'message' => 'Błąd podczas sprawdzania dostępności subdomeny.'
            ];
        }
    }
    
    /**
     * Sprawdź czy subdomena jest dostępna (wrapper dla checkAvailability)
     */
    public function isAvailable($subdomain_name) {
        $result = $this->checkAvailability($subdomain_name);
        return $result['available'];
    }
    
    /**
     * Utwórz nową subdomenę
     */
    public function create($user_id, $subdomain_name, $type, $target_ip = null) {
        try {
            // Sprawdź dostępność
            $availability = $this->checkAvailability($subdomain_name);
            if (!$availability['available']) {
                throw new Exception($availability['message']);
            }
            
            // Sprawdź limit subdomen na użytkownika
            $user_subdomains = $this->getUserSubdomains($user_id);
            $max_subdomains = $this->getMaxSubdomainsPerUser();
            
            if (count($user_subdomains) >= $max_subdomains) {
                throw new Exception("Osiągnięto maksymalną liczbę subdomen ({$max_subdomains}). Usuń nieużywane subdomeny lub skontaktuj się z administratorem.");
            }
            
            // Walidacja typu
            if (!in_array($type, ['redirect', 'hosted'])) {
                throw new Exception('Nieprawidłowy typ subdomeny.');
            }
            
            // Walidacja IP dla typu redirect
            if ($type === 'redirect') {
                if (empty($target_ip) || !validate_ip($target_ip)) {
                    throw new Exception('Podaj prawidłowy adres IP.');
                }
            }
            
            // Rozpocznij transakcję
            $this->db->beginTransaction();
            
            try {
                // Wstaw subdomenę do bazy
                $result = $this->db->execute(
                    "INSERT INTO subdomains (user_id, subdomain_name, subdomain_type, target_ip, status) VALUES (?, ?, ?, ?, 'pending')",
                    [$user_id, $subdomain_name, $type, $target_ip]
                );
                
                if (!$result) {
                    throw new Exception('Błąd podczas tworzenia subdomeny w bazie danych.');
                }
                
                $subdomain_id = $this->db->lastInsertId();
                
                // Utwórz katalog dla hostowanych subdomen
                if ($type === 'hosted') {
                    $subdomain_dir = UPLOADS_PATH . '/subdomains/' . $subdomain_name;
                    if (!is_dir($subdomain_dir)) {
                        mkdir($subdomain_dir, 0755, true);
                    }
                    
                    // Utwórz konfigurację Nginx
                    $nginx_result = $this->createNginxConfig($subdomain_name);
                    if (!$nginx_result['success']) {
                        error_log("Failed to create Nginx config for {$subdomain_name}: " . $nginx_result['message']);
                    }
                }
                
                // Dodaj rekord DNS w Cloudflare
                $cloudflare_result = $this->addCloudflareRecord($subdomain_name, $type, $target_ip);
                
                if ($cloudflare_result['success']) {
                    // Aktualizuj rekord z ID Cloudflare
                    $this->db->execute(
                        "UPDATE subdomains SET cloudflare_record_id = ?, status = 'active' WHERE id = ?",
                        [$cloudflare_result['record_id'], $subdomain_id]
                    );
                } else {
                    // Jeśli Cloudflare nie działa, pozostaw jako pending
                    error_log("Cloudflare error for subdomain {$subdomain_name}: " . $cloudflare_result['message']);
                }
                
                $this->db->commit();
                
                log_activity($user_id, 'subdomain_created', "Utworzono subdomenę: {$subdomain_name} (typ: {$type})");
                
                return [
                    'success' => true,
                    'subdomain_id' => $subdomain_id,
                    'message' => "Subdomena {$subdomain_name}." . DOMAIN_NAME . " została utworzona!",
                    'cloudflare_status' => $cloudflare_result['success'] ? 'active' : 'pending'
                ];
                
            } catch (Exception $e) {
                $this->db->rollback();
                throw $e;
            }
            
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
    
    /**
     * Pobierz subdomeny użytkownika
     */
    public function getUserSubdomains($user_id) {
        return $this->db->select(
            "SELECT * FROM subdomains WHERE user_id = ? AND status != 'deleted' ORDER BY created_at DESC",
            [$user_id]
        );
    }
    
    /**
     * Pobierz szczegóły subdomeny
     */
    public function getSubdomainById($subdomain_id, $user_id = null) {
        $query = "SELECT * FROM subdomains WHERE id = ?";
        $params = [$subdomain_id];
        
        if ($user_id !== null) {
            $query .= " AND user_id = ?";
            $params[] = $user_id;
        }
        
        return $this->db->selectOne($query, $params);
    }
    
    /**
     * Pobierz szczegóły subdomeny (alias dla getSubdomainById)
     */
    public function getSubdomainDetails($subdomain_id, $user_id = null) {
        return $this->getSubdomainById($subdomain_id, $user_id);
    }
    
    /**
     * Usuń subdomenę (alias dla delete)
     */
    public function deleteSubdomain($subdomain_id, $user_id = null) {
        if ($user_id === null && isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
        }
        
        return $this->delete($subdomain_id, $user_id);
    }
    
    /**
     * Usuń subdomenę
     */
    public function delete($subdomain_id, $user_id) {
        try {
            $subdomain = $this->getSubdomainById($subdomain_id, $user_id);
            
            if (!$subdomain) {
                throw new Exception('Subdomena nie została znaleziona.');
            }
            
            $this->db->beginTransaction();
            
            try {
                // Usuń rekord DNS z Cloudflare
                if ($subdomain['cloudflare_record_id']) {
                    $this->deleteCloudflareRecord($subdomain['cloudflare_record_id']);
                }
                
                // Usuń pliki dla hostowanych subdomen
                if ($subdomain['subdomain_type'] === 'hosted') {
                    $subdomain_dir = UPLOADS_PATH . '/subdomains/' . $subdomain['subdomain_name'];
                    if (is_dir($subdomain_dir)) {
                        $this->deleteDirectory($subdomain_dir);
                    }
                    
                    // Usuń konfigurację Nginx
                    $nginx_result = $this->deleteNginxConfig($subdomain['subdomain_name']);
                    if (!$nginx_result['success']) {
                        error_log("Failed to delete Nginx config for {$subdomain['subdomain_name']}: " . $nginx_result['message']);
                    }
                }
                
                // Oznacz jako usunięte w bazie
                $this->db->execute(
                    "UPDATE subdomains SET status = 'deleted' WHERE id = ?",
                    [$subdomain_id]
                );
                
                $this->db->commit();
                
                log_activity($user_id, 'subdomain_deleted', "Usunięto subdomenę: {$subdomain['subdomain_name']}");
                
                return [
                    'success' => true,
                    'message' => 'Subdomena została usunięta.'
                ];
                
            } catch (Exception $e) {
                $this->db->rollback();
                throw $e;
            }
            
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
    
    /**
     * Pobierz wszystkie subdomeny (dla admina)
     */
    public function getAllSubdomains($limit = 50, $offset = 0) {
        return $this->db->select(
            "SELECT s.*, u.username FROM subdomains s 
             LEFT JOIN users u ON s.user_id = u.id 
             WHERE s.status != 'deleted' 
             ORDER BY s.created_at DESC 
             LIMIT ? OFFSET ?",
            [$limit, $offset]
        );
    }
    
    /**
     * Dodaj rekord DNS w Cloudflare
     */
    private function addCloudflareRecord($subdomain_name, $type, $target_ip = null) {
        try {
            $cloudflare = new CloudflareAPI();
            
            if ($type === 'redirect') {
                return $cloudflare->addARecord($subdomain_name, $target_ip);
            } else {
                // Dla hostowanych subdomen, wskaż na serwer
                $server_ip = $this->getServerIP();
                return $cloudflare->addARecord($subdomain_name, $server_ip);
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Usuń rekord DNS z Cloudflare
     */
    private function deleteCloudflareRecord($record_id) {
        try {
            $cloudflare = new CloudflareAPI();
            return $cloudflare->deleteRecord($record_id);
        } catch (Exception $e) {
            error_log("Failed to delete Cloudflare record {$record_id}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Pobierz IP serwera
     */
    private function getServerIP() {
        // TODO: Pobierz z ustawień systemu
        return '83.168.107.164'; // Placeholder
    }
    
    /**
     * Pobierz maksymalną liczbę subdomen na użytkownika
     */
    public function getMaxSubdomainsPerUser() {
        $setting = $this->db->selectOne(
            "SELECT setting_value FROM system_settings WHERE setting_key = 'max_subdomains_per_user'"
        );
        return $setting ? (int)$setting['setting_value'] : 5;
    }
    
    /**
     * Usuń katalog rekursywnie
     */
    private function deleteDirectory($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        
        return rmdir($dir);
    }
    
    /**
     * Utwórz konfigurację Nginx dla subdomeny
     */
    private function createNginxConfig($subdomain_name) {
        try {
            $config_dir = '/etc/nginx/sites-available';
            $config_file = $config_dir . '/' . $subdomain_name . '.ekipowo.pl';
            $enabled_dir = '/etc/nginx/sites-enabled';
            $enabled_file = $enabled_dir . '/' . $subdomain_name . '.ekipowo.pl';
            
            // Sprawdź czy katalogi istnieją
            if (!is_dir($config_dir)) {
                throw new Exception('Katalog konfiguracji Nginx nie istnieje: ' . $config_dir);
            }
            
            // Ścieżka do plików subdomeny
            $document_root = UPLOADS_PATH . '/subdomains/' . $subdomain_name;
            
            // Szablon konfiguracji Nginx
            $config_content = "server {
    listen 443 ssl;
    listen [::]:443 ssl;
    http2 on;  # Nowoczesna składnia dla HTTP/2
    server_name {$subdomain_name}.ekipowo.pl;

    root {$document_root};
    index index.php index.html index.htm;

    # Ustawienia dla rzeczywistych adresów IP z Cloudflare
    set_real_ip_from 173.245.48.0/20;
    set_real_ip_from 103.21.244.0/22;
    set_real_ip_from 103.22.200.0/22;
    set_real_ip_from 103.31.4.0/22;
    set_real_ip_from 141.101.64.0/18;
    set_real_ip_from 104.16.0.0/12;
    set_real_ip_from 108.162.192.0/18;
    set_real_ip_from 131.0.72.0/22;
    set_real_ip_from 162.158.0.0/15;
    set_real_ip_from 172.64.0.0/13;
    set_real_ip_from 188.114.96.0/20;
    set_real_ip_from 190.93.240.0/20;
    set_real_ip_from 197.234.240.0/22;
    set_real_ip_from 2400:cb00::/32;
    set_real_ip_from 2606:4700::/32;
    set_real_ip_from 2803:f800::/32;
    set_real_ip_from 2405:b500::/32;
    set_real_ip_from 2607:f8b0::/32;

    real_ip_header CF-Connecting-IP;  # Użyj nagłówka Cloudflare

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    client_max_body_size 1G;

    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;  # Zawiera fastcgi_pass
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }

    location ~* \.(jpg|jpeg|png|gif|ico|css|js)\$ {
        expires 30d;
        add_header Cache-Control \"public, no-transform\";
    }
}

server {
    if (\$host = {$subdomain_name}.ekipowo.pl) {
        return 301 https://\$host\$request_uri;
    } # managed by Certbot

    listen 80;
    listen [::]:80;
    server_name {$subdomain_name}.ekipowo.pl;
    return 301 https://\$host\$request_uri;
}
";
            
            // Użyj bezpiecznego wrappera do utworzenia konfiguracji Nginx
            $wrapper_path = ROOT_PATH . '/scripts/secure_nginx_wrapper.sh';
            $create_cmd = "sudo '{$wrapper_path}' create '{$subdomain_name}' '{$document_root}'";
            
            exec($create_cmd . ' 2>&1', $create_output, $create_return);
            
            if ($create_return !== 0) {
                throw new Exception('Nie udało się utworzyć konfiguracji Nginx: ' . implode('\n', $create_output));
            }
            
            return [
                'success' => true,
                'message' => 'Konfiguracja Nginx została utworzona pomyślnie'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Usuń konfigurację Nginx dla subdomeny
     */
    private function deleteNginxConfig($subdomain_name) {
        try {
            $config_file = '/etc/nginx/sites-available/' . $subdomain_name . '.ekipowo.pl';
            $enabled_file = '/etc/nginx/sites-enabled/' . $subdomain_name . '.ekipowo.pl';
            
            // Użyj bezpiecznego wrappera do usunięcia konfiguracji Nginx
            $wrapper_path = ROOT_PATH . '/scripts/secure_nginx_wrapper.sh';
            $delete_cmd = "sudo '{$wrapper_path}' delete '{$subdomain_name}'";
            
            exec($delete_cmd . ' 2>&1', $delete_output, $delete_return);
            
            if ($delete_return !== 0) {
                throw new Exception('Nie udało się usunąć konfiguracji Nginx: ' . implode('\n', $delete_output));
            }
            
            return [
                'success' => true,
                'message' => 'Konfiguracja Nginx została usunięta'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    

}
?>