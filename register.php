<?php
require_once 'config/config.php';

$error_message = '';
$success_message = '';

// Sprawdź czy użytkownik jest już zalogowany
if (is_logged_in()) {
    redirect('/dashboard.php');
}

// Sprawdź czy rejestracja jest włączona
$db = new DatabaseManager();
$registration_setting = $db->selectOne(
    "SELECT setting_value FROM system_settings WHERE setting_key = 'registration_enabled'"
);

if (!$registration_setting || $registration_setting['setting_value'] !== 'true') {
    set_flash_message('error', 'Rejestracja nowych użytkowników jest obecnie wyłączona.');
    redirect('/login.php');
}

// Obsługa formularza rejestracji
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Nieprawidłowy token bezpieczeństwa.';
    } else {
        $username = sanitize_input($_POST['username'] ?? '');
        $email = sanitize_input($_POST['email'] ?? '');
        $full_name = sanitize_input($_POST['full_name'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';
        $terms_accepted = isset($_POST['terms_accepted']);
        
        // Walidacja
        if (empty($username) || empty($email) || empty($password) || empty($password_confirm)) {
            $error_message = 'Wszystkie wymagane pola muszą być wypełnione.';
        } elseif ($password !== $password_confirm) {
            $error_message = 'Hasła nie są identyczne.';
        } elseif (!$terms_accepted) {
            $error_message = 'Musisz zaakceptować regulamin serwisu.';
        } else {
            // Weryfikacja Turnstile CAPTCHA
            if (TurnstileVerifier::isConfigured()) {
                $turnstile_token = $_POST['cf-turnstile-response'] ?? '';
                $turnstile = new TurnstileVerifier();
                $captcha_result = $turnstile->verify($turnstile_token, $_SERVER['REMOTE_ADDR'] ?? null);
                
                if (!$captcha_result['success']) {
                    $error_message = 'Weryfikacja CAPTCHA nie powiodła się. Spróbuj ponownie.';
                }
            }
            
            if (empty($error_message)) {
                try {
                    $user = new User();
                    $result = $user->register($username, $email, $password, $full_name);
                    
                    if ($result['success']) {
                        if ($result['email_sent']) {
                            set_flash_message('success', 'Konto zostało utworzone! Sprawdź swoją skrzynkę email w celu weryfikacji konta.');
                        } else {
                            set_flash_message('warning', 'Konto zostało utworzone, ale wystąpił problem z wysłaniem emaila weryfikacyjnego. Skontaktuj się z administratorem.');
                        }
                        redirect('/login.php');
                    }
                } catch (Exception $e) {
                    $error_message = $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rejestracja - <?= SITE_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <?php if (TurnstileVerifier::isConfigured()): ?>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <?php endif; ?>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .glass {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .input-focus:focus {
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        .password-strength {
            height: 4px;
            border-radius: 2px;
            transition: all 0.3s ease;
        }
    </style>
</head>
<body class="min-h-screen gradient-bg flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <!-- Logo i nagłówek -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-white rounded-full mb-4 shadow-lg">
                <i class="fas fa-rocket text-2xl text-indigo-600"></i>
            </div>
            <h1 class="text-3xl font-bold text-white mb-2"><?= SITE_NAME ?></h1>
            <p class="text-indigo-100">Utwórz nowe konto</p>
        </div>

        <!-- Formularz rejestracji -->
        <div class="glass rounded-2xl p-8 shadow-xl">
            <?php if ($error_message): ?>
                <div class="bg-red-500/20 border border-red-500/50 text-red-100 px-4 py-3 rounded-lg mb-6 flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6" id="registerForm">
                <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                
                <div>
                    <label for="username" class="block text-sm font-medium text-white mb-2">
                        <i class="fas fa-user mr-2"></i>Nazwa użytkownika *
                    </label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        required 
                        pattern="[a-zA-Z0-9_]{3,30}"
                        class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-white/60 input-focus focus:border-white/40 focus:outline-none transition-all duration-200"
                        placeholder="3-30 znaków: litery, cyfry, podkreślenia"
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                    >
                    <p class="text-xs text-white/60 mt-1">Tylko litery, cyfry i podkreślenia (3-30 znaków)</p>
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-white mb-2">
                        <i class="fas fa-envelope mr-2"></i>Adres email *
                    </label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        required 
                        class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-white/60 input-focus focus:border-white/40 focus:outline-none transition-all duration-200"
                        placeholder="twoj@email.com"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    >
                </div>

                <div>
                    <label for="full_name" class="block text-sm font-medium text-white mb-2">
                        <i class="fas fa-id-card mr-2"></i>Imię i nazwisko
                    </label>
                    <input 
                        type="text" 
                        id="full_name" 
                        name="full_name" 
                        class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-white/60 input-focus focus:border-white/40 focus:outline-none transition-all duration-200"
                        placeholder="Opcjonalne"
                        value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                    >
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-white mb-2">
                        <i class="fas fa-lock mr-2"></i>Hasło *
                    </label>
                    <div class="relative">
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            required 
                            minlength="<?= PASSWORD_MIN_LENGTH ?>"
                            class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-white/60 input-focus focus:border-white/40 focus:outline-none transition-all duration-200 pr-12"
                            placeholder="Minimum <?= PASSWORD_MIN_LENGTH ?> znaków"
                            onkeyup="checkPasswordStrength()"
                        >
                        <button 
                            type="button" 
                            onclick="togglePassword('password', 'password-icon')" 
                            class="absolute right-3 top-1/2 transform -translate-y-1/2 text-white/60 hover:text-white transition-colors"
                        >
                            <i id="password-icon" class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="mt-2">
                        <div id="password-strength" class="password-strength bg-gray-300"></div>
                        <p id="password-strength-text" class="text-xs text-white/60 mt-1">Siła hasła</p>
                    </div>
                </div>

                <div>
                    <label for="password_confirm" class="block text-sm font-medium text-white mb-2">
                        <i class="fas fa-lock mr-2"></i>Potwierdź hasło *
                    </label>
                    <div class="relative">
                        <input 
                            type="password" 
                            id="password_confirm" 
                            name="password_confirm" 
                            required 
                            class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-white/60 input-focus focus:border-white/40 focus:outline-none transition-all duration-200 pr-12"
                            placeholder="Powtórz hasło"
                            onkeyup="checkPasswordMatch()"
                        >
                        <button 
                            type="button" 
                            onclick="togglePassword('password_confirm', 'password-confirm-icon')" 
                            class="absolute right-3 top-1/2 transform -translate-y-1/2 text-white/60 hover:text-white transition-colors"
                        >
                            <i id="password-confirm-icon" class="fas fa-eye"></i>
                        </button>
                    </div>
                    <p id="password-match-text" class="text-xs mt-1 hidden"></p>
                </div>

                <div>
                    <label class="flex items-start text-white">
                        <input 
                            type="checkbox" 
                            name="terms_accepted" 
                            required
                            class="w-4 h-4 text-indigo-600 bg-white/10 border-white/20 rounded focus:ring-indigo-500 focus:ring-2 mt-1 mr-3"
                        >
                        <span class="text-sm">
                            Akceptuję <a href="/terms.php" target="_blank" class="text-white font-semibold hover:underline">regulamin serwisu</a> 
                            i <a href="/privacy.php" target="_blank" class="text-white font-semibold hover:underline">politykę prywatności</a> *
                        </span>
                    </label>
                </div>

                <?php if (TurnstileVerifier::isConfigured()): ?>
                <div class="flex justify-center">
                    <div class="cf-turnstile" data-sitekey="<?= TurnstileVerifier::getSiteKey() ?>" data-theme="dark"></div>
                </div>
                <?php endif; ?>

                <button 
                    type="submit" 
                    id="submitBtn"
                    class="w-full bg-white text-indigo-600 py-3 px-4 rounded-lg font-semibold hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-indigo-600 transition-all duration-200 flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <i class="fas fa-user-plus mr-2"></i>
                    Utwórz konto
                </button>
            </form>

            <div class="mt-6 text-center">
                <p class="text-white/80">
                    Masz już konto? 
                    <a href="/login.php" class="text-white font-semibold hover:underline">
                        Zaloguj się
                    </a>
                </p>
            </div>
        </div>

        <!-- Powrót do strony głównej -->
        <div class="text-center mt-6">
            <a href="/" class="text-white/80 hover:text-white transition-colors flex items-center justify-center">
                <i class="fas fa-arrow-left mr-2"></i>
                Powrót do strony głównej
            </a>
        </div>
    </div>

    <script>
        function togglePassword(inputId, iconId) {
            const passwordInput = document.getElementById(inputId);
            const passwordIcon = document.getElementById(iconId);
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordIcon.className = 'fas fa-eye-slash';
            } else {
                passwordInput.type = 'password';
                passwordIcon.className = 'fas fa-eye';
            }
        }

        function checkPasswordStrength() {
            const password = document.getElementById('password').value;
            const strengthBar = document.getElementById('password-strength');
            const strengthText = document.getElementById('password-strength-text');
            
            let strength = 0;
            let text = '';
            let color = '';
            
            if (password.length >= <?= PASSWORD_MIN_LENGTH ?>) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            
            switch (strength) {
                case 0:
                case 1:
                    text = 'Bardzo słabe';
                    color = 'bg-red-500';
                    break;
                case 2:
                    text = 'Słabe';
                    color = 'bg-orange-500';
                    break;
                case 3:
                    text = 'Średnie';
                    color = 'bg-yellow-500';
                    break;
                case 4:
                    text = 'Silne';
                    color = 'bg-green-500';
                    break;
                case 5:
                    text = 'Bardzo silne';
                    color = 'bg-green-600';
                    break;
            }
            
            strengthBar.className = `password-strength ${color}`;
            strengthBar.style.width = (strength * 20) + '%';
            strengthText.textContent = text;
        }

        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const passwordConfirm = document.getElementById('password_confirm').value;
            const matchText = document.getElementById('password-match-text');
            
            if (passwordConfirm.length > 0) {
                if (password === passwordConfirm) {
                    matchText.textContent = 'Hasła są identyczne';
                    matchText.className = 'text-xs mt-1 text-green-300';
                    matchText.classList.remove('hidden');
                } else {
                    matchText.textContent = 'Hasła nie są identyczne';
                    matchText.className = 'text-xs mt-1 text-red-300';
                    matchText.classList.remove('hidden');
                }
            } else {
                matchText.classList.add('hidden');
            }
        }

        // Auto-focus na pierwszy input
        document.getElementById('username').focus();
    </script>
</body>
</html>