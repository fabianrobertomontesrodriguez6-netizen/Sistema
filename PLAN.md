# PLAN.md — AutoPlaza (Sistema)

> **Leer este archivo antes de tocar o escribir código.** Aquí está lo que ya existe y lo que falta, para no repetir tareas.
> Al terminar una tarea, actualizar la sección correspondiente (Hecho / Pendiente / Registro de cambios).
> Las ideas numeradas están en `ideas.md`; se aplican cuando se piden por el chat ("aplica idea #X").

Última actualización: 2026-11-27

---

## 1. Qué es

Sitio web de un **concesionario de autos ("AutoPlaza")**.
- **Landing pública** con presentación y formulario de entrada; registro abierto.
- **Solo con sesión:** "Busca tu modelo" (todos los autos, búsqueda y filtros), catálogo en pestañas (marca → modelo), y detalle de cada auto con galería, datos del motor y formulario "Me interesa".
- **Solo el admin:** panel para gestionar autos, consultas y ver usuarios.

El código propio está en **español**. La web está en 5 idiomas: español (por defecto), inglés, portugués, chino y japonés.

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
| BD | **SQLite** (`var/data.db`) con Doctrine ORM 3 + Migrations. PostgreSQL de `compose.yaml` sin usar (no hay `pdo_pgsql` ni Docker) |
| Seguridad | Security: usuarios en BD, form_login en la landing, remember_me, login_throttling (`symfony/rate-limiter`) |
| Correo | Mailer, envío síncrono. `MAILER_DSN=null://null` (no sale nada) |
| Tests | PHPUnit 13 (+ browser-kit, css-selector). BD de tests: `var/test.db` |
| Instalados sin usar | Messenger (salvo la tabla de cola), Notifier, Serializer |

## 3. Cómo funciona

### Datos
- **Autos:** tabla `vehiculo` (entidad `Vehiculo`). Guarda los datos "en bruto" con la misma forma que `data/autos.json`: `descripcion` es `{idioma: texto}` y `medios` una lista `{tipo, src, alt: {idioma: texto}}` (columnas JSON).
- `data/autos.json` es la **semilla**: `php bin/console app:importar-autos` la carga en la BD (con `--reemplazar` borra antes). Editar el JSON ya no cambia la web: hay que reimportar o usar el panel.
- Campos de un auto: `slug, marca, modelo, anio, precio, kilometraje, combustible, transmision, carroceria, descripcion, medios[]` y, opcionales, `motor` (texto técnico igual en todos los idiomas, "5.0 L V8"), `potencia` (hp), `torque` (Nm), `traccion`, `aceleracion` (s 0–100), `velocidad_maxima` (km/h).
- `CatalogoAutos` lee los autos de una `FuenteAutos` (`FuenteBaseDatos` en la web, `FuenteJson` en la importación y los tests unitarios), los **valida** y los convierte en DTO `Auto`/`Medio` listos para mostrar. Un error lanza `CatalogoInvalidoException` con el lugar (`autos[3].medios[1]: ...`). El panel usa la misma validación antes de guardar.
- Medio `src`: URL `https://` tal cual; YouTube → `youtube-nocookie.com/embed/ID` (tipo interno `youtube`); `/uploads/autos/...` → archivo subido desde el panel (en `public/uploads/autos/`, ignorado por git); otra ruta → archivo en `assets/images/` (AssetMapper). Se bloquea `..` y se comprueban extensiones.
- `descripcion` y `alt`: texto (solo español) u objeto por idioma; `es` obligatorio y de respaldo (clase `TextoTraducido`).
- `combustible`, `transmision`, `carroceria` y `traccion` se guardan en español y se traducen con `translations/valores.*.yaml`. Lista de opciones del panel en `Catalogo/Valores.php` (un valor nuevo hay que añadirlo ahí y en los 5 archivos).
- **Usuarios:** tabla `usuario` (correo único, nombre, contraseña hasheada, roles). **Consultas:** tabla `consulta` (auto y usuario con `SET NULL` al borrar; guarda `autoNombre`, por si se borra el auto).

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
- Mensajes de Symfony que solo traen `zh_CN` (seguridad, validación) tienen copia propia en `translations/security.zh.yaml` y `validators.zh.yaml`.
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
| `Entity/Consulta.php` | Consulta "Me interesa" (leída / no leída). |
| `Repository/*.php` | `VehiculoRepository::todosOrdenados()`, `UsuarioRepository::buscarPorEmail()` (+ rehash de contraseñas), `ConsultaRepository::recientes()`, `contarSinLeer()`. |
| `Form/RegistroType.php`, `ConsultaType.php` | Registro y "Me interesa". |
| `Form/VehiculoType.php`, `MedioType.php`, `TraduccionesType.php` | Formulario de autos del panel (slug automático si se deja vacío; colección de medios con subida de archivo; un campo por idioma). |
| `Servicio/GestorMedios.php` | Mueve archivos subidos a `public/uploads/autos/` y limpia los medios antes de guardar. |
| `Servicio/NotificadorConsultas.php` | Envía el correo de la consulta a `CONCESIONARIO_EMAIL` (si falla, solo lo anota en el log). |
| `Command/ImportarAutosCommand.php` | `app:importar-autos [archivo] [--reemplazar]`: valida todo y carga el JSON en la BD. |
| `Command/CrearAdminCommand.php` | `app:crear-admin correo [nombre]`. |
| `Twig/FormatoExtension.php` | Filtro `numero`: separadores según el idioma (48.900 / 48,900). |
| `migrations/Version20261127173453.php` | Tablas `usuario`, `vehiculo`, `consulta` (+ cola de Messenger). |

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
| `messages+intl-icu.{es,en,pt,zh,ja}.yaml` | Textos de la interfaz (ICU, con plurales). Mismas claves en los 5. |
| `validators.{es,en,pt,zh,ja}.yaml` | Mensajes de validación propios (`campo.obligatorio`, etc.). |
| `valores.{es,en,pt,zh,ja}.yaml` | Combustible / transmisión / carrocería / tracción. |
| `security.zh.yaml` | Errores al entrar en chino. |

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
| `styles/app.css` | Estilos: fuentes, escala `--azul-*`, colores `light-dark()`, formularios, landing, búsqueda, catálogo en pestañas, panel. |
| `fonts/` | Michroma y Saira (OFL). |
| `images/autos/fiat-500-frente.jpg` | Única imagen local. |

### Tests — `tests/` (45 tests, 225 aserciones, **todos pasan**)
- `Soporte/BaseDeDatosDePrueba.php`: rehace `var/test.db` en cada test con los 8 autos + `admin@autoplaza.test` y `cliente@autoplaza.test`.
- `Catalogo/CatalogoAutosTest.php`: medios, filtrar+ordenar, búsqueda sin acentos, catálogo por marca y `secciones()`, datos del motor, archivos subidos, errores de validación, traducciones.
- `Controller/SeguridadControllerTest.php`: `/` por idioma, landing pública, sin sesión → landing, entrar/contraseña mala, registro (y errores traducidos), usuario normal sin panel (403), admin con panel, salir.
- `Controller/AutoControllerTest.php`: listado y 4 idiomas, búsqueda, filtros, selector de idioma, catálogo en pestañas (por defecto y con `?marca=&modelo=` de la URL), detalle con motor, detalle traducido, "Me interesa" (guarda + correo) y errores 422 en japonés, 404.
- `Controller/AdminControllerTest.php`: resumen y listados, crear auto con foto subida, no guarda un auto inválido, editar, borrar conservando consultas, marcar consulta leída.

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

Correo real: poner `MAILER_DSN` (por ejemplo `smtp://usuario:clave@servidor:587`) y `CONCESIONARIO_EMAIL` en `.env.local`.

## 6. Estado

### Hecho ✅
- [x] Catálogo con validación completa y mensajes claros.
- [x] Detalle con galería de imágenes, video mp4 y YouTube.
- [x] Diseño CSS propio, responsive.
- [x] Idea #1: interfaz minimalista azul–negro con modo claro/oscuro.
- [x] Idea #2: 5 idiomas (es, en, pt, zh, ja), incluido el contenido de los autos.
- [x] Idea #3: tipografía automotriz (Michroma + Saira).
- [x] Idea #4.1: "Busca tu modelo" + catálogo en pestañas (marca → modelo) + datos del motor.
- [x] Corregido: 403 al pulsar "Salir" (CSRF).
- [x] Idea #5: panel de administración (autos, consultas, usuarios).
- [x] Idea #7: formulario "Me interesa" (se guarda y se envía por correo).
- [x] Idea #8: landing con login, registro, roles usuario/admin.
- [x] Idea #6 casi completa: SQLite + Doctrine (falta PostgreSQL si se quiere).

### Pendiente
- Las ideas pendientes están en `ideas.md` (#4 medios reales, #6 PostgreSQL, #9 más filtros y paginación, #10 git).
- No hay recuperación de contraseña ("olvidé mi contraseña"): hace falta un correo configurado.
- Al borrar un auto o un medio no se borran sus archivos de `public/uploads/autos/`.

## 7. Registro de cambios
- 2026-11-27 — Se crea PLAN.md describiendo el estado actual del proyecto.
- 2026-11-27 — Se crea `ideas.md` (banco de ideas #1–#3: interfaz azul–negro claro/oscuro, idiomas, tipografía automotriz). Flujo: "aplica idea #X" por chat.
- 2026-11-27 — Las ideas pendientes de PLAN.md pasan a `ideas.md` (#4–#10). La idea #2 traduce la página entera, incluido el contenido de los autos.
- 2026-11-27 — Ideas #1, #2 y #3 aplicadas: diseño azul–negro con modo claro/oscuro, 5 idiomas con URLs `/{_locale}`, fuentes Michroma + Saira. Tests: 20 OK.
- 2026-11-27 — Ideas #4.1, #5, #7 y #8 aplicadas (y casi toda la #6): autos, usuarios y consultas en SQLite con Doctrine; landing pública con login y registro; catálogo solo con sesión; panel solo para admin; "Busca tu modelo" + catálogo por marca + datos del motor; "Me interesa". URLs nuevas: listado en `/{_locale}/autos` (antes `/{_locale}`). Tests: 44 OK.
- 2026-11-27 — Corregido 403 en `POST /{_locale}/salir`: la protección CSRF stateless solo cargaba en páginas con formularios de Symfony; ahora se importa en `app.js`. El catálogo pasa a pestañas anidadas (marca → modelo) que cambian sin recargar ni desplazar la página, con `?marca=&modelo=` en la URL. Tests: 45 OK.
