<?php
require_once 'config/config.php';

// Sprawdź czy użytkownik jest zalogowany
if (is_logged_in()) {
    $user = new User();
    $user->logout();
    
    set_flash_message('success', 'Zostałeś pomyślnie wylogowany.');
}

redirect('/login.php');
?>