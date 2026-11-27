<?php

declare(strict_types=1);

namespace App\Catalogo;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Lee los autos de un archivo con la forma {"autos": [ ... ]}.
 * La usa "app:importar-autos" para cargar data/autos.json en la base de datos.
 */
final class FuenteJson implements FuenteAutos
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/data/autos.json')]
        private readonly string $ruta,
    ) {
    }

    public function ruta(): string
    {
        return $this->ruta;
    }

    public function filas(): array
    {
        if (!is_file($this->ruta)) {
            throw new CatalogoInvalidoException(\sprintf('No existe el archivo de catálogo "%s".', $this->ruta));
        }

        try {
            $datos = json_decode((string) file_get_contents($this->ruta), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new CatalogoInvalidoException(\sprintf('El archivo "%s" no es un JSON válido: %s', $this->ruta, $e->getMessage()), 0, $e);
        }

        if (!\is_array($datos) || !\is_array($datos['autos'] ?? null)) {
            throw new CatalogoInvalidoException('El JSON del catálogo debe tener la forma {"autos": [ ... ]}.');
        }

        return $datos['autos'];
    }
}
