<?php
/**
 * Weryfikacja emaila użytkownika
 * Ekipowo.pl - System zarządzania subdomenami
 */

require_once 'config/config.php';
require_once 'classes/User.php';

// Sprawdź czy token został podany
if (!isset($_GET['token']) || empty($_GET['token'])) {
    set_flash_message('error', 'Nieprawidłowy link weryfikacyjny.');
    header('Location: /login.php');
    exit;
}

$token = sanitize_input($_GET['token']);
$user = new User();

try {
    if ($user->verifyEmail($token)) {
        set_flash_message('success', 'Twoje konto zostało pomyślnie zweryfikowane! Możesz się teraz zalogować.');
        header('Location: /login.php');
    } else {
        set_flash_message('error', 'Błąd podczas weryfikacji konta. Spróbuj ponownie lub skontaktuj się z administratorem.');
        header('Location: /login.php');
    }
} catch (Exception $e) {
    set_flash_message('error', $e->getMessage());
    header('Location: /login.php');
}
exit;
?>