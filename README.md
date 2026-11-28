# AutoPlaza

Sitio web de un concesionario de autos hecho con **Symfony 7.4**: catálogo de 140 autos con búsqueda y filtros, ficha de cada auto con galería y datos del motor, formulario "Me interesa", registro e inicio de sesión, y panel de administración para gestionar autos, consultas y usuarios.

La web está en **español, inglés y portugués**, con modo claro y oscuro.

| Pieza | Detalle |
|---|---|
| Backend | PHP 8.4 (mínimo 8.2), Symfony 7.4, Doctrine ORM 3 |
| Base de datos | SQLite (`var/data.db`) |
| Frontend | Twig, Tailwind CSS v4 (sin Node), AssetMapper, Stimulus y Turbo |
| Traductor | LibreTranslate local, para traducir los mensajes de las consultas en el panel |
| Servidor | Servidor integrado de PHP en local; Apache + mod_php en la red local |

---

## Requisitos

- PHP 8.2 o superior con las extensiones `intl`, `pdo_sqlite`, `mbstring`, `ctype` e `iconv`
- [Composer](https://getcomposer.org/)
- Para la red local: Apache 2 con `libapache2-mod-php`, `rsync` y permisos de `sudo`
- Opcional: LibreTranslate en `~/.local/share/libretranslate/venv`. Sin él la web funciona igual, pero los mensajes de las consultas se ven sin traducir.

En Debian, Ubuntu o Parrot:

```bash
sudo apt install php php-intl php-sqlite3 php-mbstring composer rsync apache2 libapache2-mod-php
```

---

## 1. Ejecutar en localhost (desarrollo)

### Primera vez

```bash
cd Sistema
composer install                                        # dependencias
php bin/console tailwind:build                          # descarga Tailwind (una vez) y compila el CSS
php bin/console doctrine:migrations:migrate -n          # crea var/data.db
php bin/console app:importar-autos                      # carga los 140 autos de data/autos.json
php bin/console app:crear-admin tu@correo.com "Tu nombre"   # crea el administrador (pide la contraseña)
```

### Arrancar

Un solo comando:

```bash
bin/servidor            # → http://localhost:8000   (Ctrl+C para parar todo)
```

Pone en marcha, juntos:

1. **LibreTranslate** (el traductor de las consultas), si está instalado y no está ya en marcha. Tarda unos segundos en estar listo. Registro: `var/log/libretranslate.log`.
2. **Tailwind** en modo vigilante: vuelve a compilar el CSS al guardar una plantilla. Registro: `var/log/tailwind.log`.
3. **El servidor web de PHP**, con un límite de subida de 64 MB para fotos y videos del panel (por defecto PHP admite solo 2 MB).

Ctrl+C para los tres. Un LibreTranslate que ya estuviera en marcha antes (por ejemplo, como servicio) se deja como estaba.

> **Importante:** Tailwind solo genera las clases que encuentra en `templates/` y `assets/controllers/`. Si añades una clase y no se aplica, mira `var/log/tailwind.log` o ejecuta `php bin/console tailwind:build`.

### Traductor (opcional)

`bin/servidor` lo arranca solo. Para arrancarlo a mano:

```bash
~/.local/share/libretranslate/venv/bin/libretranslate --host 127.0.0.1 --port 5000 --load-only es,en,pt --disable-web-ui
```

Si no está instalado o no responde, la web funciona igual: el panel muestra las consultas sin traducir, con un aviso.

### Probar desde otro equipo sin instalar Apache

Para una prueba rápida, haz que el servidor de PHP escuche en todas las interfaces:

```bash
bin/servidor 0.0.0.0:8000
# y abre http://IP-DE-ESTE-EQUIPO:8000 desde el otro equipo (ver la IP con: hostname -I)
```

Sigue en modo desarrollo: muestra la barra de depuración y los errores con detalle a cualquiera de la red, y atiende las peticiones de una en una. Para dejarlo funcionando de forma estable, usa la sección siguiente.

---

## 2. Desplegar en la red local (producción con Apache)

Todo lo hace un script:

```bash
sudo bash deploy/desplegar.sh
```

Al terminar muestra la dirección, por ejemplo **http://192.168.1.207**. Se abre desde cualquier equipo o móvil conectado a la misma red.

### Qué hace el script

1. Copia el proyecto a `/var/www/sistema` con `rsync`. Apache no puede leer dentro de la carpeta personal, que tiene permisos 700.
2. Crea `/var/www/sistema/.env.local` **solo la primera vez**: `APP_ENV=prod`, `APP_DEBUG=0`, un `APP_SECRET` nuevo y `DEFAULT_URI=http://<IP>`.
3. Ejecuta `composer install --no-dev --optimize-autoloader`.
4. Compila el CSS con `tailwind:build --minify` y los assets con `asset-map:compile`, que los deja en `public/assets/`.
5. Aplica las migraciones y prepara la caché.
6. Da permisos: `var/` y `public/uploads/` pasan a ser de `www-data`.
7. Instala el sitio de Apache (`deploy/apache-sistema.conf`), desactiva `000-default`, activa `mod_headers` y recarga Apache.
8. Si LibreTranslate está instalado, lo deja como servicio del sistema (`deploy/libretranslate.service`): arranca con el equipo, también cuando arranca o se reinicia Apache (`deploy/apache2-libretranslate.conf`), y se vuelve a arrancar solo si se cae. Si no está instalado, el script lo avisa al final.
9. Si el cortafuegos `ufw` está activo, abre el puerto 80.

### Actualizar después de hacer cambios

Ejecuta el mismo comando:

```bash
sudo bash deploy/desplegar.sh
```

Copia el código nuevo, pero **no sobrescribe** la base de datos del servidor (`var/data.db`), las fotos subidas desde el panel (`public/uploads/`) ni su `.env.local`. Solo la primera vez copia la base de datos y las fotos del equipo de desarrollo.

### Comandos útiles en el servidor

```bash
# Crear o promover un administrador en la base de datos del servidor
cd /var/www/sistema && sudo -u www-data php bin/console app:crear-admin correo@ejemplo.com "Nombre"

# Ver errores (Apache y Symfony)
sudo tail -f /var/log/apache2/sistema_error.log

# Estado de los servicios
systemctl status apache2
systemctl status libretranslate

# Copia de seguridad de los datos
sudo tar czf autoplaza-respaldo-$(date +%F).tar.gz -C /var/www/sistema var/data.db public/uploads
```

### Recomendaciones

- **IP fija:** el router suele dar la IP por DHCP y puede cambiarla. Reserva una IP fija para este equipo en la configuración del router.
- **HTTP sin cifrar:** basta para una red de casa u oficina, pero las contraseñas viajan sin cifrar por la red. No lo expongas a Internet así.
- **Correo:** para que las consultas lleguen por correo, añade en `/var/www/sistema/.env.local`:
  ```bash
  MAILER_DSN=smtp://usuario:clave@servidor:587
  CONCESIONARIO_EMAIL=ventas@tu-dominio.com
  ```
  y después ejecuta `cd /var/www/sistema && sudo php bin/console cache:clear && sudo chown -R www-data:www-data var`.

---

## 3. Otros comandos

```bash
php bin/phpunit                                       # tests (usan var/test.db, no tocan los datos reales)
php bin/console app:importar-autos --reemplazar       # borra los autos y vuelve a cargar data/autos.json
python3 data/combinar_marcas.py                       # regenera data/autos.json desde data/marcas/*.json
php bin/console debug:router                          # lista de rutas
php bin/console debug:translation en --only-missing   # traducciones que faltan
php bin/console cache:clear
```

---

## 4. Estructura

```
assets/          CSS (entrada de Tailwind), controladores Stimulus, fuentes e imágenes
config/          configuración de Symfony (seguridad, idiomas, Tailwind...)
data/            semilla de autos (autos.json) y datos por marca
deploy/          script de despliegue, sitio de Apache y servicio de LibreTranslate
migrations/      migraciones de Doctrine
public/          raíz web (index.php, uploads/, assets/ compilados)
src/             código PHP: controladores, catálogo, entidades, formularios, servicios
templates/       plantillas Twig
tests/           tests de PHPUnit
translations/    textos en es, en y pt
```

Más detalles del funcionamiento interno en [`PLAN.md`](PLAN.md).

---

## 5. Problemas frecuentes

| Problema | Solución |
|---|---|
| La página se ve sin estilos | Ejecuta `php bin/console tailwind:build`. En el servidor, vuelve a ejecutar `sudo bash deploy/desplegar.sh`. |
| Una clase de Tailwind nueva no se aplica | Deja abierto `php bin/console tailwind:build --watch`. |
| Error 500 en el servidor | Mira `/var/log/apache2/sistema_error.log`. Suele ser un problema de permisos: `sudo chown -R www-data:www-data /var/www/sistema/var /var/www/sistema/public/uploads` |
| No abre desde otro equipo | Comprueba la IP (`hostname -I`), que los dos equipos estén en la misma red y el cortafuegos (`sudo ufw status`). |
| "Traductor no disponible" en el panel | Arranca LibreTranslate: `sudo systemctl start libretranslate` (servidor) o el comando de la sección 1 (local). |
| No se pueden subir videos grandes | En local, arranca el servidor con los `-d` de la sección 1. En Apache el límite ya es de 64 MB. |
