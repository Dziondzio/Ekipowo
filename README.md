# System Rezerwacji Subdomen

Nowoczesny system do zarządzania subdomenami z integracją Cloudflare, hostingiem plików i panelem administratora.

## Wymagania

- PHP 7.4 lub nowszy
- MySQL 5.7 lub nowszy
- Serwer web (Apache/Nginx) lub PHP Built-in Server
- Rozszerzenia PHP: PDO, PDO_MySQL, cURL, JSON

## Instalacja

### 1. Przygotowanie środowiska

**Opcja A: XAMPP (Zalecane dla Windows)**
1. Pobierz i zainstaluj XAMPP z https://www.apachefriends.org/
2. Uruchom Apache i MySQL z panelu kontrolnego XAMPP

**Opcja B: WAMP**
1. Pobierz i zainstaluj WAMP z https://www.wampserver.com/
2. Uruchom wszystkie usługi

**Opcja C: Laragon**
1. Pobierz i zainstaluj Laragon z https://laragon.org/
2. Uruchom wszystkie usługi

### 2. Konfiguracja bazy danych

1. Otwórz phpMyAdmin (zwykle http://localhost/phpmyadmin)
2. Utwórz nową bazę danych o nazwie `subdomain_system`
3. Zaimportuj plik `database.sql` do utworzonej bazy danych

### 3. Konfiguracja aplikacji

1. Skopiuj pliki projektu do katalogu www serwera (np. `C:\xampp\htdocs\ekipowo`)
2. Edytuj plik `config/config.php` i ustaw:
   - Dane połączenia z bazą danych
   - Klucze API Cloudflare (opcjonalne)
   - Inne ustawienia systemu

### 4. Uruchomienie

**Opcja A: Przez XAMPP/WAMP/Laragon**
- Otwórz przeglądarkę i przejdź do http://localhost/ekipowo

**Opcja B: PHP Built-in Server**
```bash
cd /ścieżka/do/projektu
php -S localhost:8000
```
- Otwórz przeglądarkę i przejdź do http://localhost:8000

## Funkcjonalności

### Dla użytkowników:
- ✅ Rejestracja i logowanie
- ✅ Tworzenie subdomen (hosting plików lub przekierowanie IP)
- ✅ Upload i zarządzanie plikami HTML/CSS/JS
- ✅ Edytor plików online
- ✅ Zmiana hasła
- ✅ Automatyczna integracja z Cloudflare

### Dla administratorów:
- ✅ Panel administratora
- ✅ Zarządzanie użytkownikami
- ✅ Zarządzanie subdomenami
- ✅ Zarządzanie plikami
- ✅ Przeglądanie logów aktywności
- ✅ Konfiguracja ustawień systemu

## Struktura projektu

```
ekipowo/
├── api/                    # Endpointy API
│   ├── admin/             # API dla panelu administratora
│   └── *.php              # Główne API endpointy
├── classes/               # Klasy PHP
│   ├── CloudflareAPI.php  # Integracja z Cloudflare
│   ├── Subdomain.php      # Zarządzanie subdomenami
│   └── User.php           # Zarządzanie użytkownikami
├── config/                # Konfiguracja
│   ├── config.php         # Główna konfiguracja
│   └── database.php       # Klasa bazy danych
├── uploads/               # Katalog na pliki użytkowników
├── admin.php              # Panel administratora
├── dashboard.php          # Panel użytkownika
├── index.php              # Strona główna
├── login.php              # Logowanie
├── register.php           # Rejestracja
└── database.sql           # Struktura bazy danych
```

## Konfiguracja Cloudflare (Opcjonalna)

1. Zaloguj się do panelu Cloudflare
2. Przejdź do sekcji "My Profile" → "API Tokens"
3. Utwórz nowy token z uprawnieniami:
   - Zone:Zone:Read
   - Zone:DNS:Edit
4. Skopiuj Zone ID swojej domeny
5. Wprowadź dane w panelu administratora lub w pliku config.php

## Bezpieczeństwo

- Wszystkie hasła są hashowane za pomocą password_hash()
- Walidacja danych wejściowych
- Ochrona przed SQL Injection
- Sesje zabezpieczone
- Logowanie aktywności użytkowników

## Wsparcie

W przypadku problemów:
1. Sprawdź logi błędów PHP
2. Upewnij się, że wszystkie wymagania są spełnione
3. Sprawdź uprawnienia do katalogów (uploads/ musi być zapisywalny)

## Licencja

Projekt stworzony dla celów edukacyjnych i komercyjnych.