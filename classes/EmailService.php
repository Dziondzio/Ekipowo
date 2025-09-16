<?php

require_once __DIR__ . '/../config/config.php';

/**
 * EmailService - Ulepszona klasa do wysyłania emaili
 * Obsługuje zarówno PHPMailer (jeśli dostępny) jak i wbudowaną funkcję mail()
 */
class EmailService {
    private $usePHPMailer = false;
    private $mailer = null;
    
    public function __construct() {
        // Sprawdź czy PHPMailer jest dostępny
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            $this->usePHPMailer = true;
            $this->setupPHPMailer();
        } else {
            error_log("PHPMailer not available, using built-in mail() function");
        }
    }
    
    /**
     * Konfiguracja PHPMailer (jeśli dostępny)
     */
    private function setupPHPMailer() {
        try {
            $this->mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
            
            // Konfiguracja serwera SMTP
            $this->mailer->isSMTP();
            $this->mailer->Host       = defined('SMTP_HOST') ? SMTP_HOST : 'localhost';
            $this->mailer->SMTPAuth   = defined('SMTP_AUTH') ? SMTP_AUTH : false;
            $this->mailer->Username   = defined('SMTP_USERNAME') ? SMTP_USERNAME : '';
            $this->mailer->Password   = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '';
            $this->mailer->SMTPSecure = defined('SMTP_SECURE') ? SMTP_SECURE : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $this->mailer->Port       = defined('SMTP_PORT') ? SMTP_PORT : 587;
            
            // Ustawienia nadawcy
            $this->mailer->setFrom(ADMIN_EMAIL, SITE_NAME);
            $this->mailer->addReplyTo(ADMIN_EMAIL, SITE_NAME);
            
            // Ustawienia kodowania
            $this->mailer->CharSet = 'UTF-8';
            $this->mailer->Encoding = 'base64';
            
            // Wyłącz debug w produkcji
            $this->mailer->SMTPDebug = defined('SMTP_DEBUG') ? SMTP_DEBUG : \PHPMailer\PHPMailer\SMTP::DEBUG_OFF;
            
        } catch (Exception $e) {
            error_log("PHPMailer setup error: " . $e->getMessage());
            $this->usePHPMailer = false;
        }
    }
    
    /**
     * Wyślij email weryfikacyjny
     */
    public function sendVerificationEmail($email, $username, $token) {
        try {
            $verification_url = SITE_URL . '/verify_email.php?token=' . $token;
            $subject = 'Weryfikacja konta - ' . SITE_NAME;
            $htmlBody = $this->getVerificationEmailTemplate($username, $verification_url);
            $textBody = $this->getVerificationEmailTextVersion($username, $verification_url);
            
            if ($this->usePHPMailer && $this->mailer) {
                return $this->sendWithPHPMailer($email, $username, $subject, $htmlBody, $textBody);
            } else {
                return $this->sendWithBuiltinMail($email, $subject, $htmlBody);
            }
            
        } catch (Exception $e) {
            error_log("Error sending verification email: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Wyślij email resetowania hasła
     */
    public function sendPasswordResetEmail($email, $username, $reset_url) {
        try {
            $subject = 'Resetowanie hasła - ' . SITE_NAME;
            $htmlBody = $this->getPasswordResetEmailTemplate($username, $reset_url);
            $textBody = $this->getPasswordResetEmailTextVersion($username, $reset_url);
            
            if ($this->usePHPMailer && $this->mailer) {
                return $this->sendWithPHPMailer($email, $username, $subject, $htmlBody, $textBody);
            } else {
                return $this->sendWithBuiltinMail($email, $subject, $htmlBody);
            }
            
        } catch (Exception $e) {
            error_log("Error sending password reset email: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Wyślij email używając PHPMailer
     */
    private function sendWithPHPMailer($email, $username, $subject, $htmlBody, $textBody) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($email, $username);
            
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $htmlBody;
            $this->mailer->AltBody = $textBody;
            
            $result = $this->mailer->send();
            
            if ($result) {
                error_log("Email sent successfully via PHPMailer to: " . $email);
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("PHPMailer send error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Wyślij email używając wbudowanej funkcji mail()
     */
    private function sendWithBuiltinMail($email, $subject, $htmlBody) {
        try {
            $headers = [
                'MIME-Version: 1.0',
                'Content-type: text/html; charset=UTF-8',
                'From: ' . SITE_NAME . ' <' . ADMIN_EMAIL . '>',
                'Reply-To: ' . ADMIN_EMAIL,
                'X-Mailer: PHP/' . phpversion(),
                'X-Priority: 3',
                'X-MSMail-Priority: Normal'
            ];
            
            $result = mail($email, $subject, $htmlBody, implode("\r\n", $headers));
            
            if ($result) {
                error_log("Email sent successfully via built-in mail() to: " . $email);
            } else {
                error_log("Built-in mail() function failed for: " . $email);
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Built-in mail() error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Szablon HTML emaila weryfikacyjnego
     */
    private function getVerificationEmailTemplate($username, $verification_url) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Weryfikacja konta</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
                .container { max-width: 600px; margin: 0 auto; background: white; }
                .header { background: #007bff; color: white; padding: 30px 20px; text-align: center; }
                .header h1 { margin: 0; font-size: 28px; }
                .content { padding: 30px 20px; }
                .content h2 { color: #007bff; margin-top: 0; }
                .button { display: inline-block; padding: 15px 30px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; font-weight: bold; }
                .button:hover { background: #0056b3; }
                .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; background: #f8f9fa; }
                .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }
                @media only screen and (max-width: 600px) {
                    .container { width: 100% !important; }
                    .content { padding: 20px 15px; }
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>' . SITE_NAME . '</h1>
                </div>
                <div class="content">
                    <h2>Witaj ' . htmlspecialchars($username) . '!</h2>
                    <p>Dziękujemy za rejestrację w systemie <strong>' . SITE_NAME . '</strong>. Aby aktywować swoje konto i móc w pełni korzystać z naszych usług, musisz zweryfikować swój adres email.</p>
                    
                    <p style="text-align: center;">
                        <a href="' . $verification_url . '" class="button">Zweryfikuj konto</a>
                    </p>
                    
                    <div class="warning">
                        <p><strong>Jeśli przycisk nie działa:</strong></p>
                        <p>Skopiuj i wklej poniższy link do przeglądarki:</p>
                        <p style="word-break: break-all;"><a href="' . $verification_url . '">' . $verification_url . '</a></p>
                    </div>
                    
                    <p><strong>Ważne informacje:</strong></p>
                    <ul>
                        <li>Link weryfikacyjny jest ważny przez <strong>24 godziny</strong></li>
                        <li>Po tym czasie będziesz musiał ponownie się zarejestrować</li>
                        <li>Jeśli nie rejestrowałeś się w naszym serwisie, zignoruj tę wiadomość</li>
                    </ul>
                </div>
                <div class="footer">
                    <p>&copy; ' . date('Y') . ' ' . SITE_NAME . '. Wszystkie prawa zastrzeżone.</p>
                    <p>Ta wiadomość została wysłana automatycznie. Prosimy nie odpowiadać na ten email.</p>
                </div>
            </div>
        </body>
        </html>';
    }
    
    /**
     * Wersja tekstowa emaila weryfikacyjnego
     */
    private function getVerificationEmailTextVersion($username, $verification_url) {
        return "Witaj " . $username . "!\n\n" .
               "Dziękujemy za rejestrację w systemie " . SITE_NAME . ".\n" .
               "Aby aktywować swoje konto, kliknij w poniższy link:\n\n" .
               $verification_url . "\n\n" .
               "Link weryfikacyjny jest ważny przez 24 godziny.\n" .
               "Jeśli nie rejestrowałeś się w naszym serwisie, zignoruj tę wiadomość.\n\n" .
               "© " . date('Y') . " " . SITE_NAME . ". Wszystkie prawa zastrzeżone.";
    }
    
    /**
     * Szablon HTML emaila resetowania hasła
     */
    private function getPasswordResetEmailTemplate($username, $reset_url) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Resetowanie hasła</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
                .container { max-width: 600px; margin: 0 auto; background: white; }
                .header { background: #dc3545; color: white; padding: 30px 20px; text-align: center; }
                .header h1 { margin: 0; font-size: 28px; }
                .content { padding: 30px 20px; }
                .content h2 { color: #dc3545; margin-top: 0; }
                .button { display: inline-block; padding: 15px 30px; background: #dc3545; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; font-weight: bold; }
                .button:hover { background: #c82333; }
                .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; background: #f8f9fa; }
                .warning { background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin: 20px 0; }
                .security-notice { background: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; border-radius: 5px; margin: 20px 0; }
                @media only screen and (max-width: 600px) {
                    .container { width: 100% !important; }
                    .content { padding: 20px 15px; }
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>' . SITE_NAME . '</h1>
                </div>
                <div class="content">
                    <h2>Resetowanie hasła</h2>
                    <p>Witaj <strong>' . htmlspecialchars($username) . '</strong>!</p>
                    <p>Otrzymaliśmy żądanie resetowania hasła do Twojego konta w systemie <strong>' . SITE_NAME . '</strong>. Aby ustawić nowe hasło, kliknij w poniższy przycisk:</p>
                    
                    <p style="text-align: center;">
                        <a href="' . $reset_url . '" class="button">Resetuj hasło</a>
                    </p>
                    
                    <div class="warning">
                        <p><strong>Jeśli przycisk nie działa:</strong></p>
                        <p>Skopiuj i wklej poniższy link do przeglądarki:</p>
                        <p style="word-break: break-all;"><a href="' . $reset_url . '">' . $reset_url . '</a></p>
                    </div>
                    
                    <div class="security-notice">
                        <p><strong>Informacje bezpieczeństwa:</strong></p>
                        <ul>
                            <li>Link jest ważny przez <strong>1 godzinę</strong></li>
                            <li>Po użyciu link stanie się nieaktywny</li>
                            <li>Jeśli nie żądałeś resetowania hasła, <strong>zignoruj tę wiadomość</strong></li>
                            <li>Twoje obecne hasło pozostanie bez zmian</li>
                        </ul>
                    </div>
                    
                    <p>Jeśli masz problemy z resetowaniem hasła, skontaktuj się z naszym zespołem wsparcia.</p>
                </div>
                <div class="footer">
                    <p>&copy; ' . date('Y') . ' ' . SITE_NAME . '. Wszystkie prawa zastrzeżone.</p>
                    <p>Ta wiadomość została wysłana automatycznie. Prosimy nie odpowiadać na ten email.</p>
                </div>
            </div>
        </body>
        </html>';
    }
    
    /**
     * Wersja tekstowa emaila resetowania hasła
     */
    private function getPasswordResetEmailTextVersion($username, $reset_url) {
        return "Resetowanie hasła\n\n" .
               "Witaj " . $username . "!\n\n" .
               "Otrzymaliśmy żądanie resetowania hasła do Twojego konta.\n" .
               "Aby ustawić nowe hasło, kliknij w poniższy link:\n\n" .
               $reset_url . "\n\n" .
               "WAŻNE INFORMACJE:\n" .
               "- Link jest ważny przez 1 godzinę\n" .
               "- Po użyciu link stanie się nieaktywny\n" .
               "- Jeśli nie żądałeś resetowania hasła, zignoruj tę wiadomość\n\n" .
               "© " . date('Y') . " " . SITE_NAME . ". Wszystkie prawa zastrzeżone.";
    }
}