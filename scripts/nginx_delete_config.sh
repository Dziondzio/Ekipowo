#!/bin/bash

# Skrypt do usuwania konfiguracji Nginx dla subdomen
# Użycie: ./nginx_delete_config.sh <subdomain_name>

if [ $# -ne 1 ]; then
    echo "Użycie: $0 <subdomain_name>"
    exit 1
fi

SUBDOMAIN_NAME="$1"

# Walidacja parametru wejściowego dla bezpieczeństwa
# Sprawdź czy nazwa subdomeny zawiera tylko dozwolone znaki
if [[ ! "$SUBDOMAIN_NAME" =~ ^[a-zA-Z0-9-]+$ ]]; then
    echo "Błąd: Nazwa subdomeny może zawierać tylko litery, cyfry i myślniki"
    exit 1
fi
CONFIG_FILE="/etc/nginx/sites-available/${SUBDOMAIN_NAME}.ekipowo.pl"
ENABLED_FILE="/etc/nginx/sites-enabled/${SUBDOMAIN_NAME}.ekipowo.pl"

# Usuń symlink używając sudo
if [ -e "$ENABLED_FILE" ]; then
    sudo rm -f "$ENABLED_FILE"
    if [ $? -ne 0 ]; then
        echo "Błąd: Nie udało się usunąć symlinku"
        exit 1
    fi
fi

# Usuń plik konfiguracji używając sudo
if [ -e "$CONFIG_FILE" ]; then
    sudo rm -f "$CONFIG_FILE"
    if [ $? -ne 0 ]; then
        echo "Błąd: Nie udało się usunąć pliku konfiguracji"
        exit 1
    fi
fi

# Sprawdź składnię używając sudo
sudo nginx -t
if [ $? -ne 0 ]; then
    echo "Ostrzeżenie: Nieprawidłowa składnia konfiguracji Nginx po usunięciu"
fi

# Przeładuj Nginx używając sudo
sudo systemctl reload nginx
if [ $? -ne 0 ]; then
    echo "Błąd: Nie udało się przeładować Nginx"
    exit 1
fi

echo "Sukces: Konfiguracja Nginx dla ${SUBDOMAIN_NAME}.ekipowo.pl została usunięta"
exit 0