# ideas.md — AutoPlaza

> Banco de ideas. **No se implementa nada de aquí hasta que se pida en el chat**: "aplica idea #X".
> Al aplicar una idea: revisar `PLAN.md`, implementarla, marcarla aquí como ✅ Hecha (con fecha) y registrar el cambio en `PLAN.md`.

Estados: ⏳ Pendiente · 🚧 En curso · ✅ Hecha

---

## #1 — Interfaz minimalista con escala azul–negro (modo claro y oscuro)
**Estado:** ✅ Hecha (2026-11-27)

- **Aplicada:** colores con `light-dark()` en `app.css`, botón ☾/☀ (`assets/controllers/tema_controller.js`), cabecera y pie con degradado azul → negro. Contraste revisado (todo ≥ 4.5:1).
- **Cambio (2026-11-28):** la paleta pasa al rojo del logo: rojo → blanco en modo claro y rojo → negro en modo oscuro (pedido por chat).

- Rediseño minimalista: menos bordes y sombras, más espacio en blanco y jerarquía tipográfica clara.
- Paleta basada en una escala de azul a negro, aplicada en los dos modos:
  - **Claro:** fondo casi blanco, texto azul muy oscuro o negro, y acentos azules.
  - **Oscuro:** fondo negro o azul noche, texto claro y acentos azules más luminosos.
- Colores definidos como variables CSS (`--color-...`) en `assets/styles/app.css`.
- Modo automático según `prefers-color-scheme`, con un botón en la cabecera para cambiarlo a mano. La preferencia se guarda en `localStorage` (con un controlador Stimulus).
- Verificar el contraste (WCAG AA) en los dos modos.

## #2 — Idiomas: español, inglés, portugués, chino y japonés
**Estado:** ✅ Hecha (2026-11-27)

- **Cambio (2026-11-28):** se quitan el chino y el japonés por pedido; quedan es, en y pt.

- **Aplicada:** rutas `/{_locale}/...`, `/` redirige según el navegador, `translations/messages+intl-icu.*.yaml` y `valores.*.yaml`, textos de los autos por idioma en `autos.json`, números con el filtro `numero`.

- Idiomas: `es` (por defecto), `en`, `pt`, `zh` y `ja`.
- Usar Symfony Translation (ya instalado) con archivos en `translations/` (`messages.es.yaml`, etc.).
- Idioma en la URL (`/{_locale}/...`) y un selector de idioma en la cabecera.
- Traducir todos los textos de la interfaz (menú, filtros, fichas, pie, etiquetas).
- Formato de precio y kilometraje según el idioma.
- **Se traduce la página entera**, incluido el contenido de los autos (`descripcion`, `alt` de los medios, valores como combustible, transmisión y carrocería). En `autos.json` los textos pasan a ser por idioma (por ejemplo `descripcion: {es, en, pt, zh, ja}`), con el español como respaldo si falta alguno.
- Chino y japonés necesitan una fuente con esos caracteres o una de respaldo (relacionado con la idea #3).
- Actualizar los tests para las rutas con idioma.

## #3 — Tipografía de estilo automotriz
**Estado:** ✅ Hecha (2026-11-27)

- **Aplicada:** **Michroma** (logo, títulos, precios) y **Saira** (texto) en `assets/fonts/`. Para chino y japonés se usan las fuentes del sistema (PingFang, Noto Sans SC/JP, Hiragino, Yu Gothic…) en lugar de cargar varios MB de fuentes CJK.

- Letras cuadradas o geométricas, estilizadas y con aspecto profesional.
- Candidatas (gratuitas, de Google Fonts): **Orbitron** o **Michroma** para el logo y los títulos, y **Rajdhani**, **Exo 2** o **Saira** para el texto, que tiene que leerse bien.
- Fuente de títulos y fuente de texto distintas, para que se siga leyendo bien.
- Archivos de fuente guardados en el proyecto (`assets/fonts/`, servidos con AssetMapper), sin CDN externo.
- Fuentes de respaldo para chino y japonés (por ejemplo Noto Sans SC o JP) si se aplica la idea #2.

## #4 — Medios reales
**Estado:** ✅ Hecha (2026-11-27)

- **Aplicada** (commit `1e7d703`, junto con la #9): 140 autos (20 por marca, 7 marcas) con fotos de Wikimedia Commons en `assets/images/autos/<marca>/`, con autor y licencia en cada foto, y anuncios oficiales de YouTube en 78 autos. Ya no hay fotos de Unsplash ni el video de MDN. Datos por marca en `data/marcas/*.json`, combinados en `data/autos.json` con `data/combinar_marcas.py`. **Importante:** después de cambiar `data/autos.json` hay que ejecutar `php bin/console app:importar-autos --reemplazar` (hasta el 2026-11-28 la base seguía con los 8 autos de antes).

- Reemplazar las fotos de Unsplash y el video de ejemplo del Mustang (un placeholder de MDN) por fotos y videos reales de los autos.

## #4.1 — Catálogo seccionado por marca y modelo, datos del motor y "Busca tu modelo"
**Estado:** ✅ Hecha (2026-11-27)

- **Aplicada:** `/{_locale}/autos` es "Busca tu modelo": barra de búsqueda con sugerencias (no importan acentos ni mayúsculas: "lamborghini", "huracan"), botón pequeño "Ver catálogo" debajo y filtros; sin búsqueda se ven todos los autos. `/{_locale}/catalogo` es el catálogo seccionado **en pestañas**: una pestaña por marca y, dentro, una pestaña por modelo; al elegir (por ejemplo BMW) solo cambia el contenido, sin recargar ni desplazar la página (la URL guarda `?marca=&modelo=`). Datos del motor (motor, potencia, par, tracción, 0–100 km/h, velocidad máxima) en la ficha y en las tarjetas, cargados para los 8 autos (valores aproximados de fábrica).
- Pedido original: «Hacer un catalogo seccionado por modelos y marcas. Anadirle datos sobre el motor etc. En el apartado general si se ven todos los autos (Osea la pagina que diga busca tu modelo y una barra de busqueda y este al ingresar Lamborghini le salgan todos los modelos de la marca pero si quiere hacerlo manual un boton pequeno debajo de la barra que diga ver catalogo y lo lleve entonces al catalogo seccionado)»

## #5 — Panel de administración
**Estado:** ✅ Hecha (2026-11-27)

- **Aplicada:** `/{_locale}/admin` (solo ROLE_ADMIN): resumen con cifras y últimas consultas; autos (crear, editar, borrar, con subida de fotos/videos a `public/uploads/autos/` o URL/YouTube, textos en 5 idiomas y datos del motor); consultas (marcar leída, borrar); lista de usuarios. Antes de guardar se valida igual que el catálogo, así nunca se guarda un auto que rompa la web.

- Cargar, editar y borrar autos y sus medios desde un panel (hoy `data/autos.json` se edita a mano).
- Requiere la idea #8 (login). Conviene hacerlo después de la #6 (base de datos).

## #6 — Migrar a base de datos
**Estado:** ✅ Hecha con SQLite (2026-11-27); PostgreSQL aplazado (2026-11-28)

- **Hecho junto con #5/#7/#8:** SQLite con Doctrine (`var/data.db`), entidades `Vehiculo`, `Usuario` y `Consulta`, migración y comando `app:importar-autos`.
- **Decisión (2026-11-28):** nos quedamos con SQLite, que basta para el volumen actual. PostgreSQL (o MariaDB) se elegirá cuando se sepa dónde se publica la web. Para cambiar: instalar `php8.4-pgsql` (o `php8.4-mysql`), crear la base, poner `DATABASE_URL` en `.env.local`, generar una migración nueva (la actual es SQL de SQLite), importar los autos y copiar los usuarios y las consultas.

- Pasar de `data/autos.json` a PostgreSQL con Doctrine (ya instalado; `Entity/` y `migrations/` están vacíos).
- Importar los autos actuales con una migración o un comando.

## #7 — Formulario de contacto / "Me interesa"
**Estado:** ✅ Hecha (2026-11-27)

- **Aplicada:** formulario en el detalle de cada auto, relleno con el nombre y el correo del usuario. La consulta se guarda y se ve en el panel; además se envía por correo a `CONCESIONARIO_EMAIL` cuando `MAILER_DSN` esté configurado (hoy es `null://null`: no sale ningún correo).

- Formulario en la página de detalle de cada auto que envía un correo al concesionario (Mailer ya instalado; Mailpit para pruebas).
- Protección CSRF y validación de los campos.

## #8 — Login y seguridad
**Estado:** ✅ Hecha (2026-11-27)

- **Aplicada (con landing):** `/{_locale}` es una landing pública con presentación y formulario de entrada. Sin sesión solo se ven la landing y el registro. Cualquiera puede registrarse como usuario normal, que solo ve el catálogo. El admin se crea con `php bin/console app:crear-admin correo@... "Nombre"` y es el único que ve el enlace "Panel" y puede abrir `/admin` (un usuario normal recibe 403). Bloqueo tras 5 intentos fallidos, "Recordarme" 30 días, CSRF en entrar, salir y formularios.

- Configurar `security.yaml` con usuarios y login para proteger el panel de administración (#5).

## #9 — Más filtros y paginación
**Estado:** ✅ Hecha (2026-11-27)

- **Aplicada** (commit `1e7d703`): en "Busca tu modelo", filtros de combustible, transmisión, rango de precio y de año y orden "más antiguos" (`Catalogo/Filtros.php`); paginación de 12 que conserva los filtros (`Catalogo/Pagina.php`).

- Filtros por rango de precio, año, combustible y transmisión.
- Paginación del listado.

## #10 — Repositorio git
**Estado:** ✅ Hecha (2026-11-27)

- **Aplicada:** repositorio creado; primer commit `262e557`.

- Inicializar git (`git init`) y hacer el primer commit. Conviene hacerlo antes de los cambios grandes. El `.gitignore` ya existe.

Arreglos -

1- Crear un filtro de validacion para no permitir espacios antes de las letras (ejemplo: "  Fabian") y no permitir caracteres especiales.
   - ✅ Hecho (2026-11-28): restricción `App\Validator\NombrePersona` en el nombre del registro, de "Me interesa" y de `app:crear-admin`. Rechaza espacios al inicio y todo lo que no sean letras (con acentos/ñ) separadas por un espacio, apóstrofo o guion (sin números ni símbolos). Mensajes en es/en/pt.

2- validacion de el emil, uso obligatorio de dominios reales
   - ✅ Hecho (2026-11-28): restricción `App\Validator\CorreoReal` en el correo de `Usuario` (registro, `app:crear-admin`) y de `Consulta` ("Me interesa"). Consulta el DNS: el dominio necesita MX (o, si no tiene, una IP); se rechazan los que no existen ("gmial.con") y los que declaran que no reciben correo (MX nulo, como example.com). Si el DNS no responde (equipo sin internet) se acepta y se anota en el log. Mensajes en es/en/pt.

3- La barra de buscar tu modelo que no salga el sub menu lateral si no un autocompletado de sugerencia
   - ✅ Hecho (2026-11-28): autocompletado **en línea**, sin listas ni menús: al escribir "Lambo" aparece "rghini" en gris claro dentro de la misma barra (`assets/controllers/autocompletar_controller.js`). Tab o → lo aceptan (en el móvil, tocar el texto gris); Escape lo quita; Enter busca lo escrito. Sin acentos ni mayúsculas ("hura" → "Huracán"); también completa modelos sueltos. Primer intento (lista desplegable debajo de la barra) descartado: no era lo que se pedía.

4- Areglar el sistema de filtrado para que no te coloque en el inicio de la pagina cuando apliques el filtro y se quede donde el usuario dejo la pagina.
   - ✅ Hecho (2026-11-28): al filtrar, ordenar, buscar o "Quitar filtros" la página se queda donde estaba el usuario (`assets/controllers/conservar_scroll_controller.js`: guarda la posición al enviar y la recupera cuando Turbo termina de cargar). Se conserva el historial (Atrás deshace el filtro). Al cambiar de página en la paginación se va a los resultados (`#resultados`), no al inicio. Revisado en Chromium con los 140 autos.

5- LibreTranslate debe arrancar solo junto con la página cuando se despliegue (igual cuando se ejecuta en local). Opciones: servicio de systemd en el servidor o contenedor en `compose.yaml` (imagen `libretranslate/libretranslate`, solo es/en/pt), con `LIBRETRANSLATE_URL` apuntando a él. Si no está activo, el panel muestra las consultas sin traducir y un aviso.
   - ✅ Hecho (2026-11-28): **en local**, `bin/servidor` arranca LibreTranslate (si está instalado y no está ya en marcha), Tailwind y la web con un solo comando; Ctrl+C lo para todo. **En el servidor**, `deploy/desplegar.sh` lo instala como servicio de systemd que arranca con el equipo y con Apache (`deploy/apache2-libretranslate.conf`), se reinicia solo si se cae y avisa si LibreTranslate no está instalado. Se eligió systemd (Docker no está instalado). Si no responde, el panel ya mostraba las consultas sin traducir con un aviso.

6- En catalogo hay un error visual en el body que contiene a los otros botones especialmente al de BMW que lo corta en una esquina. estos son los logs [Sat Nov 28 11:22:23 2026] [::1]:48744 [200]: GET /es/catalogo?marca=chevrolet
[Sat Nov 28 11:22:23 2026] [::1]:48744 Closing
[Sat Nov 28 11:22:23 2026] [::1]:48754 Accepted
[Sat Nov 28 11:22:24 2026] [::1]:48754 [200]: GET /es/catalogo?marca=lamborghini
[Sat Nov 28 11:22:24 2026] [::1]:48754 Closing
[Sat Nov 28 11:22:24 2026] [::1]:48764 Accepted
[Sat Nov 28 11:22:24 2026] [::1]:48764 [200]: GET /es/catalogo?marca=chevrolet
[Sat Nov 28 11:22:24 2026] [::1]:48764 Closing
[Sat Nov 28 11:22:24 2026] [::1]:48780 Accepted
[Sat Nov 28 11:22:24 2026] [::1]:48780 [200]: GET /es/catalogo?marca=fiat
[Sat Nov 28 11:22:24 2026] [::1]:48780 Closing
[Sat Nov 28 11:22:24 2026] [::1]:48790 Accepted
[Sat Nov 28 11:22:24 2026] [::1]:48790 [200]: GET /es/catalogo?marca=ford
[Sat Nov 28 11:22:24 2026] [::1]:48790 Closing
[Sat Nov 28 11:22:32 2026] [::1]:43866 Accepted
[Sat Nov 28 11:22:32 2026] [::1]:43866 [200]: GET /es/catalogo?marca=ford
[Sat Nov 28 11:22:32 2026] [::1]:43866 Closing
[Sat Nov 28 11:22:32 2026] [::1]:43872 Accepted
[Sat Nov 28 11:22:32 2026] [::1]:43872 [200]: GET /es/catalogo?marca=porsche
   - ✅ Hecho (2026-11-28): la barra de marcas tenía `rounded-full`; por debajo de ~1100 px pasa a dos filas y la curva (radio de la mitad de la altura) recortaba la pestaña de la esquina (BMW). Ahora tiene esquinas de 24 px (`rounded-3xl`): en una fila se ve igual de redonda y en dos ya no recorta.

7- Al estar en catalogo no me deja explorar entre los modelos porque no hay barra de desplazamiento 
   - ✅ Hecho (2026-11-28): la lista de modelos tenía la barra oculta (`scroll-sin-barra`) y en escritorio medía casi una pantalla desde la mitad de la página, así que su final quedaba fuera de la vista. Ahora usa `scroll-fino` (barra roja delgada y visible, también en la barra de marcas y en la fila de modelos del móvil); en escritorio mide lo mismo que la ficha del auto (mínimo 20 rem, tope de una pantalla); y se desplaza sola hasta el modelo abierto (`?modelo=` de la URL o con el teclado) sin mover la página.

8- En la seccion del formulario de me interesa hay que tener numeros validos, osea no se puede poner cualquier combinacion numerica.
Tiene que lleavr el id del pais (+X) y el numero telefonico de este
   - ✅ Hecho (2026-11-28): el teléfono de "Me interesa" se pide en dos partes, **país con su prefijo** ("Argentina (+54)", 245 países en el idioma de la página) y **número**, y se comprueba con libphonenumber (`giggsey/libphonenumber-for-php-lite`) que ese número exista en ese país: largo y formato correctos ("+54 123" no vale). También se puede escribir el número con su prefijo ("+34 612 34 56 78") sin elegir país. Se guarda en formato internacional (`+541155551234`) y se muestra legible en el panel y en el correo ("+54 11 5555-1234"). Sigue siendo opcional. Al elegir el país, el campo muestra un número de ejemplo de ese país. Mensajes en es/en/pt.