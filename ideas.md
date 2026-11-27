# ideas.md — AutoPlaza

> Banco de ideas. **No se implementa nada de aquí hasta que se pida en el chat**: "aplica idea #X".
> Al aplicar una idea: revisar `PLAN.md`, implementarla, marcarla aquí como ✅ Hecha (con fecha) y registrar el cambio en `PLAN.md`.

Estados: ⏳ Pendiente · 🚧 En curso · ✅ Hecha

---

## #1 — Interfaz minimalista con escala azul–negro (modo claro y oscuro)
**Estado:** ✅ Hecha (2026-11-27)

- **Aplicada:** colores con `light-dark()` en `app.css`, botón ☾/☀ (`assets/controllers/tema_controller.js`), cabecera y pie con degradado azul → negro. Contraste revisado (todo ≥ 4.5:1).

- Rediseño minimalista: menos bordes y sombras, más espacio en blanco y jerarquía tipográfica clara.
- Paleta basada en una escala de azul a negro, aplicada en los dos modos:
  - **Claro:** fondo casi blanco, texto azul muy oscuro o negro, y acentos azules.
  - **Oscuro:** fondo negro o azul noche, texto claro y acentos azules más luminosos.
- Colores definidos como variables CSS (`--color-...`) en `assets/styles/app.css`.
- Modo automático según `prefers-color-scheme`, con un botón en la cabecera para cambiarlo a mano. La preferencia se guarda en `localStorage` (con un controlador Stimulus).
- Verificar el contraste (WCAG AA) en los dos modos.

## #2 — Idiomas: español, inglés, portugués, chino y japonés
**Estado:** ✅ Hecha (2026-11-27)

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
**Estado:** ⏳ Pendiente

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
**Estado:** 🚧 En curso (casi hecha, 2026-11-27)

- **Hecho junto con #5/#7/#8:** SQLite con Doctrine (`var/data.db`), entidades `Vehiculo`, `Usuario` y `Consulta`, migración y comando `app:importar-autos`. **Falta:** pasar a PostgreSQL si se quiere (cambiar `DATABASE_URL` e instalar la extensión `pdo_pgsql`).

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
**Estado:** ⏳ Pendiente

- Filtros por rango de precio, año, combustible y transmisión.
- Paginación del listado.

## #10 — Repositorio git
**Estado:** ⏳ Pendiente

- Inicializar git (`git init`) y hacer el primer commit. Conviene hacerlo antes de los cambios grandes. El `.gitignore` ya existe.
