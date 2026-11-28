# PLAN.md — AutoPlaza (Sistema)

> **Leer este archivo antes de tocar o escribir código.** Aquí está lo que ya existe y lo que falta, para no repetir tareas.
> Al terminar una tarea, actualizar la sección correspondiente (Hecho / Pendiente / Registro de cambios).
> Las ideas numeradas están en `ideas.md`; se aplican cuando se piden por el chat ("aplica idea #X").

Última actualización: 2026-11-28

---

## 1. Qué es

Sitio web de un **concesionario de autos ("AutoPlaza")**.
- **Landing pública** con presentación y formulario de entrada; registro abierto.
- **Solo con sesión:** "Busca tu modelo" (todos los autos, búsqueda y filtros), catálogo en pestañas (marca → modelo), y detalle de cada auto con galería, datos del motor y formulario "Me interesa".
- **Solo el admin:** panel para gestionar autos, consultas y ver usuarios.

El código propio está en **español**. La web está en 3 idiomas: español (por defecto), inglés y portugués (chino y japonés se quitaron el 2026-11-28).

## 2. Stack

| Pieza | Versión / detalle |
|---|---|
| PHP | 8.4 (requiere ≥ 8.2), extensiones `intl` y `pdo_sqlite` |
| Framework | Symfony 7.4 (webapp completo) |
| Plantillas | Twig, formularios con Symfony Form |
| Front | AssetMapper + importmap (sin Node/npm), Stimulus 3.2.2, Turbo 8.0.23 |
| CSS | **Tailwind CSS v4.3.5** con `symfonycasts/tailwind-bundle` (binario independiente, sin Node; se descarga en `var/tailwind/`). Entrada: `assets/styles/app.css`. Modo claro/oscuro con colores semánticos `light-dark()` + variante `dark:` |
| Fuentes | Michroma (títulos) y Saira (texto), guardadas en `assets/fonts/` |
| Idiomas | Symfony Translation, formato ICU (`translations/`) |
| BD | **SQLite** (`var/data.db`) con Doctrine ORM 3 + Migrations. Decidido seguir con SQLite (2026-11-28). PostgreSQL 17 está instalado en el equipo pero apagado y sin `pdo_pgsql`; el de `compose.yaml` no se usa (no hay Docker) |
| Seguridad | Security: usuarios en BD, form_login en la landing, remember_me, login_throttling (`symfony/rate-limiter`) |
| Correo | Mailer, envío síncrono. `MAILER_DSN=null://null` (no sale nada) |
| Traductor | **LibreTranslate** local (`LIBRETRANSLATE_URL`, por defecto `http://127.0.0.1:5000`) para los mensajes de las consultas. Instalado en `~/.local/share/libretranslate/venv` (fuera del proyecto), solo con los modelos es, en y pt. Cliente HTTP `libretranslate.client` en `framework.yaml` |
| Teléfonos | `giggsey/libphonenumber-for-php-lite` (metadatos de Google libphonenumber): validar y dar formato a los teléfonos de las consultas |
| Tests | PHPUnit 13 (+ browser-kit, css-selector). BD de tests: `var/test.db` |
| Instalados sin usar | Messenger (salvo la tabla de cola), Notifier, Serializer |

## 3. Cómo funciona

### Datos
- **Autos:** tabla `vehiculo` (entidad `Vehiculo`). Guarda los datos "en bruto" con la misma forma que `data/autos.json`: `descripcion` es `{idioma: texto}` y `medios` una lista `{tipo, src, alt: {idioma: texto}}` (columnas JSON).
- `data/autos.json` es la **semilla** (140 autos; se genera desde `data/marcas/*.json` con `python3 data/combinar_marcas.py`): `php bin/console app:importar-autos` la carga en la BD (con `--reemplazar` borra antes). Editar el JSON ya no cambia la web: hay que reimportar o usar el panel.
- Campos de un auto: `slug, marca, modelo, anio, precio, kilometraje, combustible, transmision, carroceria, descripcion, medios[]` y, opcionales, `version` ("xDrive40i (G05)"), `motor` (texto técnico igual en todos los idiomas, "5.0 L V8"), `potencia` (hp), `torque` (Nm), `traccion`, `aceleracion` (s 0–100), `velocidad_maxima` (km/h).
- `CatalogoAutos` lee los autos de una `FuenteAutos` (`FuenteBaseDatos` en la web, `FuenteJson` en la importación y los tests unitarios), los **valida** y los convierte en DTO `Auto`/`Medio` listos para mostrar. Un error lanza `CatalogoInvalidoException` con el lugar (`autos[3].medios[1]: ...`). El panel usa la misma validación antes de guardar.
- Medio `src`: URL `https://` tal cual; YouTube → `youtube-nocookie.com/embed/ID` (tipo interno `youtube`); `/uploads/autos/...` → archivo subido desde el panel (en `public/uploads/autos/`, ignorado por git); otra ruta → archivo en `assets/images/` (AssetMapper). Se bloquea `..` y se comprueban extensiones.
- `descripcion` y `alt`: texto (solo español) u objeto por idioma; `es` obligatorio y de respaldo (clase `TextoTraducido`).
- `combustible`, `transmision`, `carroceria` y `traccion` se guardan en español y se traducen con `translations/valores.*.yaml`. Lista de opciones del panel en `Catalogo/Valores.php` (un valor nuevo hay que añadirlo ahí y en los 5 archivos).
- **Usuarios:** tabla `usuario` (correo único, nombre, contraseña hasheada, roles). **Consultas:** tabla `consulta` (auto y usuario con `SET NULL` al borrar; guarda `autoNombre`, por si se borra el auto). `idioma` es el de la página; `idioma_mensaje` es el que detecta LibreTranslate, y `traducciones` (JSON `{idioma: texto}`) guarda cada traducción para no pedirla otra vez. Si se cambia el mensaje, se borran.
- **Traducción de consultas:** al abrir el resumen o "Consultas" del panel, `TraductorConsultas` traduce al idioma del panel los mensajes que falten (`source: auto`), los guarda y muestra la traducción con el original plegado ("ver el original"). Si LibreTranslate no responde, se ve el original y un aviso; deja de intentarlo en esa petición (tiempo de espera de 5 s).

### Acceso (`config/packages/security.yaml`)
| Quién | Qué ve |
|---|---|
| Sin sesión | `/` (redirige), `/{_locale}` landing con login, `/{_locale}/registro` |
| ROLE_USER (se registra solo) | lo anterior + `/autos`, `/catalogo`, `/autos/{slug}` con "Me interesa" |
| ROLE_ADMIN (incluye ROLE_USER) | lo anterior + enlace "Panel" y `/admin/...` |

- Sin sesión, las páginas del catálogo redirigen a la landing; un usuario normal en `/admin` recibe 403. Doble protección: `access_control` + `#[IsGranted]` en los controladores.
- El admin **no se registra**: se crea con `php bin/console app:crear-admin correo "Nombre"` (pide la contraseña oculta; si el correo ya existe, lo hace admin).
- Entrar: el formulario de la landing hace POST a `/{_locale}/entrar` (lo procesa el firewall). Salir: POST a `/{_locale}/salir` con CSRF. 5 intentos fallidos → bloqueo temporal.
- CSRF "stateless" (`csrf.yaml`) para entrar, salir y formularios: **necesita** `assets/controllers/csrf_protection_controller.js`, importado en `app.js` para que esté en **todas** las páginas. Si solo carga a veces (es un controlador "lazy"), Symfony guarda en la sesión que se usó la cookie de doble envío y después rechaza con **403** los formularios que no la traen (pasó con "Salir").

### Idiomas y tema
- Todas las URLs empiezan con el idioma (prefijo en `config/routes.yaml`). `/` redirige al idioma del navegador (español si no coincide). El selector de la cabecera mantiene la página y los filtros. Lista de idiomas en 3 sitios: `config/packages/translation.yaml`, `config/routes.yaml` y `config/packages/twig.yaml`.
- Tema: sigue el modo del sistema; el botón ☾/☀ lo cambia y lo guarda en `localStorage("tema")` → `<html data-tema="claro|oscuro">`.

## 4. Archivos del proyecto (código propio)

### Backend — `src/`
| Archivo | Qué hace |
|---|---|
| `Controller/InicioController.php` | `GET /` (`inicio`, en `config/routes.yaml`): redirige a la landing en el idioma del navegador. |
| `Controller/SeguridadController.php` | `landing` (`/{_locale}`, con sesión → catálogo), `login_check` (`/entrar`), `salir`, `registro` (crea ROLE_USER y entra). |
| `Controller/AutoController.php` | `#[IsGranted('ROLE_USER')]`. `auto_listado` (`/autos`: "Busca tu modelo", `q` + filtros `marca`, `carroceria`, `orden`; valores no válidos se ignoran), `catalogo` (`/catalogo?marca=&modelo=`: pestañas; abre las de la URL o las primeras), `auto_detalle` (`/autos/{slug}`, GET/POST con el formulario "Me interesa"; 404 si no existe). |
| `Controller/AdminController.php` | `#[IsGranted('ROLE_ADMIN')]`, prefijo `/admin`: `admin_inicio`, `admin_autos`, `admin_auto_nuevo`, `admin_auto_editar`, `admin_auto_borrar` (POST+CSRF), `admin_consultas`, `admin_consulta_leida`, `admin_consulta_borrar`, `admin_usuarios`. |
| `Catalogo/CatalogoAutos.php` | Servicio: valida/convierte (caché por petición), `todos()`, `buscarPorSlug()`, `filtrar(marca, carroceria, orden, texto)` (búsqueda sin acentos ni mayúsculas), `porMarca()`, `secciones()` (marcas → modelos con su clave para la URL), `sugerencias()`, `marcas()`, `carrocerias()`, `validarFila()`, `normalizar()`. Constantes `ORDENES`, `EXTENSIONES_*`, `CARPETA_SUBIDAS`. |
| `Catalogo/FuenteAutos.php`, `FuenteBaseDatos.php`, `FuenteJson.php` | De dónde salen los autos (BD en la web; JSON para importar y en tests). |
| `Catalogo/Auto.php` | DTO readonly: `nombre()`, `portada()`, `tieneVideo()`, `tieneDatosMotor()`. |
| `Catalogo/Medio.php`, `TipoMedio.php`, `TextoTraducido.php`, `CatalogoInvalidoException.php` | DTO de medio, enum Imagen/Video/YouTube, texto por idioma con respaldo, excepción. |
| `Catalogo/Valores.php` | Opciones de combustible, transmisión, carrocería y tracción. |
| `Entity/Vehiculo.php` | Auto en BD. `desdeFila()` / `aFila()` convierten desde/hacia la forma del JSON. |
| `Entity/Usuario.php` | Cuenta (UserInterface). `esAdmin()`. |
| `Entity/Consulta.php` | Consulta "Me interesa" (leída / no leída, idioma detectado, traducciones guardadas). |
| `Repository/*.php` | `VehiculoRepository::todosOrdenados()`, `UsuarioRepository::buscarPorEmail()` (+ rehash de contraseñas), `ConsultaRepository::recientes()`, `contarSinLeer()`. |
| `Form/RegistroType.php`, `ConsultaType.php` | Registro y "Me interesa". |
| `Form/VehiculoType.php`, `MedioType.php`, `TraduccionesType.php` | Formulario de autos del panel (slug automático si se deja vacío; colección de medios con subida de archivo; un campo por idioma). |
| `Servicio/GestorMedios.php` | Mueve archivos subidos a `public/uploads/autos/` y limpia los medios antes de guardar. |
| `Servicio/NotificadorConsultas.php` | Envía el correo de la consulta a `CONCESIONARIO_EMAIL` (si falla, solo lo anota en el log). |
| `Servicio/TraductorConsultas.php` | Traduce y guarda los mensajes de las consultas con LibreTranslate; devuelve `false` si no respondió. |
| `Command/ImportarAutosCommand.php` | `app:importar-autos [archivo] [--reemplazar]`: valida todo y carga el JSON en la BD. |
| `Command/CrearAdminCommand.php` | `app:crear-admin correo [nombre]`. |
| `Validator/NombrePersona.php` | Restricción compuesta para nombres de personas: no puede empezar con espacios y solo admite letras (cualquier idioma, con acentos) unidas por un espacio, apóstrofo o guion. Se usa en `RegistroType`, `ConsultaType` (con `'trim' => false`, si no Symfony quita los espacios antes de validar) y `app:crear-admin`. |
| `Validator/TelefonoInternacional.php` + `TelefonoInternacionalValidator.php` | Teléfono con prefijo del país y número que exista en ese país (libphonenumber). Espera E.164; mensajes `telefono.caracteres_invalidos`, `telefono.sin_pais`, `telefono.no_valido`. En `Consulta::telefono`. |
| `Form/TelefonoType.php` | Teléfono en dos campos: `pais` (lista de 245 países "Nombre (+prefijo)" en el idioma de la página, con `data-ejemplo`) y `numero`. Los junta en E.164 (`+541155551234`); si el número ya empieza con "+", no hace falta país. Si no se puede interpretar, deja lo escrito y el validador da el error. |
| `Validator/CorreoReal.php` + `CorreoRealValidator.php` | El dominio del correo tiene que existir y recibir correo. En `Usuario::email` y `Consulta::email` (junto a `Assert\Email`, que sigue comprobando la forma). Mensaje `correo.dominio_inexistente` con `{{ dominio }}`. |
| `Servicio/ComprobadorDominios.php`, `ComprobadorDominiosDns.php` | Interfaz + implementación con el DNS: vale con MX o, si no hay, A/AAAA; MX nulo (RFC 7505) = no recibe correo. Si tampoco encuentra gmail.com/outlook.com, el DNS no funciona: devuelve `null`, se acepta el correo y se anota un aviso en el log. Caché por petición. En los tests se sustituye por `tests/Soporte/ComprobadorDominiosFalso.php` (`services.yaml`, `when@test`). |
| `Twig/FormatoExtension.php` | Filtro `numero`: separadores según el idioma (48.900 / 48,900). Filtro `nombre_idioma`: "pt" → "portugués". Filtro `telefono`: "+541155551234" → "+54 11 5555-1234" (panel y correo). |
| `migrations/Version20261127173453.php` | Tablas `usuario`, `vehiculo`, `consulta` (+ cola de Messenger). |
| `migrations/Version20261128125835.php` | `consulta.idioma_mensaje` y `consulta.traducciones`; `vehiculo.version` si falta (la idea #9 la había creado sin migración). |

### Plantillas — `templates/`
| Archivo | Qué hace |
|---|---|
| `_iconos.html.twig` | Macro `icono(nombre, clase)`: iconos SVG de línea (buscar, sol, luna, auto, panel, mensaje...). Hay que importarlo en cada plantilla que lo use (`{% import '_iconos.html.twig' as i %}`), también dentro de macros. |
| `base.html.twig` | Layout: cabecera fija de vidrio (sticky + blur), menú según rol (Buscar, Catálogo, Panel solo admin), idiomas, tema, nombre + "Salir"; avisos (flash); pie. |
| `seguridad/landing.html.twig`, `registro.html.twig` | Landing con login y fotos; registro. |
| `auto/listado.html.twig` | "Busca tu modelo": barra con autocompletado en línea (`autocompletar_controller.js`; sugerencias y marcas en atributos `data-*` como JSON; capa `.autocompletado` encima de la entrada con el mismo relleno y tamaño de letra), botón "Ver catálogo", filtros (conservan la posición: `conservar_scroll_controller.js`), ancla `#resultados` (la paginación lleva ahí), grilla. |
| `auto/catalogo.html.twig` | Pestañas de marcas y, dentro de cada marca, pestañas de modelos (columna lateral). Solo cambia el contenido: no recarga ni desplaza la página. En escritorio la lista de modelos mide lo mismo que la ficha (`lg:contain-size` + `self-stretch`, tope de una pantalla) y se recorre con su barra (`scroll-fino`). |
| `auto/_ficha_catalogo.html.twig` | Un auto dentro de la pestaña de su modelo: foto, precio, datos clave y "Ver ficha completa". |
| `auto/detalle.html.twig` | Galería, ficha, "Motor y prestaciones" y formulario "Me interesa" (`#consulta`). |
| `auto/_tarjeta.html.twig`, `_medio.html.twig`, `_precio.html.twig` | Tarjeta (con motor y hp), medio, precio. |
| `admin/_base.html.twig` + `inicio`, `autos`, `auto_form`, `consultas`, `usuarios`, `_consulta` | Panel. |
| `email/consulta.html.twig` | Correo al concesionario (en español). |

Todos los textos usan `|trans`.

### Traducciones — `translations/`
| Archivo | Qué hace |
|---|---|
| `messages+intl-icu.{es,en,pt}.yaml` | Textos de la interfaz (ICU, con plurales). Mismas claves en los 3. |
| `validators.{es,en,pt}.yaml` | Mensajes de validación propios (`campo.obligatorio`, etc.). |
| `valores.{es,en,pt}.yaml` | Combustible / transmisión / carrocería / tracción. |

### Front — `assets/`
| Archivo | Qué hace |
|---|---|
| `app.js` | Arranca Stimulus, importa el CSS y la protección CSRF (en todas las páginas). |
| `controllers/tema_controller.js` | Botón modo claro/oscuro. |
| `controllers/galeria_controller.js` | Galería del detalle. |
| `controllers/pestanas_controller.js` | Pestañas accesibles y anidables (marcas → modelos): cambia el panel sin recargar, flechas del teclado, actualiza `?marca=&modelo=` con `replaceState`. Al cargar (y con el teclado) desplaza solo la lista hasta la pestaña abierta, sin mover la página. Sin JS los enlaces recargan y funcionan igual. |
| `controllers/autocompletar_controller.js` | Autocompletado **en línea** de "Busca tu modelo" (sin listas): "Lambo" → "rghini" en gris dentro de la barra. Capa encima de la entrada: copia invisible de lo escrito + lo que falta. Candidatas: marcas, "Marca Modelo" y el modelo solo; gana la más corta. Tab o → aceptan (con la forma correcta: "hura" → "Huracán"), tocar el gris en el móvil, Escape la quita hasta que cambie el texto, Enter busca lo escrito. Si el texto no cabe en la barra, no se muestra (no cuadraría). Sin JS la barra funciona igual. |
| `controllers/conservar_scroll_controller.js` | En el formulario de "Busca tu modelo": al enviarlo (filtrar, ordenar, buscar) o al pulsar "Quitar filtros" guarda `scrollY` en `sessionStorage` y lo recupera en `turbo:load` (que llega después de que Turbo suba la página), solo si sigue en la misma ruta. Mantiene el historial. |
| `controllers/telefono_controller.js` | En "Me interesa": al elegir el país, el campo del número muestra un número de ejemplo de ese país. |
| `controllers/coleccion_controller.js` | Agregar/quitar fotos y videos en el formulario del panel. |
| `controllers/confirmar_controller.js` | "¿Seguro?" antes de borrar. |
| `controllers/csrf_protection_controller.js` | **Necesario**: genera los tokens CSRF stateless de los formularios (importado en `app.js`). |
| `controllers/hello_controller.js` | De la receta de Symfony (sin usar). |
| `styles/app.css` | **Entrada de Tailwind** (no se sirve tal cual: AssetMapper sirve `var/tailwind/app.built.css`). `@theme`: fuentes (`font-display` Michroma, `font-sans` Saira), escala `marca-50…950` (500 = rojo del logo #e31e24), colores semánticos que cambian solos con el modo (`fondo`, `superficie`, `superficie-2`, `texto`, `suave`, `borde`, `acento`, `error`, `exito`), sombras `suave/elevada/brillo`, animaciones `aparecer` y `desfile`. `@custom-variant dark`: `data-tema` o, si no hay, el sistema. `@layer components`: solo lo que genera Twig/Symfony Form y no admite utilidades (campos de `.formulario`, `.boton`, `.aviso`, `.especificaciones`, `.tabla`, formulario de autos). Utilidades propias: `scroll-sin-barra`, `scroll-fino` (barra delgada visible en rojo, para listas que hay que recorrer), `texto-degradado`, `fondo-pagina`, `rejilla-fondo`. Las plantillas usan utilidades de Tailwind; las clases con nombre propio (`.tarjeta`, `.ficha`, `.pestanas__panel`, `.miniatura.activa`...) se mantienen porque los tests y los controladores Stimulus las usan. |
| `fonts/` | Michroma y Saira (OFL). |
| `images/autoplaza.png` | Logo original (2000×795, fondo transparente). |
| `images/logo-cabecera.png`, `favicon-32.png`, `favicon-192.png`, `apple-touch-icon.png` | Generados desde `autoplaza.png` (sin márgenes): logo de la cabecera (sobre el texto AUTOPLAZA) e iconos de la pestaña del navegador. Si cambia el logo, hay que volver a generarlos. |
| `images/autos/<marca>/*.jpg` | Fotos de los 140 autos (Wikimedia Commons; autor y licencia en el campo de crédito de cada medio). |

### Tests — `tests/` (73 tests, 377 aserciones, **todos pasan**)
- `Soporte/LibreTranslateFalso.php`: sustituye a LibreTranslate en los tests (`mock_response_factory` en `framework.yaml`, servicio en `services.yaml` `when@test`).
- `Soporte/ComprobadorDominiosFalso.php`: sustituye al DNS; todo dominio existe salvo los que contienen "inexistente" o terminan en `.invalid`; con "sindns" simula que el DNS no responde.
- `Servicio/ComprobadorDominiosDnsTest.php` (grupo `red`, usa el DNS real; se salta sin internet): gmail.com/outlook.com valen; dominio inventado, `gmial.con` y example.com (MX nulo) no.
- `Soporte/BaseDeDatosDePrueba.php`: rehace `var/test.db` en cada test con los 8 autos fijos de `tests/Fixtures/autos.json` + `admin@autoplaza.test` y `cliente@autoplaza.test`.
- `Catalogo/CatalogoAutosTest.php`: medios, filtrar+ordenar, búsqueda sin acentos, catálogo por marca y `secciones()`, datos del motor, archivos subidos, errores de validación, traducciones.
- `Controller/SeguridadControllerTest.php`: `/` por idioma (y ja → en), landing pública, sin sesión → landing, entrar/contraseña mala, registro (y errores traducidos), nombres no válidos (espacios al inicio, símbolos, números, HTML, dos espacios) y válidos (acentos, apóstrofo, guion), correo de dominio inexistente (422) y aceptado si el DNS no responde, usuario normal sin panel (403), admin con panel, salir.
- `Controller/AutoControllerTest.php`: listado en en y pt, barra con autocompletado en línea (sin `<datalist>` ni listbox, capa de sugerencia, ayuda para lectores de pantalla, sugerencias y marcas en JSON), búsqueda, filtros, selector de idioma, catálogo en pestañas (por defecto y con `?marca=&modelo=` de la URL), detalle con motor, detalle traducido, "Me interesa" (guarda + correo, teléfono guardado en E.164) y errores 422 en portugués, teléfono (lista de países con prefijo y ejemplo, prefijo escrito sin país, sin teléfono, y rechazos: cifras al azar, demasiado largo, número de otro país, prefijo inexistente, sin país ni prefijo, letras), nombre con espacios al inicio o símbolos, correo de dominio inexistente, 404.
- `Controller/AdminControllerTest.php`: resumen y listados, crear auto con foto subida, no guarda un auto inválido, editar, borrar conservando consultas, marcar consulta leída, consultas traducidas al idioma del panel (una sola vez) y aviso si el traductor no responde.

## 5. Comandos

```bash
# Primera vez (o en otro equipo):
php bin/console doctrine:migrations:migrate -n        # crea var/data.db
php bin/console app:importar-autos                    # carga data/autos.json (--reemplazar para rehacerlo)
php bin/console app:crear-admin tu@correo.com "Nombre"  # crea el admin (pide la contraseña)

# Arrancar en local (un solo comando; Ctrl+C lo para todo) → http://localhost:8000
#   LibreTranslate (si está instalado y no está ya en marcha) + Tailwind --watch + php -S con subidas de 64 MB.
#   Registros en var/log/libretranslate.log y var/log/tailwind.log. "bin/servidor 0.0.0.0:8000" para la red.
bin/servidor

# CSS: Tailwind solo genera las clases que encuentra en templates/ y assets/controllers/.
php bin/console tailwind:build                        # compilar una vez (bin/servidor ya deja el vigilante)

php bin/phpunit                                       # tests
php bin/console debug:router                          # rutas
php bin/console debug:translation en --only-missing   # traducciones que faltan
php bin/console cache:clear
```

Traductor (LibreTranslate): `bin/servidor` lo arranca solo. A mano:

```bash
~/.local/share/libretranslate/venv/bin/libretranslate --host 127.0.0.1 --port 5000 --load-only es,en,pt --disable-web-ui
```

En el servidor: `deploy/desplegar.sh` lo instala como servicio del sistema (`/etc/systemd/system/libretranslate.service`, plantilla en `deploy/libretranslate.service`, se ejecuta con el usuario del proyecto, `Restart=always`). Arranca con el equipo y también con Apache: el complemento `deploy/apache2-libretranslate.conf` (→ `/etc/systemd/system/apache2.service.d/libretranslate.conf`) pone `Wants=libretranslate.service` en Apache (no `Requires`: si el traductor falla, la web sigue). Si LibreTranslate no está instalado, el script lo avisa. Docker no está instalado, así que no se usa la opción de `compose.yaml`. Si no responde, el panel muestra las consultas sin traducir y un aviso.

Límite conocido: con mensajes muy cortos ("Hola, ¿el auto sigue disponible?") la detección automática de idioma de LibreTranslate falla (devuelve "en" con confianza 0) y el mensaje se queda sin traducir; con mensajes normales detecta bien es/en/pt.

### Despliegue en la red local (Apache)

```bash
sudo bash deploy/desplegar.sh      # primera vez y cada actualización
```

- Copia el proyecto a `/var/www/sistema` con rsync (la carpeta de casa tiene permisos 700 y Apache no puede entrar). La primera vez copia también `var/data.db` y `public/uploads/`; después **no los pisa** (los datos del servidor mandan).
- Crea `/var/www/sistema/.env.local` una sola vez: `APP_ENV=prod`, `APP_DEBUG=0`, `APP_SECRET` nuevo y `DEFAULT_URI=http://<IP>`.
- `composer install --no-dev`, `tailwind:build --minify`, `asset-map:compile` (→ `public/assets/`), migraciones, caché y permisos (`var/` y `public/uploads/` de `www-data`).
- Apache con mod_php: `deploy/apache-sistema.conf` → `/etc/apache2/sites-available/sistema.conf` (`FallbackResource /index.php`, subidas de 64 MB, caché de un año para `/assets`), desactiva `000-default`, activa `headers`. Abre el puerto 80 si ufw está activo.
- LibreTranslate: servicio del sistema que arranca con el equipo y con Apache (`Wants=`), `Restart=always`.
- Web: `http://<IP del equipo>` (el 2026-11-28 era 192.168.1.207, por DHCP: conviene reservarla en el router). Errores: `/var/log/apache2/sistema_error.log`.

Correo real: poner `MAILER_DSN` (por ejemplo `smtp://usuario:clave@servidor:587`) y `CONCESIONARIO_EMAIL` en `.env.local`.

## 6. Estado

### Hecho ✅
- [x] Catálogo con validación completa y mensajes claros.
- [x] Detalle con galería de imágenes, video mp4 y YouTube.
- [x] Diseño responsive (CSS propio hasta el 2026-11-28; ahora Tailwind).
- [x] Idea #1: interfaz minimalista con modo claro/oscuro (azul–negro; desde el 2026-11-28, rojo del logo: rojo → blanco / rojo → negro).
- [x] Logo de AutoPlaza en la cabecera y como icono de la pestaña del navegador.
- [x] Idea #2: idiomas, incluido el contenido de los autos. Desde el 2026-11-28 solo es, en y pt.
- [x] Mensajes de las consultas traducidos al idioma del panel (LibreTranslate local).
- [x] Idea #3: tipografía automotriz (Michroma + Saira).
- [x] Idea #4.1: "Busca tu modelo" + catálogo en pestañas (marca → modelo) + datos del motor.
- [x] Corregido: 403 al pulsar "Salir" (CSRF).
- [x] Idea #5: panel de administración (autos, consultas, usuarios).
- [x] Idea #7: formulario "Me interesa" (se guarda y se envía por correo).
- [x] Idea #8: landing con login, registro, roles usuario/admin.
- [x] Idea #6: SQLite + Doctrine (PostgreSQL aplazado).
- [x] Idea #4: 140 autos con fotos reales y anuncios de YouTube (importados en la BD el 2026-11-28).
- [x] Idea #9: más filtros y paginación.
- [x] Idea #10: repositorio git.
- [x] Interfaz rehecha con Tailwind CSS v4 (2026-11-28).
- [x] Despliegue en la red local con Apache (`deploy/desplegar.sh`).
- [x] Arreglo 1: validación de nombres (sin espacios al inicio ni caracteres especiales).
- [x] Arreglo 2: correos solo de dominios reales (comprobación DNS).
- [x] Arreglo 3: autocompletado en línea en "Busca tu modelo" (texto gris dentro de la barra, sin listas).
- [x] Arreglo 4: filtrar no lleva al inicio de la página; la paginación lleva a los resultados.
- [x] Arreglo 6 (y el 7 de `ideas.md`): catálogo: la barra de marcas ya no recorta BMW y la lista de modelos se puede recorrer (barra visible, mide lo mismo que la ficha).
- [x] Arreglo 7: teléfono de "Me interesa" con país (+prefijo) y número válido para ese país.
- [x] Arreglo 5: LibreTranslate arranca solo: en local con `bin/servidor`, en el servidor como servicio ligado a Apache.

### Pendiente
- No quedan ideas pendientes en `ideas.md` (PostgreSQL/MariaDB queda aplazado hasta elegir hosting, ver #6).
- No hay recuperación de contraseña ("olvidé mi contraseña"): hace falta un correo configurado.
- Al borrar un auto o un medio no se borran sus archivos de `public/uploads/autos/`.
- El correo al concesionario lleva el mensaje en su idioma original (no se traduce).
- Mensajes muy cortos: LibreTranslate no detecta bien el idioma y no los traduce (se podría usar el idioma de la página como respaldo cuando la confianza es 0).
- La comprobación de dominios no bloquea correos temporales/desechables (mailinator, etc.): esos dominios existen. Haría falta una lista de bloqueo.

## 7. Registro de cambios
- 2026-11-27 — Se crea PLAN.md describiendo el estado actual del proyecto.
- 2026-11-27 — Se crea `ideas.md` (banco de ideas #1–#3: interfaz azul–negro claro/oscuro, idiomas, tipografía automotriz). Flujo: "aplica idea #X" por chat.
- 2026-11-27 — Las ideas pendientes de PLAN.md pasan a `ideas.md` (#4–#10). La idea #2 traduce la página entera, incluido el contenido de los autos.
- 2026-11-27 — Ideas #1, #2 y #3 aplicadas: diseño azul–negro con modo claro/oscuro, 5 idiomas con URLs `/{_locale}`, fuentes Michroma + Saira. Tests: 20 OK.
- 2026-11-27 — Ideas #4.1, #5, #7 y #8 aplicadas (y casi toda la #6): autos, usuarios y consultas en SQLite con Doctrine; landing pública con login y registro; catálogo solo con sesión; panel solo para admin; "Busca tu modelo" + catálogo por marca + datos del motor; "Me interesa". URLs nuevas: listado en `/{_locale}/autos` (antes `/{_locale}`). Tests: 44 OK.
- 2026-11-27 — Corregido 403 en `POST /{_locale}/salir`: la protección CSRF stateless solo cargaba en páginas con formularios de Symfony; ahora se importa en `app.js`. El catálogo pasa a pestañas anidadas (marca → modelo) que cambian sin recargar ni desplazar la página, con `?marca=&modelo=` en la URL. Tests: 45 OK.
- 2026-11-27 — (commit `1e7d703`, no se registró aquí) Ideas #4 y #9: 140 autos con fotos de Wikimedia y anuncios de YouTube, campos `version` y crédito de foto, filtros de combustible, transmisión, precio y año, y paginación de 12. Tests con datos fijos en `tests/Fixtures/autos.json`.
- 2026-11-28 — La web seguía mostrando 8 autos porque no se había reimportado `data/autos.json`: se ejecutó `app:importar-autos --reemplazar` (140 autos; copia previa en `var/data.db.bak-2026-11-28`) y se volvió a enlazar la consulta existente con su auto. Se decide seguir con SQLite. `.vscode/settings.json` ignora `var/` (los avisos de VSCode venían de la caché generada de Symfony). Tests: 50 OK.
- 2026-11-28 — Se quitan el chino y el japonés: la web queda en es, en y pt (rutas, `security.yaml`, `translation.yaml`, `twig.yaml`, traducciones, CSS de fuentes CJK, textos de `autos.json`, de la BD y de los tests). `data/combinar_marcas.py` vuelve a generar `autos.json` idéntico (antes, al ejecutarlo otra vez, dejaba de convertir de CV a hp en Porsche y Lamborghini). Mensajes de las consultas traducidos al idioma del panel con LibreTranslate local (columnas `idioma_mensaje` y `traducciones`; la migración nueva también crea `vehiculo.version`, que faltaba). Copias previas de la BD: `var/data.db.bak-2026-11-28-idiomas` y `-migracion`. Tests: 50 OK.
- 2026-11-28 — Rediseño de colores con el rojo del logo (`assets/images/autoplaza.png`): escala `--rojo-*` en lugar de `--azul-*`; cabecera de rojo a blanco (modo claro) o de rojo a negro (modo oscuro), con el logo siempre sobre el rojo; pie liso con una línea roja arriba; errores en tono vino/naranja para no confundirlos con la marca. Logo en la esquina de la cabecera, sobre el texto AUTOPLAZA, y como icono de la pestaña (antes 🚗). Corregido: en móvil la landing no pasaba a una columna (la regla general estaba después de la del `@media`). Revisado en Chromium (escritorio, 1920 px y móvil, claro y oscuro). Tests: 50 OK.
- 2026-11-28 — **Interfaz con Tailwind CSS v4** (`symfonycasts/tailwind-bundle`, binario v4.3.5, sin Node): `app.css` pasa a ser la entrada de Tailwind (de 1564 líneas de CSS propio a tema + componentes mínimos) y todas las plantillas se rehacen con utilidades: cabecera fija de vidrio con menú en píldoras e iconos, selector de idioma segmentado, botón de tema con iconos sol/luna, avatar con la inicial; landing con titular en degradado, ventajas con iconos, login en tarjeta de vidrio y desfile infinito de fotos; búsqueda en tarjeta con barra grande; tarjetas con zoom de la foto, año, chips (km, transmisión, hp) y precio; catálogo con pestañas de marca fijas y lista de modelos lateral fija; detalle con galería grande, miniaturas con anillo, precio + "Me interesa" y bloque oscuro de "Motor y prestaciones"; panel con menú lateral, cifras en tarjetas, tablas modernas y consultas con barra roja si no están leídas. Nueva macro de iconos `templates/_iconos.html.twig`. Se conservan las clases que usan los tests y Stimulus. Respeta "reducir movimiento". Revisado con capturas en claro/oscuro, escritorio y móvil. Tests: 50 OK.
- 2026-11-28 — **Despliegue en la red local**: `deploy/desplegar.sh` (rsync a `/var/www/sistema`, `.env.local` de producción, composer sin dev, Tailwind minificado, `asset-map:compile`, migraciones, permisos, Apache, LibreTranslate como servicio del sistema, ufw), `deploy/apache-sistema.conf`, `deploy/libretranslate.service`. Probado en seco en una carpeta temporal en modo prod (sin sudo); falta ejecutarlo con sudo.
- 2026-11-28 — Se crea `README.md`: requisitos, ejecución en localhost (desarrollo, con `tailwind:build --watch`), prueba rápida en la red con `php -S 0.0.0.0`, despliegue en la red local con `deploy/desplegar.sh` (qué hace, cómo actualizar, comandos del servidor, copias de seguridad, correo), otros comandos, estructura y problemas frecuentes.
- 2026-11-28 — **Arreglo 1** (`ideas.md`): nueva restricción `App\Validator\NombrePersona` en el nombre del registro, de "Me interesa" y de `app:crear-admin`. Rechaza "  Fabian" (antes Symfony quitaba los espacios en silencio: los campos pasan a `'trim' => false`) y cualquier carácter que no sea letra, espacio simple, apóstrofo o guion. Mensajes `nombre.espacio_inicial` y `nombre.caracteres_invalidos` en `validators.{es,en,pt}.yaml`. Tests: 58 OK.
- 2026-11-28 — **Arreglo 2** (`ideas.md`): correos solo de dominios reales. Restricción `App\Validator\CorreoReal` en `Usuario::email` y `Consulta::email`, con `ComprobadorDominiosDns` (MX o A/AAAA; MX nulo = no recibe correo; si el DNS no responde se acepta y se anota en el log). En los tests el DNS se sustituye por `ComprobadorDominiosFalso`; el test del DNS real va en el grupo `red`. Mensaje `correo.dominio_inexistente` en `validators.{es,en,pt}.yaml`. Tests: 63 OK.
- 2026-11-28 — **Arreglo 3** (`ideas.md`): la barra de "Busca tu modelo" deja el `<datalist>` (el navegador lo mostraba como un menú aparte) por un autocompletado propio debajo de la barra: controlador Stimulus `autocompletar_controller.js`, claves `busqueda.tipo_marca`, `busqueda.tipo_modelo` y `busqueda.sugerencias` en es/en/pt. Revisado en Chromium sin interfaz (claro y oscuro): "lam" → Lamborghini / Lamborghini Huracán, "huracan" encuentra "Huracán", flechas, Escape y elegir con Enter (busca "McLaren" → 1 resultado). Tests: 64 OK.
- 2026-11-28 — **Arreglo 3, corregido**: no se pedía una lista desplegable sino autocompletado en línea. `autocompletar_controller.js` se rehace: "Lambo" muestra "rghini" en gris claro dentro de la barra; Tab/→/tocar aceptan, Escape quita, Enter busca lo escrito. Se quitan las claves `busqueda.tipo_marca`, `busqueda.tipo_modelo` y `busqueda.sugerencias`; nueva `busqueda.completar_ayuda` (ayuda oculta para lectores de pantalla, es/en/pt). Revisado en Chromium (claro y oscuro: el gris queda alineado justo detrás del cursor). Tests: 64 OK.
- 2026-11-28 — **Arreglo 4** (`ideas.md`): al filtrar/ordenar/buscar/quitar filtros en "Busca tu modelo" la página ya no sube al inicio: `conservar_scroll_controller.js` guarda la posición antes de enviar y la recupera en `turbo:load`. Los enlaces de la paginación llevan `#resultados` (abren la página siguiente en los resultados). Test de paginación actualizado (href con `#resultados`) y comprobación del cableado del controlador. Revisado en Chromium sin interfaz sobre una copia de la BD (140 autos): la posición se conserva al filtrar (300 → 300), ordenar (420 → 420) y quitar filtros; Atrás funciona; los demás enlaces siguen yendo arriba. Tests: 64 OK.
- 2026-11-28 — **Arreglo 5** (`ideas.md`): LibreTranslate arranca solo junto con la web. En local, nuevo `bin/servidor`: un comando que arranca LibreTranslate (si está instalado y no responde ya; lee `LIBRETRANSLATE_URL` de `.env`/`.env.local`), `tailwind:build --watch` y `php -S` con subidas de 64 MB; cada proceso en su propio grupo (`setsid`) y Ctrl+C los para todos (probado en un pseudo-terminal: 5 procesos → 0, puertos libres). En el servidor, `desplegar.sh` instala además `deploy/apache2-libretranslate.conf` (Apache `Wants=libretranslate.service`), reinicia el servicio y avisa si LibreTranslate no está instalado; el servicio pasa a `Restart=always`. README actualizado. Docker no está instalado: no se usa `compose.yaml`. Encontrado de paso: con mensajes muy cortos la detección automática de idioma falla (pendiente).
- 2026-11-28 — **Arreglo 7** (`ideas.md`): el teléfono de "Me interesa" deja de aceptar cualquier cifra. Nuevo `Form/TelefonoType` (país con prefijo + número; lista de países por nombre en el idioma de la página) y restricción `Validator/TelefonoInternacional` en `Consulta::telefono` (sustituye al `Assert\Regex`), con `giggsey/libphonenumber-for-php-lite` (^9.0): el número tiene que existir en el país (largo y formato). Se guarda en E.164; filtro Twig `telefono` para mostrarlo en el panel y el correo; `telefono_controller.js` pone un número de ejemplo del país como placeholder. Sigue siendo opcional. Si hay error se marcan las dos partes (`aria-invalid`). Claves nuevas: `consulta.telefono_pais`, `consulta.telefono_pais_elegir`, `consulta.telefono_numero`, `consulta.telefono_ayuda` y, en validators, `telefono.caracteres_invalidos`, `telefono.sin_pais`, `telefono.no_valido` (se quita `consulta.telefono_invalido`), en es/en/pt. Sin migración (misma columna). Los teléfonos antiguos sin prefijo se siguen mostrando tal cual. Revisado en Chromium (escritorio y móvil) sobre una copia de la BD. Tests: 73 OK.
- 2026-11-28 — **Arreglo 6** (`ideas.md`, entradas 6 y 7): catálogo. (a) La barra de marcas (`rounded-full`) pasaba a dos filas por debajo de ~1100 px y la curva recortaba la pestaña de BMW: ahora `rounded-3xl`. (b) La lista de modelos tenía la barra oculta y en escritorio su final quedaba fuera de la pantalla (724 px desde y≈375): nueva utilidad `scroll-fino` (barra roja delgada y visible) en la lista y en la barra de marcas; en escritorio la lista mide lo mismo que la ficha (`lg:contain-size lg:self-stretch lg:min-h-80`, tope `100dvh-11rem`); `pestanas_controller.js` desplaza la lista hasta el modelo abierto al cargar y con el teclado (sin mover la página). Revisado en Chromium sin interfaz sobre una copia de la BD (1440×900 claro/oscuro, 1280×720, 900 y 390 px). Test del catálogo ampliado. Tests: 73 OK.
