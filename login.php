<?php
require_once 'config/config.php';

$error_message = '';
$success_message = '';

// Sprawdź czy użytkownik jest już zalogowany
if (is_logged_in()) {
    redirect('/dashboard.php');
}

// Obsługa formularza logowania
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Nieprawidłowy token bezpieczeństwa.';
    } else {
        $username_or_email = sanitize_input($_POST['username_or_email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember_me = isset($_POST['remember_me']);
        
        if (empty($username_or_email) || empty($password)) {
            $error_message = 'Wszystkie pola są wymagane.';
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
                    $result = $user->login($username_or_email, $password, $remember_me);
                
                    if ($result['success']) {
                        if ($result['is_admin']) {
                            redirect('/admin.php');
                        } else {
                            redirect('/dashboard.php');
                        }
                    }
                } catch (Exception $e) {
                    $error_message = $e->getMessage();
                }
            }
        }
    }
}

// Pobierz flash message
$flash = get_flash_message();
if ($flash) {
    if ($flash['type'] === 'success') {
        $success_message = $flash['message'];
    } else {
        $error_message = $flash['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logowanie - <?= SITE_NAME ?></title>
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
            <p class="text-indigo-100">Zaloguj się do swojego konta</p>
        </div>

        <!-- Formularz logowania -->
        <div class="glass rounded-2xl p-8 shadow-xl">
            <?php if ($error_message): ?>
                <div class="bg-red-500/20 border border-red-500/50 text-red-100 px-4 py-3 rounded-lg mb-6 flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="bg-green-500/20 border border-green-500/50 text-green-100 px-4 py-3 rounded-lg mb-6 flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                
                <div>
                    <label for="username_or_email" class="block text-sm font-medium text-white mb-2">
                        <i class="fas fa-user mr-2"></i>Nazwa użytkownika lub email
                    </label>
                    <input 
                        type="text" 
                        id="username_or_email" 
                        name="username_or_email" 
                        required 
                        class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-white/60 input-focus focus:border-white/40 focus:outline-none transition-all duration-200"
                        placeholder="Wprowadź nazwę użytkownika lub email"
                        value="<?= htmlspecialchars($_POST['username_or_email'] ?? '') ?>"
                    >
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-white mb-2">
                        <i class="fas fa-lock mr-2"></i>Hasło
                    </label>
                    <div class="relative">
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            required 
                            class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-white/60 input-focus focus:border-white/40 focus:outline-none transition-all duration-200 pr-12"
                            placeholder="Wprowadź hasło"
                        >
                        <button 
                            type="button" 
                            onclick="togglePassword()" 
                            class="absolute right-3 top-1/2 transform -translate-y-1/2 text-white/60 hover:text-white transition-colors"
                        >
                            <i id="password-icon" class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <label class="flex items-center text-white">
                        <input 
                            type="checkbox" 
                            name="remember_me" 
                            class="w-4 h-4 text-indigo-600 bg-white/10 border-white/20 rounded focus:ring-indigo-500 focus:ring-2"
                        >
                        <span class="ml-2 text-sm">Zapamiętaj mnie</span>
                    </label>
                    <a href="/reset_password.php" class="text-sm text-white/80 hover:text-white transition-colors">
                        Zapomniałeś hasła?
                    </a>
                </div>

                <?php if (TurnstileVerifier::isConfigured()): ?>
                <div class="flex justify-center">
                    <div class="cf-turnstile" data-sitekey="<?= TurnstileVerifier::getSiteKey() ?>" data-theme="dark"></div>
                </div>
                <?php endif; ?>

                <button 
                    type="submit" 
                    class="w-full bg-white text-indigo-600 py-3 px-4 rounded-lg font-semibold hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-indigo-600 transition-all duration-200 flex items-center justify-center"
                >
                    <i class="fas fa-sign-in-alt mr-2"></i>
                    Zaloguj się
                </button>
            </form>

            <div class="mt-6 text-center">
                <p class="text-white/80">
                    Nie masz konta? 
                    <a href="/register.php" class="text-white font-semibold hover:underline">
                        Zarejestruj się
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
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const passwordIcon = document.getElementById('password-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordIcon.className = 'fas fa-eye-slash';
            } else {
                passwordInput.type = 'password';
                passwordIcon.className = 'fas fa-eye';
            }
        }

        // Auto-focus na pierwszy input
        document.getElementById('username_or_email').focus();
    </script>
</body>
</html>