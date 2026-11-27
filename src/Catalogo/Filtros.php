<?php

declare(strict_types=1);

namespace App\Catalogo;

/**
 * Lo que el usuario eligió en "Busca tu modelo": búsqueda, filtros, orden y página.
 * null = sin ese filtro.
 */
final readonly class Filtros
{
    public const POR_PAGINA = 12;

    public function __construct(
        public ?string $texto = null,
        public ?string $marca = null,
        public ?string $carroceria = null,
        public ?string $combustible = null,
        public ?string $transmision = null,
        public ?int $precioMin = null,
        public ?int $precioMax = null,
        public ?int $anioMin = null,
        public ?int $anioMax = null,
        public string $orden = 'recientes',
        public int $pagina = 1,
    ) {
    }

    /**
     * ¿Hay algún filtro aplicado (aparte del orden y la página)?
     */
    public function activos(): bool
    {
        return null !== $this->texto || null !== $this->marca || null !== $this->carroceria
            || null !== $this->combustible || null !== $this->transmision
            || null !== $this->precioMin || null !== $this->precioMax
            || null !== $this->anioMin || null !== $this->anioMax;
    }

    /**
     * ¿Hay filtros de los que van en "Más filtros"? (para dejar ese panel abierto)
     */
    public function avanzados(): bool
    {
        return null !== $this->combustible || null !== $this->transmision
            || null !== $this->precioMin || null !== $this->precioMax
            || null !== $this->anioMin || null !== $this->anioMax;
    }
}
