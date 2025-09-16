#!/bin/bash

# Skrypt do tworzenia konfiguracji Nginx dla subdomen
# Użycie: ./nginx_create_config.sh <subdomain_name> <document_root>

if [ $# -ne 2 ]; then
    echo "Użycie: $0 <subdomain_name> <document_root>"
    exit 1
fi

SUBDOMAIN_NAME="$1"
DOCUMENT_ROOT="$2"

# Walidacja parametrów wejściowych dla bezpieczeństwa
# Sprawdź czy nazwa subdomeny zawiera tylko dozwolone znaki
if [[ ! "$SUBDOMAIN_NAME" =~ ^[a-zA-Z0-9-]+$ ]]; then
    echo "Błąd: Nazwa subdomeny może zawierać tylko litery, cyfry i myślniki"
    exit 1
fi

# Sprawdź czy document_root jest bezpieczną ścieżką
if [[ ! "$DOCUMENT_ROOT" =~ ^/var/www/ ]]; then
    echo "Błąd: Document root musi być w katalogu /var/www/"
    exit 1
fi

# Sprawdź czy document_root nie zawiera niebezpiecznych znaków
if [[ "$DOCUMENT_ROOT" =~ [\;\&\|\`\$] ]]; then
    echo "Błąd: Document root zawiera niedozwolone znaki"
    exit 1
fi
CONFIG_FILE="/etc/nginx/sites-available/${SUBDOMAIN_NAME}.ekipowo.pl"
ENABLED_FILE="/etc/nginx/sites-enabled/${SUBDOMAIN_NAME}.ekipowo.pl"

# Sprawdź czy katalogi istnieją
if [ ! -d "/etc/nginx/sites-available" ]; then
    echo "Błąd: Katalog /etc/nginx/sites-available nie istnieje"
    exit 1
fi

# Utwórz plik konfiguracji w tymczasowym katalogu
TEMP_CONFIG="/tmp/${SUBDOMAIN_NAME}.ekipowo.pl.conf"
cat > "$TEMP_CONFIG" << EOF
server {
    listen 443 ssl;
    listen [::]:443 ssl;
    http2 on;  # Nowoczesna składnia dla HTTP/2
    server_name ${SUBDOMAIN_NAME}.ekipowo.pl;

    root ${DOCUMENT_ROOT};
    index index.php index.html index.htm;

    # Ustawienia dla rzeczywistych adresów IP z Cloudflare
    set_real_ip_from 173.245.48.0/20;
    set_real_ip_from 103.21.244.0/22;
    set_real_ip_from 103.22.200.0/22;
    set_real_ip_from 103.31.4.0/22;
    set_real_ip_from 141.101.64.0/18;
    set_real_ip_from 104.16.0.0/12;
    set_real_ip_from 108.162.192.0/18;
    set_real_ip_from 131.0.72.0/22;
    set_real_ip_from 162.158.0.0/15;
    set_real_ip_from 172.64.0.0/13;
    set_real_ip_from 188.114.96.0/20;
    set_real_ip_from 190.93.240.0/20;
    set_real_ip_from 197.234.240.0/22;
    set_real_ip_from 2400:cb00::/32;
    set_real_ip_from 2606:4700::/32;
    set_real_ip_from 2803:f800::/32;
    set_real_ip_from 2405:b500::/32;
    set_real_ip_from 2607:f8b0::/32;

    real_ip_header CF-Connecting-IP;  # Użyj nagłówka Cloudflare

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    client_max_body_size 1G;

    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;  # Zawiera fastcgi_pass
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }

    location ~* \.(jpg|jpeg|png|gif|ico|css|js)\$ {
        expires 30d;
        add_header Cache-Control "public, no-transform";
    }
}

server {
    if (\$host = ${SUBDOMAIN_NAME}.ekipowo.pl) {
        return 301 https://\$host\$request_uri;
    } # managed by Certbot

    listen 80;
    listen [::]:80;
    server_name ${SUBDOMAIN_NAME}.ekipowo.pl;
    return 301 https://\$host\$request_uri;
}
EOF

if [ $? -ne 0 ]; then
    echo "Błąd: Nie udało się utworzyć tymczasowego pliku konfiguracji"
    exit 1
fi

# Przenieś plik do katalogu Nginx używając sudo
sudo mv "$TEMP_CONFIG" "$CONFIG_FILE"
if [ $? -ne 0 ]; then
    rm -f "$TEMP_CONFIG"
    echo "Błąd: Nie udało się przenieść pliku konfiguracji"
    exit 1
fi

# Ustaw odpowiednie uprawnienia używając sudo
sudo chmod 644 "$CONFIG_FILE"

# Utwórz symlink w sites-enabled używając sudo
if [ ! -e "$ENABLED_FILE" ]; then
    sudo ln -s "$CONFIG_FILE" "$ENABLED_FILE"
    if [ $? -ne 0 ]; then
        echo "Błąd: Nie udało się utworzyć symlinku"
        exit 1
    fi
fi

# Sprawdź składnię używając sudo
sudo nginx -t
if [ $? -ne 0 ]; then
    echo "Błąd: Nieprawidłowa składnia konfiguracji Nginx"
    # Usuń pliki konfiguracji używając sudo
    sudo rm -f "$CONFIG_FILE"
    sudo rm -f "$ENABLED_FILE"
    exit 1
fi

# Przeładuj Nginx używając sudo
sudo systemctl reload nginx
if [ $? -ne 0 ]; then
    echo "Błąd: Nie udało się przeładować Nginx"
    # Usuń pliki konfiguracji używając sudo
    sudo rm -f "$CONFIG_FILE"
    sudo rm -f "$ENABLED_FILE"
    exit 1
fi

echo "Sukces: Konfiguracja Nginx dla ${SUBDOMAIN_NAME}.ekipowo.pl została utworzona"
exit 0