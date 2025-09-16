#!/bin/bash

# Bezpieczny wrapper do zarządzania konfiguracją Nginx
# Ten skrypt może być uruchomiony z uprawnieniami SUID jako alternatywa dla sudoers

# Sprawdź czy skrypt jest uruchomiony przez odpowiedniego użytkownika
if [ "$(whoami)" != "www-data" ] && [ "$(whoami)" != "root" ]; then
    echo "Błąd: Skrypt może być uruchomiony tylko przez www-data lub root"
    exit 1
fi

# Funkcja do logowania operacji
log_operation() {
    echo "$(date): $1" >> /var/log/nginx/ekipowo_operations.log
}

# Sprawdź pierwszy argument (operacja)
if [ $# -lt 1 ]; then
    echo "Użycie: $0 <create|delete> [argumenty...]"
    exit 1
fi

OPERATION="$1"
shift

case "$OPERATION" in
    "create")
        if [ $# -ne 2 ]; then
            echo "Użycie: $0 create <subdomain_name> <document_root>"
            exit 1
        fi
        
        SUBDOMAIN_NAME="$1"
        DOCUMENT_ROOT="$2"
        
        # Walidacja parametrów
        if [[ ! "$SUBDOMAIN_NAME" =~ ^[a-zA-Z0-9-]+$ ]]; then
            echo "Błąd: Nazwa subdomeny może zawierać tylko litery, cyfry i myślniki"
            exit 1
        fi
        
        if [[ ! "$DOCUMENT_ROOT" =~ ^/var/www/ ]]; then
            echo "Błąd: Document root musi być w katalogu /var/www/"
            exit 1
        fi
        
        if [[ "$DOCUMENT_ROOT" =~ [\;\&\|\`\$] ]]; then
            echo "Błąd: Document root zawiera niedozwolone znaki"
            exit 1
        fi
        
        log_operation "Creating Nginx config for $SUBDOMAIN_NAME"
        /var/www/ekipowo/scripts/nginx_create_config.sh "$SUBDOMAIN_NAME" "$DOCUMENT_ROOT"
        ;;
        
    "delete")
        if [ $# -ne 1 ]; then
            echo "Użycie: $0 delete <subdomain_name>"
            exit 1
        fi
        
        SUBDOMAIN_NAME="$1"
        
        # Walidacja parametru
        if [[ ! "$SUBDOMAIN_NAME" =~ ^[a-zA-Z0-9-]+$ ]]; then
            echo "Błąd: Nazwa subdomeny może zawierać tylko litery, cyfry i myślniki"
            exit 1
        fi
        
        log_operation "Deleting Nginx config for $SUBDOMAIN_NAME"
        /var/www/ekipowo/scripts/nginx_delete_config.sh "$SUBDOMAIN_NAME"
        ;;
        
    *)
        echo "Błąd: Nieznana operacja '$OPERATION'. Dostępne: create, delete"
        exit 1
        ;;
esac

exit $?