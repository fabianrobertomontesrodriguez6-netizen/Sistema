<?php

declare(strict_types=1);

namespace App\Catalogo;

use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * El inventario de autos, ya validado y listo para mostrar.
 *
 * Los datos salen de una FuenteAutos (en la web, la base de datos) con la forma de
 * data/autos.json. Cada medio ("src") puede ser:
 *  - una URL pública (https://...), que se usa tal cual;
 *  - un enlace de YouTube, que se convierte en un embed de youtube-nocookie;
 *  - un archivo subido desde el panel ("/uploads/autos/..."), servido desde public/;
 *  - una ruta relativa a assets/images/ (por ejemplo "autos/fiat.jpg"),
 *    que se convierte en la URL pública que genera AssetMapper.
 *
 * "descripcion" y "alt" pueden ser un texto (solo español) o un objeto por
 * idioma, por ejemplo {"es": "...", "en": "...", "pt": "..."}; "es" es obligatorio.
 */
final class CatalogoAutos
{
    /** Órdenes disponibles => clave de traducción de su etiqueta. */
    public const ORDENES = [
        'recientes' => 'orden.recientes',
        'precio_asc' => 'orden.precio_asc',
        'precio_desc' => 'orden.precio_desc',
        'km_asc' => 'orden.km_asc',
        'anio_asc' => 'orden.anio_asc',
    ];

    public const EXTENSIONES_IMAGEN = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif', 'svg'];
    public const EXTENSIONES_VIDEO = ['mp4', 'webm', 'ogv', 'ogg'];

    /** Carpeta pública (dentro de public/) donde el panel guarda los archivos subidos. */
    public const CARPETA_SUBIDAS = '/uploads/autos/';

    /** @var array<string, Auto>|null indexado por slug */
    private ?array $autos = null;

    public function __construct(
        private readonly FuenteAutos $fuente,
        #[Autowire('%kernel.project_dir%/assets/images')]
        private readonly string $carpetaImagenes,
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $carpetaPublica,
        private readonly Packages $packages,
        /** @var list<string> */
        #[Autowire('%kernel.enabled_locales%')]
        private readonly array $idiomas = ['es', 'en', 'pt'],
    ) {
    }

    /**
     * @return list<Auto>
     */
    public function todos(): array
    {
        return array_values($this->cargar());
    }

    public function buscarPorSlug(string $slug): ?Auto
    {
        return $this->cargar()[$slug] ?? null;
    }

    /**
     * Valida una fila (por ejemplo, la que va a guardar el panel) y devuelve el auto.
     *
     * @throws CatalogoInvalidoException si algo está mal escrito
     */
    public function validarFila(mixed $fila, string $donde = 'auto'): Auto
    {
        return $this->crearAuto($fila, $donde);
    }

    /**
     * Busca, filtra y ordena (sin paginar).
     *
     * @return list<Auto>
     */
    public function filtrar(Filtros $filtros): array
    {
        $palabras = array_filter(explode(' ', self::normalizar((string) $filtros->texto)));

        $autos = array_filter(
            $this->todos(),
            static fn (Auto $auto): bool => (null === $filtros->marca || $auto->marca === $filtros->marca)
                && (null === $filtros->carroceria || $auto->carroceria === $filtros->carroceria)
                && (null === $filtros->combustible || $auto->combustible === $filtros->combustible)
                && (null === $filtros->transmision || $auto->transmision === $filtros->transmision)
                && (null === $filtros->precioMin || $auto->precio >= $filtros->precioMin)
                && (null === $filtros->precioMax || $auto->precio <= $filtros->precioMax)
                && (null === $filtros->anioMin || $auto->anio >= $filtros->anioMin)
                && (null === $filtros->anioMax || $auto->anio <= $filtros->anioMax)
                && self::coincide($auto, $palabras),
        );

        usort($autos, match ($filtros->orden) {
            'precio_asc' => static fn (Auto $a, Auto $b): int => $a->precio <=> $b->precio,
            'precio_desc' => static fn (Auto $a, Auto $b): int => $b->precio <=> $a->precio,
            'km_asc' => static fn (Auto $a, Auto $b): int => $a->kilometraje <=> $b->kilometraje,
            'anio_asc' => static fn (Auto $a, Auto $b): int => $a->anio <=> $b->anio,
            default => static fn (Auto $a, Auto $b): int => [$b->anio, $a->marca, $a->modelo] <=> [$a->anio, $b->marca, $b->modelo],
        });

        return $autos;
    }

    /**
     * Busca, filtra, ordena y devuelve solo la página pedida.
     *
     * @return Pagina<Auto>
     */
    public function buscar(Filtros $filtros): Pagina
    {
        return Pagina::de($this->filtrar($filtros), $filtros->pagina, Filtros::POR_PAGINA);
    }

    /**
     * @param callable(Auto): string $campo
     *
     * @return list<string>
     */
    public function valores(callable $campo): array
    {
        return $this->valoresUnicos($campo);
    }

    /**
     * Precio y año mínimos y máximos del inventario (para los campos de rango).
     *
     * @return array{precio: array{int, int}, anio: array{int, int}}
     */
    public function rangos(): array
    {
        $autos = $this->todos();
        if ([] === $autos) {
            return ['precio' => [0, 0], 'anio' => [0, 0]];
        }
        $precios = array_map(static fn (Auto $auto): int => $auto->precio, $autos);
        $anios = array_map(static fn (Auto $auto): int => $auto->anio, $autos);

        return ['precio' => [min($precios), max($precios)], 'anio' => [min($anios), max($anios)]];
    }

    /**
     * Catálogo seccionado: marca => sus autos, ordenados por modelo y año.
     *
     * @return array<string, list<Auto>>
     */
    public function porMarca(): array
    {
        $secciones = [];
        foreach ($this->todos() as $auto) {
            $secciones[$auto->marca][] = $auto;
        }
        ksort($secciones, \SORT_NATURAL | \SORT_FLAG_CASE);

        foreach ($secciones as &$autos) {
            usort($autos, static fn (Auto $a, Auto $b): int => [$a->modelo, $b->anio] <=> [$b->modelo, $a->anio]);
        }

        return $secciones;
    }

    /**
     * Catálogo en pestañas: marcas y, dentro de cada una, sus modelos.
     * "clave" es el identificador para la URL (?marca=bmw&modelo=m4).
     *
     * @return list<array{clave: string, nombre: string, total: int, modelos: list<array{clave: string, nombre: string, autos: list<Auto>}>}>
     */
    public function secciones(): array
    {
        $slugger = new AsciiSlugger();
        $clave = static fn (string $texto): string => $slugger->slug($texto)->lower()->toString();

        $secciones = [];
        foreach ($this->porMarca() as $marca => $autos) {
            $modelos = [];
            foreach ($autos as $auto) {
                $modelos[$auto->modelo] ??= ['clave' => $clave($auto->modelo), 'nombre' => $auto->modelo, 'autos' => []];
                $modelos[$auto->modelo]['autos'][] = $auto;
            }
            $secciones[] = ['clave' => $clave($marca), 'nombre' => $marca, 'total' => \count($autos), 'modelos' => array_values($modelos)];
        }

        return $secciones;
    }

    /**
     * Sugerencias para la barra de búsqueda: marcas y "marca modelo".
     *
     * @return list<string>
     */
    public function sugerencias(): array
    {
        $sugerencias = [...$this->marcas()];
        foreach ($this->todos() as $auto) {
            $sugerencias[] = $auto->marca.' '.$auto->modelo;
        }
        $sugerencias = array_values(array_unique($sugerencias));
        sort($sugerencias, \SORT_NATURAL | \SORT_FLAG_CASE);

        return $sugerencias;
    }

    /**
     * @return list<string>
     */
    public function marcas(): array
    {
        return $this->valoresUnicos(static fn (Auto $auto): string => $auto->marca);
    }

    /**
     * @return list<string>
     */
    public function carrocerias(): array
    {
        return $this->valoresUnicos(static fn (Auto $auto): string => $auto->carroceria);
    }

    /**
     * Minúsculas y sin acentos: "Huracán" => "huracan".
     */
    public static function normalizar(string $texto): string
    {
        static $transliterador = null;
        $transliterador ??= \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC; Lower');

        return trim((string) preg_replace('/\s+/', ' ', (string) $transliterador->transliterate($texto)));
    }

    /**
     * @param list<string> $palabras ya normalizadas
     */
    private static function coincide(Auto $auto, array $palabras): bool
    {
        $texto = self::normalizar(\sprintf('%s %s %d', $auto->marca, $auto->modelo, $auto->anio));
        foreach ($palabras as $palabra) {
            if (!str_contains($texto, $palabra)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param callable(Auto): string $campo
     *
     * @return list<string>
     */
    private function valoresUnicos(callable $campo): array
    {
        $valores = array_unique(array_map($campo, $this->todos()));
        sort($valores);

        return $valores;
    }

    /**
     * @return array<string, Auto>
     */
    private function cargar(): array
    {
        if (null !== $this->autos) {
            return $this->autos;
        }

        $autos = [];
        foreach ($this->fuente->filas() as $i => $fila) {
            $auto = $this->crearAuto($fila, "autos[$i]");

            if (isset($autos[$auto->slug])) {
                throw new CatalogoInvalidoException(\sprintf('%s: el slug "%s" está repetido.', "autos[$i]", $auto->slug));
            }
            $autos[$auto->slug] = $auto;
        }

        return $this->autos = $autos;
    }

    private function crearAuto(mixed $fila, string $donde): Auto
    {
        if (!\is_array($fila)) {
            throw new CatalogoInvalidoException("$donde: cada auto debe ser un objeto JSON.");
        }

        $slug = $this->texto($fila, 'slug', $donde);
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            throw new CatalogoInvalidoException("$donde: el slug \"$slug\" solo puede tener minúsculas, números y guiones.");
        }

        $medios = $fila['medios'] ?? [];
        if (!\is_array($medios) || !array_is_list($medios)) {
            throw new CatalogoInvalidoException("$donde: \"medios\" debe ser una lista.");
        }

        return new Auto(
            slug: $slug,
            marca: $this->texto($fila, 'marca', $donde),
            modelo: $this->texto($fila, 'modelo', $donde),
            anio: $this->entero($fila, 'anio', $donde),
            precio: $this->entero($fila, 'precio', $donde),
            kilometraje: $this->entero($fila, 'kilometraje', $donde),
            combustible: $this->texto($fila, 'combustible', $donde),
            transmision: $this->texto($fila, 'transmision', $donde),
            carroceria: $this->texto($fila, 'carroceria', $donde),
            descripcion: $this->textoTraducido($fila, 'descripcion', $donde, obligatorio: true),
            medios: array_map(
                fn (mixed $medio, int $j): Medio => $this->crearMedio($medio, "$donde.medios[$j]"),
                $medios,
                array_keys($medios),
            ),
            motor: $this->textoOpcional($fila, 'motor', $donde),
            potencia: $this->enteroOpcional($fila, 'potencia', $donde),
            torque: $this->enteroOpcional($fila, 'torque', $donde),
            traccion: $this->textoOpcional($fila, 'traccion', $donde),
            aceleracion: $this->decimalOpcional($fila, 'aceleracion', $donde),
            velocidadMaxima: $this->enteroOpcional($fila, 'velocidad_maxima', $donde),
            version: $this->textoOpcional($fila, 'version', $donde) ?? '',
        );
    }

    private function crearMedio(mixed $fila, string $donde): Medio
    {
        if (!\is_array($fila)) {
            throw new CatalogoInvalidoException("$donde: cada medio debe ser un objeto JSON.");
        }

        $tipo = TipoMedio::tryFrom($this->texto($fila, 'tipo', $donde));
        // "youtube" no se escribe en el JSON: se detecta solo a partir de la URL del video.
        if (null === $tipo || TipoMedio::YouTube === $tipo) {
            throw new CatalogoInvalidoException("$donde: \"tipo\" debe ser \"imagen\" o \"video\".");
        }

        $src = $this->texto($fila, 'src', $donde);
        $alt = $this->textoTraducido($fila, 'alt', $donde, obligatorio: false);
        $credito = $this->textoOpcional($fila, 'credito', $donde);

        if (null !== $idYoutube = self::idYoutube($src)) {
            if (TipoMedio::Imagen === $tipo) {
                throw new CatalogoInvalidoException("$donde: un enlace de YouTube debe tener \"tipo\": \"video\".");
            }

            return new Medio(TipoMedio::YouTube, 'https://www.youtube-nocookie.com/embed/'.$idYoutube, $alt, $credito);
        }

        if (preg_match('#^https?://#i', $src)) {
            return new Medio($tipo, $src, $alt, $credito);
        }

        if (str_starts_with($src, self::CARPETA_SUBIDAS)) {
            return new Medio($tipo, $this->urlSubida($src, $tipo, $donde), $alt, $credito);
        }

        return new Medio($tipo, $this->urlLocal($src, $tipo, $donde), $alt, $credito);
    }

    /**
     * Valida un archivo dentro de assets/images/ y devuelve su URL pública.
     */
    private function urlLocal(string $src, TipoMedio $tipo, string $donde): string
    {
        $src = ltrim($src, '/');
        $this->validarRuta($src, $tipo, $donde);

        if (!is_file($this->carpetaImagenes.'/'.$src)) {
            throw new CatalogoInvalidoException("$donde: no existe el archivo assets/images/$src.");
        }

        return $this->packages->getUrl('images/'.$src);
    }

    /**
     * Valida un archivo subido desde el panel (public/uploads/autos/...) y devuelve su URL.
     */
    private function urlSubida(string $src, TipoMedio $tipo, string $donde): string
    {
        $this->validarRuta($src, $tipo, $donde);

        if (!is_file($this->carpetaPublica.$src)) {
            throw new CatalogoInvalidoException("$donde: no existe el archivo public$src.");
        }

        return $src;
    }

    private function validarRuta(string $src, TipoMedio $tipo, string $donde): void
    {
        if (str_contains($src, '..')) {
            throw new CatalogoInvalidoException("$donde: la ruta \"$src\" no puede contener \"..\".");
        }

        $permitidas = TipoMedio::Imagen === $tipo ? self::EXTENSIONES_IMAGEN : self::EXTENSIONES_VIDEO;
        $extension = strtolower(pathinfo($src, \PATHINFO_EXTENSION));
        if (!\in_array($extension, $permitidas, true)) {
            throw new CatalogoInvalidoException(\sprintf('%s: "%s" no es un %s válido (extensiones permitidas: %s).', $donde, $src, $tipo->value, implode(', ', $permitidas)));
        }
    }

    private static function idYoutube(string $url): ?string
    {
        $patron = '#^https?://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})#';

        return preg_match($patron, $url, $coincidencia) ? $coincidencia[1] : null;
    }

    /**
     * @param array<mixed> $fila
     */
    private function texto(array $fila, string $campo, string $donde): string
    {
        $valor = $fila[$campo] ?? null;
        if (!\is_string($valor) || '' === trim($valor)) {
            throw new CatalogoInvalidoException("$donde: falta el campo \"$campo\" o no es un texto.");
        }

        return trim($valor);
    }

    /**
     * @param array<mixed> $fila
     */
    private function textoOpcional(array $fila, string $campo, string $donde): ?string
    {
        if (null === ($fila[$campo] ?? null) || '' === $fila[$campo]) {
            return null;
        }

        return $this->texto($fila, $campo, $donde);
    }

    /**
     * Lee un texto que puede venir como string (español) o como objeto por idioma.
     *
     * @param array<mixed> $fila
     */
    private function textoTraducido(array $fila, string $campo, string $donde, bool $obligatorio): TextoTraducido
    {
        $valor = $fila[$campo] ?? null;

        if (null === $valor && !$obligatorio) {
            return TextoTraducido::soloEspanol('');
        }

        if (\is_string($valor) && ('' !== trim($valor) || !$obligatorio)) {
            return TextoTraducido::soloEspanol(trim($valor));
        }

        if (\is_array($valor) && [] === $valor && !$obligatorio) {
            return TextoTraducido::soloEspanol('');
        }

        if (!\is_array($valor) || array_is_list($valor)) {
            throw new CatalogoInvalidoException("$donde: \"$campo\" debe ser un texto o un objeto por idioma, por ejemplo {\"es\": \"...\", \"en\": \"...\"}.");
        }

        $textos = [];
        foreach ($valor as $idioma => $texto) {
            if (!\in_array($idioma, $this->idiomas, true)) {
                throw new CatalogoInvalidoException(\sprintf('%s: "%s" tiene el idioma "%s", que no existe (idiomas: %s).', $donde, $campo, $idioma, implode(', ', $this->idiomas)));
            }
            if (!\is_string($texto)) {
                throw new CatalogoInvalidoException("$donde: \"$campo.$idioma\" debe ser un texto.");
            }
            $textos[$idioma] = trim($texto);
        }

        if ('' === ($textos[TextoTraducido::IDIOMA_BASE] ?? '') && $obligatorio) {
            throw new CatalogoInvalidoException(\sprintf('%s: falta "%s.%s" (el español es obligatorio).', $donde, $campo, TextoTraducido::IDIOMA_BASE));
        }

        return new TextoTraducido($textos);
    }

    /**
     * @param array<mixed> $fila
     */
    private function entero(array $fila, string $campo, string $donde): int
    {
        $valor = $fila[$campo] ?? null;
        if (!\is_int($valor) || $valor < 0) {
            throw new CatalogoInvalidoException("$donde: \"$campo\" debe ser un número entero positivo.");
        }

        return $valor;
    }

    /**
     * @param array<mixed> $fila
     */
    private function enteroOpcional(array $fila, string $campo, string $donde): ?int
    {
        return null === ($fila[$campo] ?? null) ? null : $this->entero($fila, $campo, $donde);
    }

    /**
     * @param array<mixed> $fila
     */
    private function decimalOpcional(array $fila, string $campo, string $donde): ?float
    {
        $valor = $fila[$campo] ?? null;
        if (null === $valor) {
            return null;
        }
        if ((!\is_int($valor) && !\is_float($valor)) || $valor <= 0) {
            throw new CatalogoInvalidoException("$donde: \"$campo\" debe ser un número mayor que 0.");
        }

        return (float) $valor;
    }
}
