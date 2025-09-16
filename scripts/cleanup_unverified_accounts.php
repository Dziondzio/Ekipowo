<?php
/**
 * Skrypt do automatycznego usuwania niezweryfikowanych kont
 * Usuwa konta które nie zostały zweryfikowane w ciągu 3 dni
 * 
 * Uruchom przez cron co godzinę:
 * 0 * * * * /usr/bin/php /path/to/cleanup_unverified_accounts.php
 */

require_once __DIR__ . '/../config/config.php';

try {
    $db = new DatabaseManager();
    
    // Znajdź niezweryfikowane konta starsze niż 3 dni
    $cutoffDate = date('Y-m-d H:i:s', strtotime('-3 days'));
    
    $unverifiedUsers = $db->select(
        "SELECT id, username, email, created_at FROM users WHERE email_verified = 0 AND created_at < ?",
        [$cutoffDate]
    );
    
    if (empty($unverifiedUsers)) {
        echo "[" . date('Y-m-d H:i:s') . "] Brak niezweryfikowanych kont do usunięcia.\n";
        exit(0);
    }
    
    $deletedCount = 0;
    
    foreach ($unverifiedUsers as $user) {
        try {
            // Usuń powiązane tokeny resetowania hasła
            $db->execute(
                "DELETE FROM password_reset_tokens WHERE user_id = ?",
                [$user['id']]
            );
            
            // Usuń próby logowania
            $db->execute(
                "DELETE FROM failed_login_attempts WHERE user_id = ?",
                [$user['id']]
            );
            
            // Usuń użytkownika
            $result = $db->execute(
                "DELETE FROM users WHERE id = ?",
                [$user['id']]
            );
            
            if ($result) {
                $deletedCount++;
                echo "[" . date('Y-m-d H:i:s') . "] Usunięto niezweryfikowane konto: {$user['username']} ({$user['email']}) - utworzone: {$user['created_at']}\n";
                
                // Loguj aktywność
                error_log("Cleanup: Deleted unverified account {$user['username']} ({$user['email']}) created at {$user['created_at']}");
            }
            
        } catch (Exception $e) {
            echo "[" . date('Y-m-d H:i:s') . "] Błąd podczas usuwania konta {$user['username']}: " . $e->getMessage() . "\n";
            error_log("Cleanup error for user {$user['username']}: " . $e->getMessage());
        }
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Zakończono czyszczenie. Usunięto {$deletedCount} niezweryfikowanych kont.\n";
    
    // Loguj podsumowanie
    if ($deletedCount > 0) {
        error_log("Account cleanup completed: Deleted {$deletedCount} unverified accounts older than 3 days");
    }
    
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Krytyczny błąd podczas czyszczenia kont: " . $e->getMessage() . "\n";
    error_log("Critical error in account cleanup: " . $e->getMessage());
    exit(1);
}

// Opcjonalnie: wyczyść stare tokeny resetowania hasła (starsze niż 24 godziny)
try {
    $oldTokenCutoff = date('Y-m-d H:i:s', strtotime('-24 hours'));
    $deletedTokens = $db->execute(
        "DELETE FROM password_reset_tokens WHERE expires_at < ?",
        [$oldTokenCutoff]
    );
    
    if ($deletedTokens) {
        echo "[" . date('Y-m-d H:i:s') . "] Usunięto {$deletedTokens} wygasłych tokenów resetowania hasła.\n";
    }
    
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Błąd podczas czyszczenia tokenów: " . $e->getMessage() . "\n";
    error_log("Error cleaning up password reset tokens: " . $e->getMessage());
}

echo "[" . date('Y-m-d H:i:s') . "] Skrypt czyszczenia zakończony.\n";
?>