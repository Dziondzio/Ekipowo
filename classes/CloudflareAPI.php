<?php
/**
 * Klasa CloudflareAPI - integracja z Cloudflare
 * Ekipowo.pl - System zarządzania subdomenami
 */

class CloudflareAPI {
    private $api_token;
    private $zone_id;
    private $api_url;
    
    public function __construct() {
        $this->api_url = CLOUDFLARE_API_URL;
        $this->loadSettings();
    }
    
    /**
     * Załaduj ustawienia Cloudflare z bazy danych
     */
    private function loadSettings() {
        try {
            $db = new DatabaseManager();
            
            $token_setting = $db->selectOne(
                "SELECT setting_value FROM system_settings WHERE setting_key = 'cloudflare_api_token'"
            );
            
            $zone_setting = $db->selectOne(
                "SELECT setting_value FROM system_settings WHERE setting_key = 'cloudflare_zone_id'"
            );
            
            $this->api_token = $token_setting['setting_value'] ?? '';
            $this->zone_id = $zone_setting['setting_value'] ?? '';
            
            if (empty($this->api_token) || empty($this->zone_id)) {
                throw new Exception('Cloudflare API nie jest skonfigurowane');
            }
            
        } catch (Exception $e) {
            error_log("Cloudflare settings error: " . $e->getMessage());
            throw new Exception('Błąd konfiguracji Cloudflare API');
        }
    }
    
    /**
     * Dodaj rekord A
     */
    public function addARecord($subdomain_name, $ip_address, $proxied = true) {
        try {
            $full_domain = $subdomain_name . '.' . DOMAIN_NAME;
            
            $data = [
                'type' => 'A',
                'name' => $full_domain,
                'content' => $ip_address,
                'ttl' => 300,
                'proxied' => $proxied
            ];
            
            $response = $this->makeRequest('POST', "/zones/{$this->zone_id}/dns_records", $data);
            
            if ($response['success']) {
                return [
                    'success' => true,
                    'record_id' => $response['result']['id'],
                    'message' => 'Rekord DNS został dodany'
                ];
            } else {
                throw new Exception('Cloudflare API error: ' . json_encode($response['errors']));
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Dodaj rekord CNAME
     */
    public function addCNAMERecord($subdomain_name, $target, $proxied = true) {
        try {
            $full_domain = $subdomain_name . '.' . DOMAIN_NAME;
            
            $data = [
                'type' => 'CNAME',
                'name' => $full_domain,
                'content' => $target,
                'ttl' => 300,
                'proxied' => $proxied
            ];
            
            $response = $this->makeRequest('POST', "/zones/{$this->zone_id}/dns_records", $data);
            
            if ($response['success']) {
                return [
                    'success' => true,
                    'record_id' => $response['result']['id'],
                    'message' => 'Rekord CNAME został dodany'
                ];
            } else {
                throw new Exception('Cloudflare API error: ' . json_encode($response['errors']));
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Usuń rekord DNS
     */
    public function deleteRecord($record_id) {
        try {
            $response = $this->makeRequest('DELETE', "/zones/{$this->zone_id}/dns_records/{$record_id}");
            
            if ($response['success']) {
                return [
                    'success' => true,
                    'message' => 'Rekord DNS został usunięty'
                ];
            } else {
                throw new Exception('Cloudflare API error: ' . json_encode($response['errors']));
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Aktualizuj rekord DNS
     */
    public function updateRecord($record_id, $ip_address, $proxied = null) {
        try {
            $data = [
                'content' => $ip_address
            ];
            
            if ($proxied !== null) {
                $data['proxied'] = $proxied;
            }
            
            $response = $this->makeRequest('PATCH', "/zones/{$this->zone_id}/dns_records/{$record_id}", $data);
            
            if ($response['success']) {
                return [
                    'success' => true,
                    'message' => 'Rekord DNS został zaktualizowany'
                ];
            } else {
                throw new Exception('Cloudflare API error: ' . json_encode($response['errors']));
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Ustaw proxy dla rekordu DNS
     */
    public function setProxyStatus($record_id, $proxied = true) {
        try {
            $data = [
                'proxied' => $proxied
            ];
            
            $response = $this->makeRequest('PATCH', "/zones/{$this->zone_id}/dns_records/{$record_id}", $data);
            
            if ($response['success']) {
                return [
                    'success' => true,
                    'message' => 'Status proxy został zaktualizowany'
                ];
            } else {
                throw new Exception('Cloudflare API error: ' . json_encode($response['errors']));
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Pobierz wszystkie rekordy DNS
     */
    public function listRecords($type = null, $name = null) {
        try {
            $params = [];
            if ($type) $params['type'] = $type;
            if ($name) $params['name'] = $name;
            
            $query_string = !empty($params) ? '?' . http_build_query($params) : '';
            
            $response = $this->makeRequest('GET', "/zones/{$this->zone_id}/dns_records{$query_string}");
            
            if ($response['success']) {
                return [
                    'success' => true,
                    'records' => $response['result']
                ];
            } else {
                throw new Exception('Cloudflare API error: ' . json_encode($response['errors']));
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Sprawdź status strefy
     */
    public function getZoneInfo() {
        try {
            $response = $this->makeRequest('GET', "/zones/{$this->zone_id}");
            
            if ($response['success']) {
                return [
                    'success' => true,
                    'zone' => $response['result']
                ];
            } else {
                throw new Exception('Cloudflare API error: ' . json_encode($response['errors']));
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Wykonaj żądanie do API Cloudflare
     */
    private function makeRequest($method, $endpoint, $data = null) {
        $url = $this->api_url . $endpoint;
        
        $headers = [
            'Authorization: Bearer ' . $this->api_token,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => $method
        ]);
        
        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            throw new Exception("cURL error: {$curl_error}");
        }
        
        if ($http_code >= 400) {
            throw new Exception("HTTP error {$http_code}: {$response}");
        }
        
        $decoded_response = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON response: {$response}");
        }
        
        return $decoded_response;
    }
    
    /**
     * Sprawdź czy API jest skonfigurowane
     */
    public function isConfigured() {
        return !empty($this->api_token) && !empty($this->zone_id);
    }
    
    /**
     * Testuj połączenie z API
     */
    public function testConnection() {
        try {
            $zone_info = $this->getZoneInfo();
            return $zone_info['success'];
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Pobierz statystyki użycia API
     */
    public function getAPIUsage() {
        try {
            $response = $this->makeRequest('GET', '/user/tokens/verify');
            
            if ($response['success']) {
                return [
                    'success' => true,
                    'token_info' => $response['result']
                ];
            } else {
                throw new Exception('Cloudflare API error: ' . json_encode($response['errors']));
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
?>