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
| CSS | `assets/styles/app.css` (CSS propio, sin framework), modo claro/oscuro con `light-dark()` |
| Fuentes | Michroma (títulos) y Saira (texto), guardadas en `assets/fonts/` |
| Idiomas | Symfony Translation, formato ICU (`translations/`) |
| BD | **SQLite** (`var/data.db`) con Doctrine ORM 3 + Migrations. Decidido seguir con SQLite (2026-11-28). PostgreSQL 17 está instalado en el equipo pero apagado y sin `pdo_pgsql`; el de `compose.yaml` no se usa (no hay Docker) |
| Seguridad | Security: usuarios en BD, form_login en la landing, remember_me, login_throttling (`symfony/rate-limiter`) |
| Correo | Mailer, envío síncrono. `MAILER_DSN=null://null` (no sale nada) |
| Traductor | **LibreTranslate** local (`LIBRETRANSLATE_URL`, por defecto `http://127.0.0.1:5000`) para los mensajes de las consultas. Instalado en `~/.local/share/libretranslate/venv` (fuera del proyecto), solo con los modelos es, en y pt. Cliente HTTP `libretranslate.client` en `framework.yaml` |
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
| `Twig/FormatoExtension.php` | Filtro `numero`: separadores según el idioma (48.900 / 48,900). Filtro `nombre_idioma`: "pt" → "portugués". |
| `migrations/Version20261127173453.php` | Tablas `usuario`, `vehiculo`, `consulta` (+ cola de Messenger). |
| `migrations/Version20261128125835.php` | `consulta.idioma_mensaje` y `consulta.traducciones`; `vehiculo.version` si falta (la idea #9 la había creado sin migración). |

### Plantillas — `templates/`
| Archivo | Qué hace |
|---|---|
| `base.html.twig` | Layout: cabecera con degradado, menú según rol (Buscar, Catálogo, Panel solo admin), idiomas, tema, nombre + "Salir"; avisos (flash); pie. |
| `seguridad/landing.html.twig`, `registro.html.twig` | Landing con login y fotos; registro. |
| `auto/listado.html.twig` | "Busca tu modelo": barra con `<datalist>`, botón "Ver catálogo", filtros, grilla. |
| `auto/catalogo.html.twig` | Pestañas de marcas y, dentro de cada marca, pestañas de modelos (columna lateral). Solo cambia el contenido: no recarga ni desplaza la página. |
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
| `controllers/pestanas_controller.js` | Pestañas accesibles y anidables (marcas → modelos): cambia el panel sin recargar, flechas del teclado, actualiza `?marca=&modelo=` con `replaceState`. Sin JS los enlaces recargan y funcionan igual. |
| `controllers/coleccion_controller.js` | Agregar/quitar fotos y videos en el formulario del panel. |
| `controllers/confirmar_controller.js` | "¿Seguro?" antes de borrar. |
| `controllers/csrf_protection_controller.js` | **Necesario**: genera los tokens CSRF stateless de los formularios (importado en `app.js`). |
| `controllers/hello_controller.js` | De la receta de Symfony (sin usar). |
| `styles/app.css` | Estilos: fuentes, escala `--rojo-*` (el 500 es el rojo del logo, #e31e24), colores `light-dark()`, cabecera de rojo → blanco (claro) / rojo → negro (oscuro) con el rojo siempre bajo el logo (`--inicio-contenedor`; en ≤ 900 px el degradado va de arriba abajo y los controles pasan a una segunda fila), formularios, landing, búsqueda, catálogo en pestañas, panel. |
| `fonts/` | Michroma y Saira (OFL). |
| `images/autoplaza.png` | Logo original (2000×795, fondo transparente). |
| `images/logo-cabecera.png`, `favicon-32.png`, `favicon-192.png`, `apple-touch-icon.png` | Generados desde `autoplaza.png` (sin márgenes): logo de la cabecera (sobre el texto AUTOPLAZA) e iconos de la pestaña del navegador. Si cambia el logo, hay que volver a generarlos. |
| `images/autos/<marca>/*.jpg` | Fotos de los 140 autos (Wikimedia Commons; autor y licencia en el campo de crédito de cada medio). |

### Tests — `tests/` (50 tests, 269 aserciones, **todos pasan**)
- `Soporte/LibreTranslateFalso.php`: sustituye a LibreTranslate en los tests (`mock_response_factory` en `framework.yaml`, servicio en `services.yaml` `when@test`).
- `Soporte/BaseDeDatosDePrueba.php`: rehace `var/test.db` en cada test con los 8 autos fijos de `tests/Fixtures/autos.json` + `admin@autoplaza.test` y `cliente@autoplaza.test`.
- `Catalogo/CatalogoAutosTest.php`: medios, filtrar+ordenar, búsqueda sin acentos, catálogo por marca y `secciones()`, datos del motor, archivos subidos, errores de validación, traducciones.
- `Controller/SeguridadControllerTest.php`: `/` por idioma (y ja → en), landing pública, sin sesión → landing, entrar/contraseña mala, registro (y errores traducidos), usuario normal sin panel (403), admin con panel, salir.
- `Controller/AutoControllerTest.php`: listado en en y pt, búsqueda, filtros, selector de idioma, catálogo en pestañas (por defecto y con `?marca=&modelo=` de la URL), detalle con motor, detalle traducido, "Me interesa" (guarda + correo) y errores 422 en portugués, 404.
- `Controller/AdminControllerTest.php`: resumen y listados, crear auto con foto subida, no guarda un auto inválido, editar, borrar conservando consultas, marcar consulta leída, consultas traducidas al idioma del panel (una sola vez) y aviso si el traductor no responde.

## 5. Comandos

```bash
# Primera vez (o en otro equipo):
php bin/console doctrine:migrations:migrate -n        # crea var/data.db
php bin/console app:importar-autos                    # carga data/autos.json (--reemplazar para rehacerlo)
php bin/console app:crear-admin tu@correo.com "Nombre"  # crea el admin (pide la contraseña)

# Servidor local → http://localhost:8000 (symfony CLI no está instalado).
# Los -d suben el límite de subida para fotos/videos grandes del panel (por defecto PHP admite 2 MB).
php -d upload_max_filesize=64M -d post_max_size=70M -S localhost:8000 -t public

php bin/phpunit                                       # tests
php bin/console debug:router                          # rutas
php bin/console debug:translation en --only-missing   # traducciones que faltan
php bin/console cache:clear
```

Traductor (LibreTranslate), en otra terminal o como servicio (ver más abajo):

```bash
~/.local/share/libretranslate/venv/bin/libretranslate --host 127.0.0.1 --port 5000 --load-only es,en,pt --disable-web-ui
```

Para que arranque solo al entrar en el equipo, el servicio de usuario está en `~/.config/systemd/user/libretranslate.service` (`systemctl --user enable --now libretranslate`).

Correo real: poner `MAILER_DSN` (por ejemplo `smtp://usuario:clave@servidor:587`) y `CONCESIONARIO_EMAIL` en `.env.local`.

## 6. Estado

### Hecho ✅
- [x] Catálogo con validación completa y mensajes claros.
- [x] Detalle con galería de imágenes, video mp4 y YouTube.
- [x] Diseño CSS propio, responsive.
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

### Pendiente
- No quedan ideas pendientes en `ideas.md` (PostgreSQL/MariaDB queda aplazado hasta elegir hosting, ver #6).
- No hay recuperación de contraseña ("olvidé mi contraseña"): hace falta un correo configurado.
- Al borrar un auto o un medio no se borran sus archivos de `public/uploads/autos/`.
- El correo al concesionario lleva el mensaje en su idioma original (no se traduce).

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
