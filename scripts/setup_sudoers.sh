#!/bin/bash

# Skrypt do konfiguracji sudoers dla zarządzania Nginx
# Ten skrypt musi być uruchomiony jako root

if [ "$EUID" -ne 0 ]; then
    echo "Ten skrypt musi być uruchomiony jako root"
    exit 1
fi

# Utwórz plik sudoers dla ekipowo
cat > /etc/sudoers.d/ekipowo << 'EOF'
# Pozwól użytkownikowi www-data na zarządzanie Nginx bez hasła
# TYLKO konkretne, bezpieczne operacje
www-data ALL=(ALL) NOPASSWD: /usr/sbin/nginx -t
www-data ALL=(ALL) NOPASSWD: /bin/systemctl reload nginx

# Pozwól tylko na wykonywanie konkretnych skryptów
# Te skrypty są kontrolowane i bezpieczne
www-data ALL=(ALL) NOPASSWD: /var/www/ekipowo/scripts/nginx_create_config.sh *
www-data ALL=(ALL) NOPASSWD: /var/www/ekipowo/scripts/nginx_delete_config.sh *
EOF

# Ustaw odpowiednie uprawnienia
chmod 440 /etc/sudoers.d/ekipowo

# Sprawdź składnię sudoers
visudo -c
if [ $? -eq 0 ]; then
    echo "Konfiguracja sudoers została pomyślnie utworzona"
else
    echo "Błąd w konfiguracji sudoers - usuwam plik"
    rm -f /etc/sudoers.d/ekipowo
    exit 1
fi

# Ustaw uprawnienia wykonywania dla skryptów
chmod +x /var/www/ekipowo/scripts/nginx_create_config.sh
chmod +x /var/www/ekipowo/scripts/nginx_delete_config.sh

echo "Konfiguracja zakończona pomyślnie!"
echo "Skrypty Nginx mogą być teraz wykonywane przez www-data bez hasła."