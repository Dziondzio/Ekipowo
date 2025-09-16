<?php
/**
 * Klasa TurnstileVerifier - weryfikacja Cloudflare Turnstile CAPTCHA
 * Ekipowo.pl - System zarządzania subdomenami
 */

class TurnstileVerifier {
    private $secretKey;
    private $verifyUrl;
    
    public function __construct() {
        $this->secretKey = TURNSTILE_SECRET_KEY;
        $this->verifyUrl = TURNSTILE_VERIFY_URL;
    }
    
    /**
     * Weryfikuje token Turnstile CAPTCHA
     * 
     * @param string $token Token otrzymany z formularza
     * @param string $remoteIp Adres IP użytkownika (opcjonalny)
     * @return array Wynik weryfikacji
     */
    public function verify($token, $remoteIp = null) {
        try {
            // Sprawdź czy token został podany
            if (empty($token)) {
                return [
                    'success' => false,
                    'error' => 'Brak tokenu CAPTCHA'
                ];
            }
            
            // Przygotuj dane do wysłania
            $postData = [
                'secret' => $this->secretKey,
                'response' => $token
            ];
            
            // Dodaj IP jeśli zostało podane
            if ($remoteIp) {
                $postData['remoteip'] = $remoteIp;
            }
            
            // Wykonaj żądanie do Cloudflare
            $response = $this->makeRequest($postData);
            
            if ($response === false) {
                return [
                    'success' => false,
                    'error' => 'Błąd komunikacji z serwerem CAPTCHA'
                ];
            }
            
            $result = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'success' => false,
                    'error' => 'Nieprawidłowa odpowiedź serwera CAPTCHA'
                ];
            }
            
            // Sprawdź wynik weryfikacji
            if (isset($result['success']) && $result['success'] === true) {
                return [
                    'success' => true,
                    'challenge_ts' => $result['challenge_ts'] ?? null,
                    'hostname' => $result['hostname'] ?? null
                ];
            } else {
                $errorCodes = $result['error-codes'] ?? ['unknown-error'];
                return [
                    'success' => false,
                    'error' => 'Weryfikacja CAPTCHA nie powiodła się',
                    'error_codes' => $errorCodes
                ];
            }
            
        } catch (Exception $e) {
            error_log('Turnstile verification error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Błąd podczas weryfikacji CAPTCHA'
            ];
        }
    }
    
    /**
     * Wykonuje żądanie HTTP do API Cloudflare
     * 
     * @param array $postData Dane do wysłania
     * @return string|false Odpowiedź serwera lub false w przypadku błędu
     */
    private function makeRequest($postData) {
        $postFields = http_build_query($postData);
        
        // Użyj cURL jeśli dostępne
        if (function_exists('curl_init')) {
            return $this->makeCurlRequest($postFields);
        }
        
        // Fallback do file_get_contents
        return $this->makeFileGetContentsRequest($postFields);
    }
    
    /**
     * Wykonuje żądanie za pomocą cURL
     */
    private function makeCurlRequest($postFields) {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->verifyUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_USERAGENT => 'Ekipowo.pl Turnstile Verifier 1.0',
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($response === false || !empty($error)) {
            error_log('cURL error in Turnstile verification: ' . $error);
            return false;
        }
        
        if ($httpCode !== 200) {
            error_log('HTTP error in Turnstile verification: ' . $httpCode);
            return false;
        }
        
        return $response;
    }
    
    /**
     * Wykonuje żądanie za pomocą file_get_contents
     */
    private function makeFileGetContentsRequest($postFields) {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => $postFields,
                'timeout' => 10
            ]
        ]);
        
        $response = @file_get_contents($this->verifyUrl, false, $context);
        
        if ($response === false) {
            error_log('file_get_contents error in Turnstile verification');
            return false;
        }
        
        return $response;
    }
    
    /**
     * Pobiera Site Key dla frontendu
     */
    public static function getSiteKey() {
        return TURNSTILE_SITE_KEY;
    }
    
    /**
     * Sprawdza czy Turnstile jest poprawnie skonfigurowane
     */
    public static function isConfigured() {
        return defined('TURNSTILE_SITE_KEY') && 
               defined('TURNSTILE_SECRET_KEY') && 
               !empty(TURNSTILE_SITE_KEY) && 
               !empty(TURNSTILE_SECRET_KEY) &&
               TURNSTILE_SITE_KEY !== '0x4AAAAAAAxxxxxxxxxxxxxxxxxx' &&
               TURNSTILE_SECRET_KEY !== '0x4AAAAAAAxxxxxxxxxxxxxxxxxx';
    }
}
?>