<?php
/**
 * Klasa User - zarządzanie użytkownikami
 * Ekipowo.pl - System zarządzania subdomenami
 */

require_once CONFIG_PATH . '/database.php';

class User {
    private $db;
    private $id;
    private $username;
    private $email;
    private $full_name;
    private $is_admin;
    private $is_active;
    private $created_at;
    private $last_login;
    private $email_verified;
    
    public function __construct() {
        $this->db = new DatabaseManager();
    }
    
    /**
     * Rejestracja nowego użytkownika
     */
    public function register($username, $email, $password, $full_name = '') {
        try {
            // Wyczyść stare niezweryfikowane konta przed rejestracją
            $this->cleanupUnverifiedAccounts();
            
            // Sprawdź czy użytkownik już istnieje
            if ($this->userExists($username, $email)) {
                throw new Exception('Użytkownik o podanej nazwie lub emailu już istnieje');
            }
            
            // Walidacja danych
            if (!$this->validateUsername($username)) {
                throw new Exception('Nieprawidłowa nazwa użytkownika. Używaj 3-30 znaków: litery, cyfry, podkreślenia');
            }
            
            if (!validate_email($email)) {
                throw new Exception('Nieprawidłowy adres email');
            }
            
            if (strlen($password) < PASSWORD_MIN_LENGTH) {
                throw new Exception('Hasło musi mieć co najmniej ' . PASSWORD_MIN_LENGTH . ' znaków');
            }
            
            // Hash hasła
            $password_hash = hash_password($password);
            $verification_token = generate_token();
            
            // Wstaw użytkownika do bazy
            $result = $this->db->execute(
                "INSERT INTO users (username, email, password_hash, full_name, verification_token) VALUES (?, ?, ?, ?, ?)",
                [$username, $email, $password_hash, $full_name, $verification_token]
            );
            
            if ($result) {
                $user_id = $this->db->lastInsertId();
                log_activity($user_id, 'user_registered', 'Nowy użytkownik zarejestrowany: ' . $username);
                
                // Wyślij email weryfikacyjny
                $email_sent = $this->sendVerificationEmail($email, $username, $verification_token);
                
                return [
                    'success' => true,
                    'user_id' => $user_id,
                    'verification_token' => $verification_token,
                    'email_sent' => $email_sent
                ];
            }
            
            throw new Exception('Błąd podczas rejestracji użytkownika');
            
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
    
    /**
     * Logowanie użytkownika
     */
    public function login($username_or_email, $password, $remember_me = false) {
        try {
            // Sprawdź liczbę prób logowania
            if ($this->isLoginBlocked($username_or_email)) {
                throw new Exception('Zbyt wiele nieudanych prób logowania. Spróbuj ponownie za ' . (LOGIN_LOCKOUT_TIME / 60) . ' minut.');
            }
            
            // Znajdź użytkownika
            $user = $this->db->selectOne(
                "SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1",
                [$username_or_email, $username_or_email]
            );
            
            if (!$user || !verify_password($password, $user['password_hash'])) {
                $this->recordFailedLogin($username_or_email);
                throw new Exception('Nieprawidłowa nazwa użytkownika/email lub hasło');
            }
            
            // Sprawdź czy email jest zweryfikowany
            if (!$user['email_verified']) {
                throw new Exception('Konto nie zostało zweryfikowane. Sprawdź swoją skrzynkę email.');
            }
            
            // Zaloguj użytkownika
            $this->loadUserData($user);
            $this->createSession($remember_me);
            $this->updateLastLogin();
            $this->clearFailedLogins($username_or_email);
            
            log_activity($this->id, 'user_login', 'Użytkownik zalogowany');
            
            return [
                'success' => true,
                'user_id' => $this->id,
                'is_admin' => $this->is_admin
            ];
            
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
    
    /**
     * Wylogowanie użytkownika
     */
    public function logout() {
        if (isset($_SESSION['session_id'])) {
            $this->db->execute(
                "UPDATE user_sessions SET is_active = 0 WHERE id = ?",
                [$_SESSION['session_id']]
            );
        }
        
        if (isset($_SESSION['user_id'])) {
            log_activity($_SESSION['user_id'], 'user_logout', 'Użytkownik wylogowany');
        }
        
        session_destroy();
        return true;
    }
    
    /**
     * Weryfikacja emaila
     */
    public function verifyEmail($token) {
        try {
            $user = $this->db->selectOne(
                "SELECT id FROM users WHERE verification_token = ? AND email_verified = 0",
                [$token]
            );
            
            if (!$user) {
                throw new Exception('Nieprawidłowy token weryfikacyjny');
            }
            
            $result = $this->db->execute(
                "UPDATE users SET email_verified = 1, verification_token = NULL WHERE id = ?",
                [$user['id']]
            );
            
            if ($result) {
                log_activity($user['id'], 'email_verified', 'Email zweryfikowany');
                return true;
            }
            
            throw new Exception('Błąd podczas weryfikacji emaila');
            
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
    
    /**
     * Zmiana hasła
     */
    public function changePassword($user_id, $old_password, $new_password) {
        try {
            $user = $this->db->selectOne(
                "SELECT password_hash FROM users WHERE id = ?",
                [$user_id]
            );
            
            if (!$user || !verify_password($old_password, $user['password_hash'])) {
                throw new Exception('Nieprawidłowe obecne hasło');
            }
            
            if (strlen($new_password) < PASSWORD_MIN_LENGTH) {
                throw new Exception('Nowe hasło musi mieć co najmniej ' . PASSWORD_MIN_LENGTH . ' znaków');
            }
            
            $new_password_hash = hash_password($new_password);
            
            $result = $this->db->execute(
                "UPDATE users SET password_hash = ? WHERE id = ?",
                [$new_password_hash, $user_id]
            );
            
            if ($result) {
                log_activity($user_id, 'password_changed', 'Hasło zostało zmienione');
                return true;
            }
            
            throw new Exception('Błąd podczas zmiany hasła');
            
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
    
    /**
     * Pobierz dane użytkownika
     */
    public function getUserById($user_id) {
        return $this->db->selectOne(
            "SELECT id, username, email, full_name, is_admin, is_active, created_at, last_login, email_verified FROM users WHERE id = ?",
            [$user_id]
        );
    }
    
    /**
     * Pobierz wszystkich użytkowników (dla admina)
     */
    public function getAllUsers($limit = 50, $offset = 0) {
        return $this->db->select(
            "SELECT id, username, email, full_name, is_admin, is_active, created_at, last_login, email_verified FROM users ORDER BY created_at DESC LIMIT ? OFFSET ?",
            [$limit, $offset]
        );
    }
    
    /**
     * Sprawdź czy użytkownik istnieje
     */
    private function userExists($username, $email) {
        $user = $this->db->selectOne(
            "SELECT id FROM users WHERE username = ? OR email = ?",
            [$username, $email]
        );
        return $user !== false;
    }
    
    /**
     * Walidacja nazwy użytkownika
     */
    private function validateUsername($username) {
        return preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username);
    }
    
    /**
     * Sprawdź czy logowanie jest zablokowane
     */
    private function isLoginBlocked($username_or_email) {
        try {
            // Sprawdź liczbę nieudanych prób w ostatnim czasie
            $attempts = $this->db->selectOne(
                "SELECT COUNT(*) as count FROM failed_login_attempts WHERE (username = ? OR email = ?) AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)",
                [$username_or_email, $username_or_email, LOGIN_LOCKOUT_TIME]
            );
            
            return $attempts && $attempts['count'] >= MAX_LOGIN_ATTEMPTS;
        } catch (Exception $e) {
            error_log("Error checking login block: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Zapisz nieudaną próbę logowania
     */
    private function recordFailedLogin($username_or_email) {
        try {
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            
            $this->db->execute(
                "INSERT INTO failed_login_attempts (username, email, ip_address, user_agent, attempted_at) VALUES (?, ?, ?, ?, NOW())",
                [$username_or_email, $username_or_email, $ip_address, $user_agent]
            );
            
            log_activity(null, 'failed_login_attempt', 'Nieudana próba logowania: ' . $username_or_email, $ip_address);
        } catch (Exception $e) {
            error_log("Error recording failed login: " . $e->getMessage());
        }
    }
    
    /**
     * Wyczyść nieudane próby logowania
     */
    private function clearFailedLogins($username_or_email) {
        try {
            $this->db->execute(
                "DELETE FROM failed_login_attempts WHERE username = ? OR email = ?",
                [$username_or_email, $username_or_email]
            );
        } catch (Exception $e) {
            error_log("Error clearing failed logins: " . $e->getMessage());
        }
    }
    
    /**
     * Wyślij email weryfikacyjny
     */
    private function sendVerificationEmail($email, $username, $token) {
        try {
            $emailService = new EmailService();
            return $emailService->sendVerificationEmail($email, $username, $token);
        } catch (Exception $e) {
            error_log("Error sending verification email: " . $e->getMessage());
            return false;
        }
    }
    

    
    /**
     * Wyślij email resetowania hasła
     */
    public function sendPasswordResetEmail($email) {
        try {
            $user = $this->db->selectOne(
                "SELECT id, username FROM users WHERE email = ? AND is_active = 1",
                [$email]
            );
            
            if (!$user) {
                throw new Exception('Nie znaleziono użytkownika z podanym adresem email');
            }
            
            $reset_token = generate_token();
            $expires_at = date('Y-m-d H:i:s', time() + 3600); // 1 godzina
            
            // Zapisz token resetowania
            $this->db->execute(
                "INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at)",
                [$user['id'], $reset_token, $expires_at]
            );
            
            $reset_url = SITE_URL . '/reset_password.php?token=' . $reset_token;
            
            $emailService = new EmailService();
            if ($emailService->sendPasswordResetEmail($email, $user['username'], $reset_url)) {
                log_activity($user['id'], 'password_reset_requested', 'Żądanie resetowania hasła');
                return true;
            }
            
            throw new Exception('Błąd podczas wysyłania emaila');
            
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
    

    
    /**
     * Załaduj dane użytkownika
     */
    private function loadUserData($user) {
        $this->id = $user['id'];
        $this->username = $user['username'];
        $this->email = $user['email'];
        $this->full_name = $user['full_name'];
        $this->is_admin = (bool)$user['is_admin'];
        $this->is_active = $user['is_active'];
        $this->created_at = $user['created_at'];
        $this->last_login = $user['last_login'];
        $this->email_verified = $user['email_verified'];
    }
    
    /**
     * Utwórz sesję użytkownika
     */
    private function createSession($remember_me = false) {
        $session_id = generate_token(64);
        $expires_at = date('Y-m-d H:i:s', time() + ($remember_me ? SESSION_LIFETIME * 4 : SESSION_LIFETIME));
        
        $this->db->execute(
            "INSERT INTO user_sessions (id, user_id, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)",
            [$session_id, $this->id, $_SERVER['REMOTE_ADDR'] ?? 'unknown', $_SERVER['HTTP_USER_AGENT'] ?? 'unknown', $expires_at]
        );
        
        $_SESSION['user_id'] = $this->id;
        $_SESSION['username'] = $this->username;
        $_SESSION['is_admin'] = $this->is_admin;
        $_SESSION['session_id'] = $session_id;
        
        if ($remember_me) {
            setcookie('remember_token', $session_id, time() + SESSION_LIFETIME * 4, '/', '', true, true);
        }
    }
    
    /**
     * Aktualizuj czas ostatniego logowania
     */
    private function updateLastLogin() {
        $this->db->execute(
            "UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?",
            [$this->id]
        );
    }
    
    // Gettery
    public function getId() { return $this->id; }
    public function getUsername() { return $this->username; }
    public function getEmail() { return $this->email; }
    public function getFullName() { return $this->full_name; }
    public function isAdmin() { return $this->is_admin; }
    public function isActive() { return $this->is_active; }
    public function getCreatedAt() { return $this->created_at; }
    public function getLastLogin() { return $this->last_login; }
    public function isEmailVerified() { return $this->email_verified; }
    
    /**
     * Czyści niezweryfikowane konta starsze niż 3 dni
     */
    private function cleanupUnverifiedAccounts() {
        try {
            $cutoffDate = date('Y-m-d H:i:s', strtotime('-3 days'));
            
            // Znajdź niezweryfikowane konta do usunięcia
            $unverifiedUsers = $this->db->select(
                "SELECT id, username, email FROM users WHERE email_verified = 0 AND created_at < ?",
                [$cutoffDate]
            );
            
            foreach ($unverifiedUsers as $user) {
                try {
                    // Usuń powiązane tokeny resetowania hasła
                    $this->db->execute(
                        "DELETE FROM password_reset_tokens WHERE user_id = ?",
                        [$user['id']]
                    );
                    
                    // Usuń próby logowania
                    $this->db->execute(
                        "DELETE FROM failed_login_attempts WHERE user_id = ?",
                        [$user['id']]
                    );
                    
                    // Usuń użytkownika
                    $this->db->execute(
                        "DELETE FROM users WHERE id = ?",
                        [$user['id']]
                    );
                    
                    error_log("Auto-cleanup: Deleted unverified account {$user['username']} ({$user['email']})");
                    
                } catch (Exception $e) {
                    error_log("Error cleaning up unverified account {$user['username']}: " . $e->getMessage());
                }
            }
            
        } catch (Exception $e) {
            error_log("Error in cleanupUnverifiedAccounts: " . $e->getMessage());
        }
    }
}
?>