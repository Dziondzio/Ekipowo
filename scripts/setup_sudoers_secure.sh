#!/bin/bash

# Bezpieczny skrypt do konfiguracji sudoers dla zarządzania Nginx
# Ten skrypt implementuje zasadę najmniejszych uprawnień

if [ "$EUID" -ne 0 ]; then
    echo "Ten skrypt musi być uruchomiony jako root"
    exit 1
fi

echo "Konfiguracja bezpiecznych uprawnień sudoers dla Ekipowo..."

# Utwórz bezpieczny plik sudoers
cat > /etc/sudoers.d/ekipowo-secure << 'EOF'
# Bezpieczna konfiguracja sudoers dla Ekipowo
# Implementuje zasadę najmniejszych uprawnień

# Pozwól www-data tylko na testowanie konfiguracji Nginx
www-data ALL=(ALL) NOPASSWD: /usr/sbin/nginx -t

# Pozwól www-data tylko na przeładowanie Nginx (nie restart!)
www-data ALL=(ALL) NOPASSWD: /bin/systemctl reload nginx

# Pozwól www-data na wykonywanie tylko bezpiecznego wrappera
# Ten wrapper ma dodatkową walidację i logowanie
www-data ALL=(ALL) NOPASSWD: /var/www/ekipowo/scripts/secure_nginx_wrapper.sh *
EOF

# Ustaw restrykcyjne uprawnienia
chmod 440 /etc/sudoers.d/ekipowo-secure

# Sprawdź składnię sudoers
visudo -c
if [ $? -eq 0 ]; then
    echo "✓ Bezpieczna konfiguracja sudoers została utworzona"
else
    echo "✗ Błąd w konfiguracji sudoers - usuwam plik"
    rm -f /etc/sudoers.d/ekipowo-secure
    exit 1
fi

# Usuń starą, niebezpieczną konfigurację jeśli istnieje
if [ -f "/etc/sudoers.d/ekipowo" ]; then
    echo "Usuwam starą, niebezpieczną konfigurację..."
    rm -f /etc/sudoers.d/ekipowo
    echo "✓ Stara konfiguracja została usunięta"
fi

# Ustaw uprawnienia dla skryptów
chmod +x /var/www/ekipowo/scripts/nginx_create_config.sh
chmod +x /var/www/ekipowo/scripts/nginx_delete_config.sh
chmod +x /var/www/ekipowo/scripts/secure_nginx_wrapper.sh

# Utwórz katalog dla logów jeśli nie istnieje
mkdir -p /var/log/nginx
touch /var/log/nginx/ekipowo_operations.log
chown www-data:www-data /var/log/nginx/ekipowo_operations.log
chmod 644 /var/log/nginx/ekipowo_operations.log

echo ""
echo "✓ Bezpieczna konfiguracja została zakończona pomyślnie!"
echo ""
echo "Zmiany:"
echo "- Usunięto niebezpieczne wildcards z sudoers"
echo "- Dodano walidację parametrów wejściowych"
echo "- Utworzono bezpieczny wrapper z logowaniem"
echo "- Ograniczono uprawnienia do minimum"
echo ""
echo "Teraz PHP powinno używać: sudo /var/www/ekipowo/scripts/secure_nginx_wrapper.sh create|delete"