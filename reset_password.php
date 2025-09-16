<?php
/**
 * Resetowanie hasła użytkownika
 * Ekipowo.pl - System zarządzania subdomenami
 */

require_once 'config/config.php';
require_once 'classes/User.php';

$token = isset($_GET['token']) ? sanitize_input($_GET['token']) : '';
$error = '';
$success = '';

// Sprawdź czy token jest prawidłowy
if (!empty($token)) {
    $db = new DatabaseManager();
    $reset_data = $db->selectOne(
        "SELECT prt.*, u.username FROM password_reset_tokens prt 
         JOIN users u ON prt.user_id = u.id 
         WHERE prt.token = ? AND prt.expires_at > NOW() AND prt.used_at IS NULL",
        [$token]
    );
    
    if (!$reset_data) {
        $error = 'Link resetowania hasła jest nieprawidłowy lub wygasł.';
        $token = '';
    }
}

// Obsługa formularza resetowania hasła
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($token)) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    try {
        // Sprawdź token CSRF
        if (!verify_csrf_token($csrf_token)) {
            throw new Exception('Nieprawidłowy token bezpieczeństwa.');
        }
        
        // Walidacja hasła
        if (strlen($new_password) < PASSWORD_MIN_LENGTH) {
            throw new Exception('Hasło musi mieć co najmniej ' . PASSWORD_MIN_LENGTH . ' znaków.');
        }
        
        if ($new_password !== $confirm_password) {
            throw new Exception('Hasła nie są identyczne.');
        }
        
        // Sprawdź ponownie token (może wygasnąć podczas wypełniania formularza)
        $reset_data = $db->selectOne(
            "SELECT user_id FROM password_reset_tokens 
             WHERE token = ? AND expires_at > NOW() AND used_at IS NULL",
            [$token]
        );
        
        if (!$reset_data) {
            throw new Exception('Link resetowania hasła wygasł.');
        }
        
        // Zaktualizuj hasło
        $password_hash = hash_password($new_password);
        $db->execute(
            "UPDATE users SET password_hash = ? WHERE id = ?",
            [$password_hash, $reset_data['user_id']]
        );
        
        // Oznacz token jako użyty
        $db->execute(
            "UPDATE password_reset_tokens SET used_at = NOW() WHERE token = ?",
            [$token]
        );
        
        log_activity($reset_data['user_id'], 'password_reset_completed', 'Hasło zostało zresetowane');
        
        $success = 'Hasło zostało pomyślnie zmienione. Możesz się teraz zalogować.';
        $token = ''; // Wyczyść token
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Obsługa żądania wysłania linku resetowania
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($token) && isset($_POST['email'])) {
    $email = sanitize_input($_POST['email']);
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    try {
        if (!verify_csrf_token($csrf_token)) {
            throw new Exception('Nieprawidłowy token bezpieczeństwa.');
        }
        
        if (!validate_email($email)) {
            throw new Exception('Nieprawidłowy adres email.');
        }
        
        $user = new User();
        $user->sendPasswordResetEmail($email);
        
        $success = 'Jeśli podany adres email istnieje w naszej bazie, wysłaliśmy na niego link do resetowania hasła.';
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$page_title = 'Resetowanie hasła';
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .btn-primary {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border: none;
            border-radius: 25px;
        }
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="fas fa-key fa-3x text-primary mb-3"></i>
                            <h2 class="card-title"><?php echo $page_title; ?></h2>
                        </div>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo htmlspecialchars($success); ?>
                            </div>
                            <?php if (empty($token)): ?>
                                <div class="text-center">
                                    <a href="/login.php" class="btn btn-primary">
                                        <i class="fas fa-sign-in-alt me-2"></i>Przejdź do logowania
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php if (!$success && !empty($token)): ?>
                            <!-- Formularz ustawiania nowego hasła -->
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                                
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">
                                        <i class="fas fa-lock me-2"></i>Nowe hasło
                                    </label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" 
                                           required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                                    <div class="form-text">
                                        Hasło musi mieć co najmniej <?php echo PASSWORD_MIN_LENGTH; ?> znaków.
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="confirm_password" class="form-label">
                                        <i class="fas fa-lock me-2"></i>Potwierdź hasło
                                    </label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                           required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-save me-2"></i>Ustaw nowe hasło
                                </button>
                            </form>
                        <?php elseif (!$success && empty($token)): ?>
                            <!-- Formularz żądania resetowania hasła -->
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                                
                                <div class="mb-4">
                                    <label for="email" class="form-label">
                                        <i class="fas fa-envelope me-2"></i>Adres email
                                    </label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           placeholder="Wprowadź swój adres email" required>
                                    <div class="form-text">
                                        Wyślemy Ci link do resetowania hasła na podany adres email.
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-paper-plane me-2"></i>Wyślij link resetowania
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <div class="text-center mt-4">
                            <a href="/login.php" class="text-decoration-none">
                                <i class="fas fa-arrow-left me-2"></i>Powrót do logowania
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Walidacja potwierdzenia hasła
        document.getElementById('confirm_password')?.addEventListener('input', function() {
            const password = document.getElementById('new_password').value;
            const confirm = this.value;
            
            if (password !== confirm) {
                this.setCustomValidity('Hasła nie są identyczne');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>