#!/usr/bin/env bash
# =========================================================
# Despliega AutoPlaza en la red local con Apache.
#   sudo bash deploy/desplegar.sh
# Se puede repetir para actualizar: copia el código nuevo, pero NO pisa
# la base de datos (var/data.db), las fotos subidas (public/uploads) ni .env.local.
# =========================================================
set -euo pipefail

[[ $EUID -eq 0 ]] || { echo "Ejecútalo con sudo: sudo bash $0"; exit 1; }

ORIGEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DESTINO=/var/www/sistema
USUARIO="${SUDO_USER:-$(stat -c %U "$ORIGEN")}"
CASA="$(getent passwd "$USUARIO" | cut -d: -f6)"
IP="$(hostname -I | awk '{print $1}')"
paso() { printf '\n\033[1;31m==>\033[0m \033[1m%s\033[0m\n' "$*"; }

primera=si
[[ -f "$DESTINO/var/data.db" ]] && primera=no

paso "Copiando el proyecto a $DESTINO (primera vez: $primera)"
mkdir -p "$DESTINO"
excluir=(
    --exclude=/.git --exclude=/.env.local --exclude=/vendor --exclude=/.phpunit.cache
    --exclude=/var/cache --exclude=/var/log --exclude=/var/share --exclude=/var/test.db
    --exclude='/var/*.bak*' --exclude='/var/*.respaldo' --exclude=/public/assets
)
# Después de la primera vez, los datos del servidor mandan.
[[ $primera == no ]] && excluir+=(--exclude=/var/data.db --exclude=/public/uploads)
rsync -a --delete "${excluir[@]}" "$ORIGEN/" "$DESTINO/"

if [[ ! -f "$DESTINO/.env.local" ]]; then
    paso "Creando .env.local (producción)"
    cat > "$DESTINO/.env.local" <<ENV
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=$(php -r 'echo bin2hex(random_bytes(16));')
DEFAULT_URI=http://$IP
ENV
    chmod 640 "$DESTINO/.env.local"
fi

cd "$DESTINO"
export COMPOSER_ALLOW_SUPERUSER=1 APP_ENV=prod

paso "Dependencias (sin las de desarrollo)"
composer install --no-dev --optimize-autoloader --no-interaction --no-progress

paso "CSS (Tailwind) y assets"
php bin/console tailwind:build --minify
rm -rf public/assets
php bin/console asset-map:compile

paso "Base de datos"
php bin/console doctrine:migrations:migrate -n --allow-no-migration

paso "Caché"
php bin/console cache:clear
php bin/console cache:warmup

paso "Permisos"
mkdir -p public/uploads/autos
chown -R root:root "$DESTINO"
chown -R www-data:www-data var public/uploads
chown root:www-data .env.local

paso "Apache"
install -m 644 deploy/apache-sistema.conf /etc/apache2/sites-available/sistema.conf
a2enmod -q headers >/dev/null
a2dissite -q 000-default >/dev/null 2>&1 || true
a2ensite -q sistema >/dev/null
apache2ctl configtest
systemctl enable --now apache2
systemctl reload apache2

# LibreTranslate arranca solo con el equipo y, además, junto con la web: Apache lo "quiere"
# (Wants=), así que al arrancar o reiniciar Apache también se pone en marcha si estaba parado.
# Si se cae, systemd lo vuelve a arrancar. Si no responde, el panel muestra las consultas
# sin traducir y un aviso.
if [[ -x "$CASA/.local/share/libretranslate/venv/bin/libretranslate" ]]; then
    paso "LibreTranslate como servicio del sistema (arranca con el equipo y con Apache)"
    sed -e "s|__USUARIO__|$USUARIO|" -e "s|__HOME__|$CASA|" deploy/libretranslate.service > /etc/systemd/system/libretranslate.service
    install -d /etc/systemd/system/apache2.service.d
    install -m 644 deploy/apache2-libretranslate.conf /etc/systemd/system/apache2.service.d/libretranslate.conf
    systemctl daemon-reload
    systemctl enable --now libretranslate
    systemctl restart libretranslate   # por si cambió la configuración del servicio
else
    paso "AVISO: LibreTranslate no está instalado"
    echo "No se encontró $CASA/.local/share/libretranslate/venv/bin/libretranslate."
    echo "La web funciona, pero el panel mostrará las consultas sin traducir (con un aviso)."
    echo "Para instalarlo (solo es, en y pt) y volver a ejecutar este script, ver el README (sección Traductor)."
fi

if command -v ufw >/dev/null && ufw status | grep -q '^Status: active'; then
    paso "Cortafuegos: abriendo el puerto 80"
    ufw allow 80/tcp
fi

paso "Listo"
echo "Abre http://$IP desde cualquier equipo de la red."
echo "Errores (Apache y Symfony): /var/log/apache2/sistema_error.log"
if systemctl is-enabled --quiet libretranslate 2>/dev/null; then
    echo "Traductor: systemctl status libretranslate  (tarda unos segundos en estar listo tras arrancar)"
fi
