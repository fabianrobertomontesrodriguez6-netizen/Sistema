<?php

declare(strict_types=1);

namespace App\Catalogo;

/**
 * Una imagen o un video de un auto, con la URL ya lista para usar en el HTML.
 */
final readonly class Medio
{
    public function __construct(
        public TipoMedio $tipo,
        public string $url,
        public TextoTraducido $alt,
        // Autor y licencia de la foto, por ejemplo "Foto: Ana Pérez · CC BY-SA 4.0 · Wikimedia Commons".
        public ?string $credito = null,
    ) {
    }

    public function esImagen(): bool
    {
        return TipoMedio::Imagen === $this->tipo;
    }
}
